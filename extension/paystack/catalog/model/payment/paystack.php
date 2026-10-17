<?php
namespace Opencart\Catalog\Model\Extension\Paystack\Payment;
/**
 * Class Paystack
 *
 * Can be called from $this->load->model('extension/paystack/payment/paystack');
 *
 * @package Opencart\Catalog\Model\Extension\Paystack\Payment
 */
class Paystack extends \Opencart\System\Engine\Model {
    /**
     * Currencies Paystack can process (subject to what is enabled on the merchant account)
     *
     * @var array<int, string>
     */
    private const CURRENCIES = ['NGN', 'GHS', 'ZAR', 'KES', 'USD', 'XOF', 'EGP', 'RWF'];

    /**
     * Get Methods
     *
     * @param array<string, mixed> $address array of data
     *
     * @return array<string, mixed>
     */
    public function getMethods(array $address = []): array {
        if (!$this->isAvailable($address)) {
            return [];
        }

        $this->load->language('extension/paystack/payment/paystack');

        $name = $this->language->get('heading_title');

        return [
            'code'       => 'paystack',
            'name'       => $name,
            'option'     => ['paystack' => ['code' => 'paystack.paystack', 'name' => $name]],
            'sort_order' => $this->config->get('payment_paystack_sort_order')
        ];
    }

    /**
     * Amount to charge for an order, in the order's currency, in minor units (cents/kobo/pesewas)
     *
     * @param array<string, mixed> $order_info
     *
     * @return int
     */
    public function getAmount(array $order_info): int {
        $total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

        return (int)round($total * 100);
    }

    /**
     * Call the Paystack API
     *
     * @param string               $method GET or POST
     * @param string               $path   e.g. /transaction/initialize
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> decoded response, empty array on failure
     */
    public function request(string $method, string $path, array $data = []): array {
        $curl = curl_init('https://api.paystack.co' . $path);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->config->get('payment_paystack_secret_key'),
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ];

        if ($method == 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);

        if ($response === false) {
            $this->log->write('Paystack cURL error: ' . curl_error($curl));
        }

        curl_close($curl);

        $result = $response ? json_decode($response, true) : [];

        return is_array($result) ? $result : [];
    }

    /**
     * Whether the method can be offered: configured, supported currency, no subscriptions and, when the store
     * asks for a payment address, inside the configured geo zone.
     *
     * @param array<string, mixed> $address
     *
     * @return bool
     */
    private function isAvailable(array $address): bool {
        $currency = $this->session->data['currency'] ?? $this->config->get('config_currency');

        $usable = $this->config->get('payment_paystack_secret_key') && in_array($currency, self::CURRENCIES, true) && !$this->cart->hasSubscription();

        $zone_id = (int)$this->config->get('payment_paystack_geo_zone_id');

        if (!$usable || !$zone_id || !$this->config->get('config_checkout_payment_address')) {
            return (bool)$usable;
        }

        $this->load->model('localisation/geo_zone');

        return (bool)$this->model_localisation_geo_zone->getGeoZone($zone_id, (int)$address['country_id'], (int)$address['zone_id']);
    }
}
