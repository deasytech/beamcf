<?php
namespace Opencart\Admin\Controller\Extension\Paystack\Payment;
/**
 * Class Paystack
 *
 * @package Opencart\Admin\Controller\Extension\Paystack\Payment
 */
class Paystack extends \Opencart\System\Engine\Controller {
    use \Opencart\System\Library\Extension\Beamcf\SettingsPage;

    private const ROUTE = 'extension/paystack/payment/paystack';
    private const PREFIX = 'payment_paystack_';

    /**
     * @return string
     */
    protected function route(): string {
        return self::ROUTE;
    }

    /**
     * @return string
     */
    protected function extensionType(): string {
        return 'payment';
    }

    /**
     * Index
     *
     * @return void
     */
    public function index(): void {
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        $data = [
            // The secret key is never sent back to the browser
            'has_secret_key' => (bool)$this->config->get(self::PREFIX . 'secret_key'),
            'webhook'        => HTTP_CATALOG . 'index.php?route=' . self::ROUTE . '.webhook',
            'order_statuses' => $this->model_localisation_order_status->getOrderStatuses(),
            'geo_zones'      => $this->model_localisation_geo_zone->getGeoZones()
        ];

        foreach (['paid_status_id', 'failed_status_id', 'geo_zone_id', 'status', 'sort_order'] as $name) {
            $data[self::PREFIX . $name] = $this->config->get(self::PREFIX . $name);
        }

        $this->renderPage($data);
    }

    /**
     * Save
     *
     * @return void
     */
    public function save(): void {
        $this->load->language(self::ROUTE);

        $json = [];

        // A blank field keeps the saved key
        $secret_key = trim((string)($this->request->post[self::PREFIX . 'secret_key'] ?? ''));

        if ($secret_key === '') {
            $secret_key = (string)$this->config->get(self::PREFIX . 'secret_key');
        }

        if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $secret_key)) {
            $json['error']['secret_key'] = $this->language->get('error_secret_key');
        }

        $this->saveSettings('payment_paystack', [self::PREFIX . 'secret_key' => $secret_key] + $this->request->post, $json);
    }
}
