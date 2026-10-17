<?php
namespace Opencart\Admin\Controller\Extension\Paystack\Payment;
/**
 * Class Paystack
 *
 * @package Opencart\Admin\Controller\Extension\Paystack\Payment
 */
class Paystack extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/paystack/payment/paystack');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/paystack/payment/paystack', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/paystack/payment/paystack.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		// The secret key is never sent back to the browser
		$data['has_secret_key'] = (bool)$this->config->get('payment_paystack_secret_key');
		$data['webhook'] = HTTP_CATALOG . 'index.php?route=extension/paystack/payment/paystack.webhook';

		$data['payment_paystack_paid_status_id'] = (int)$this->config->get('payment_paystack_paid_status_id');
		$data['payment_paystack_failed_status_id'] = (int)$this->config->get('payment_paystack_failed_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['payment_paystack_geo_zone_id'] = $this->config->get('payment_paystack_geo_zone_id');

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_paystack_status'] = $this->config->get('payment_paystack_status');
		$data['payment_paystack_sort_order'] = $this->config->get('payment_paystack_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/paystack/payment/paystack', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/paystack/payment/paystack');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/paystack/payment/paystack')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$secret_key = trim((string)($this->request->post['payment_paystack_secret_key'] ?? ''));

		// Blank keeps the saved key
		if ($secret_key === '') {
			$secret_key = (string)$this->config->get('payment_paystack_secret_key');
		}

		if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $secret_key)) {
			$json['error']['secret_key'] = $this->language->get('error_secret_key');
		}

		if (!$json) {
			$this->request->post['payment_paystack_secret_key'] = $secret_key;

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_paystack', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
