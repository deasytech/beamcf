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
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/paystack/payment/paystack');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/paystack/payment/paystack', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/paystack/payment/paystack');

		$json = [];

		$order_info = [];

		if (isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if (!$order_info) {
				$json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

				unset($this->session->data['order_id']);
			}
		} else {
			$json['error'] = $this->language->get('error_order');
		}

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'paystack.paystack') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		if (!$this->config->get('payment_paystack_secret_key')) {
			$json['error'] = $this->language->get('error_config');
		}

		if (!$json) {
			$this->load->model('extension/paystack/payment/paystack');

			$result = $this->model_extension_paystack_payment_paystack->request('POST', '/transaction/initialize', [
				'email'        => $order_info['email'],
				'amount'       => $this->model_extension_paystack_payment_paystack->getAmount($order_info),
				'currency'     => $order_info['currency_code'],
				'reference'    => 'OC' . (int)$order_info['order_id'] . '-' . bin2hex(random_bytes(8)),
				'callback_url' => $this->url->link('extension/paystack/payment/paystack.callback', 'language=' . $this->config->get('config_language'), true),
				'metadata'     => ['order_id' => (int)$order_info['order_id']]
			]);

			if (!empty($result['status']) && !empty($result['data']['authorization_url'])) {
				$json['redirect'] = $result['data']['authorization_url'];
			} else {
				$this->log->write('Paystack initialize failed: ' . ($result['message'] ?? 'no response'));

				$json['error'] = $this->language->get('error_init');
			}
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
		$this->load->language('extension/paystack/payment/paystack');

		$language = 'language=' . $this->config->get('config_language');

		$reference = (string)($this->request->get['reference'] ?? $this->request->get['trxref'] ?? '');

		$result = $this->process($reference);

		if ($result == 'paid') {
			$this->response->redirect($this->url->link('checkout/success', $language, true));
		} else {
			$this->session->data['error'] = $this->language->get($result == 'failed' ? 'error_failed' : 'error_verify');

			$this->response->redirect($this->url->link('checkout/failure', $language, true));
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
	 * Verify a transaction with Paystack and update the order. Safe to call more than once.
	 *
	 * @param string $reference
	 *
	 * @return string paid | failed | error
	 */
	private function process(string $reference): string {
		if (!preg_match('/^OC\d+-[a-f0-9]{16}$/', $reference)) {
			return 'error';
		}

		$this->load->model('extension/paystack/payment/paystack');
		$this->load->model('checkout/order');

		$result = $this->model_extension_paystack_payment_paystack->request('GET', '/transaction/verify/' . $reference);

		if (empty($result['status']) || empty($result['data']['reference']) || $result['data']['reference'] !== $reference) {
			return 'error';
		}

		$transaction = $result['data'];

		$order_id = (int)($transaction['metadata']['order_id'] ?? 0);

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info || !str_starts_with($reference, 'OC' . $order_id . '-')) {
			return 'error';
		}

		$this->load->language('extension/paystack/payment/paystack');

		if ($transaction['status'] === 'success') {
			// Amount and currency must match the order exactly
			if ((int)$transaction['amount'] !== $this->model_extension_paystack_payment_paystack->getAmount($order_info) || $transaction['currency'] !== $order_info['currency_code']) {
				$this->log->write('Paystack amount/currency mismatch for order ' . $order_id . ' reference ' . $reference);

				return 'error';
			}

			$paid_statuses = array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'));

			if (!in_array($order_info['order_status_id'], $paid_statuses)) {
				$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_paystack_paid_status_id'), sprintf($this->language->get('text_paid'), $reference), true);
			}

			return 'paid';
		}

		if ($transaction['status'] === 'failed' && !$order_info['order_status_id']) {
			$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_paystack_failed_status_id'), sprintf($this->language->get('text_failed'), $reference), false);
		}

		return 'failed';
	}
}
