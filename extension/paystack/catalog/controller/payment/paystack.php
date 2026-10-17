<?php
namespace Opencart\Catalog\Controller\Extension\Paystack\Payment;
/**
 * Class Paystack
 *
 * Hosted checkout: confirm() starts a transaction and redirects the customer to Paystack. The order is only
 * marked paid after the transaction has been verified with Paystack's API for the exact amount and currency
 * of the order, either by the customer returning (callback) or by the signed webhook.
 *
 * @package Opencart\Catalog\Controller\Extension\Paystack\Payment
 */
class Paystack extends \Opencart\System\Engine\Controller {
    private const ROUTE = 'extension/paystack/payment/paystack';
    private const MODEL = self::ROUTE;

    /**
     * Index
     *
     * @return string
     */
    public function index(): string {
        $this->load->language(self::ROUTE);

        return $this->load->view(self::ROUTE, ['language' => $this->config->get('config_language')]);
    }

    /**
     * Confirm
     *
     * @return void
     */
    public function confirm(): void {
        $this->load->language(self::ROUTE);
        $this->load->model('checkout/order');

        $json = [];

        $order_id = (int)($this->session->data['order_id'] ?? 0);

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $json['error'] = $this->language->get('error_order');

            if ($order_id) {
                $json['redirect'] = $this->url->link('checkout/failure', $this->languageQuery(), true);

                unset($this->session->data['order_id']);
            }
        }

        if (($this->session->data['payment_method']['code'] ?? '') != 'paystack.paystack') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$this->config->get('payment_paystack_secret_key')) {
            $json['error'] = $this->language->get('error_config');
        }

        if (!$json) {
            $json = $this->startTransaction($order_info);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Callback: the customer returns from Paystack
     *
     * @return void
     */
    public function callback(): void {
        $this->load->language(self::ROUTE);

        $reference = (string)($this->request->get['reference'] ?? $this->request->get['trxref'] ?? '');

        $result = $this->process($reference);

        if ($result == 'paid') {
            $this->response->redirect($this->url->link('checkout/success', $this->languageQuery(), true));
        } else {
            $this->session->data['error'] = $this->language->get($result == 'failed' ? 'error_failed' : 'error_verify');

            $this->response->redirect($this->url->link('checkout/failure', $this->languageQuery(), true));
        }
    }

    /**
     * Webhook: server to server notification from Paystack
     *
     * @return void
     */
    public function webhook(): void {
        $payload = (string)file_get_contents('php://input');
        $signature = (string)($this->request->server['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
        $secret = (string)$this->config->get('payment_paystack_secret_key');

        if (!$payload || !$signature || !$secret || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            http_response_code(400);

            exit();
        }

        $event = json_decode($payload, true);

        if (is_array($event) && ($event['event'] ?? '') == 'charge.success' && !empty($event['data']['reference'])) {
            $this->process((string)$event['data']['reference']);
        }

        // Acknowledge so Paystack does not retry
        http_response_code(200);

        exit();
    }

    /**
     * @return string
     */
    private function languageQuery(): string {
        return 'language=' . $this->config->get('config_language');
    }

    /**
     * Ask Paystack to start a transaction for the order.
     *
     * @param array<string, mixed> $order_info
     *
     * @return array<string, string> redirect or error
     */
    private function startTransaction(array $order_info): array {
        $this->load->model(self::MODEL);

        $result = $this->model_extension_paystack_payment_paystack->request('POST', '/transaction/initialize', [
            'email'        => $order_info['email'],
            'amount'       => $this->model_extension_paystack_payment_paystack->getAmount($order_info),
            'currency'     => $order_info['currency_code'],
            'reference'    => 'OC' . (int)$order_info['order_id'] . '-' . bin2hex(random_bytes(8)),
            'callback_url' => $this->url->link(self::ROUTE . '.callback', $this->languageQuery(), true),
            'metadata'     => ['order_id' => (int)$order_info['order_id']]
        ]);

        if (!empty($result['status']) && !empty($result['data']['authorization_url'])) {
            return ['redirect' => $result['data']['authorization_url']];
        }

        $this->log->write('Paystack initialize failed: ' . ($result['message'] ?? 'no response'));

        return ['error' => $this->language->get('error_init')];
    }

    /**
     * Verify a transaction with Paystack and update the order. Safe to call more than once.
     *
     * @param string $reference
     *
     * @return string paid | failed | error
     */
    private function process(string $reference): string {
        $order_info = [];

        $transaction = $this->verified($reference, $order_info);

        if (!$transaction) {
            return 'error';
        }

        return $this->settle($transaction, $order_info, $reference);
    }

    /**
     * Fetch the transaction from Paystack and make sure it belongs to one of our orders.
     *
     * @param string               $reference
     * @param array<string, mixed> $order_info filled with the order the transaction belongs to
     *
     * @return array<string, mixed> the verified transaction, empty when it cannot be trusted
     */
    private function verified(string $reference, array &$order_info): array {
        $transaction = [];

        if (preg_match('/^OC\d+-[a-f0-9]{16}$/', $reference)) {
            $this->load->model(self::MODEL);
            $this->load->model('checkout/order');

            $result = $this->model_extension_paystack_payment_paystack->request('GET', '/transaction/verify/' . $reference);

            $data = (array)($result['data'] ?? []);

            $order_id = (int)($data['metadata']['order_id'] ?? 0);

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!empty($result['status']) && ($data['reference'] ?? '') === $reference && $order_info && str_starts_with($reference, 'OC' . $order_id . '-')) {
                $transaction = $data;
            }
        }

        return $transaction;
    }

    /**
     * Apply a verified transaction to its order.
     *
     * @param array<string, mixed> $transaction
     * @param array<string, mixed> $order_info
     * @param string               $reference
     *
     * @return string paid | failed | error
     */
    private function settle(array $transaction, array $order_info, string $reference): string {
        $this->load->language(self::ROUTE);

        $order_id = (int)$order_info['order_id'];

        $result = 'failed';

        if ($transaction['status'] === 'success') {
            $result = $this->amountMatches($transaction, $order_info) ? 'paid' : 'error';

            if ($result == 'paid' && $this->canMarkPaid($order_info)) {
                $this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_paystack_paid_status_id'), sprintf($this->language->get('text_paid'), $reference), true);
            }
        } elseif ($transaction['status'] === 'failed' && !$order_info['order_status_id']) {
            $this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_paystack_failed_status_id'), sprintf($this->language->get('text_failed'), $reference), false);
        }

        return $result;
    }

    /**
     * Amount and currency must match the order exactly.
     *
     * @param array<string, mixed> $transaction
     * @param array<string, mixed> $order_info
     *
     * @return bool
     */
    private function amountMatches(array $transaction, array $order_info): bool {
        $this->load->model(self::MODEL);

        $matches = (int)$transaction['amount'] === $this->model_extension_paystack_payment_paystack->getAmount($order_info) && $transaction['currency'] === $order_info['currency_code'];

        if (!$matches) {
            $this->log->write('Paystack amount/currency mismatch for order ' . $order_info['order_id']);
        }

        return $matches;
    }

    /**
     * Only an order that has not been touched (status 0) or that this gateway marked failed may become paid, so
     * a late notification can never revive a cancelled or refunded order or repeat the paid history entry.
     *
     * @param array<string, mixed> $order_info
     *
     * @return bool
     */
    private function canMarkPaid(array $order_info): bool {
        $status_id = (int)$order_info['order_status_id'];

        return $status_id === 0 || $status_id === (int)$this->config->get('payment_paystack_failed_status_id');
    }
}
