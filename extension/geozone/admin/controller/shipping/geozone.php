<?php
namespace Opencart\Admin\Controller\Extension\Geozone\Shipping;
/**
 * Class Geozone
 *
 * @package Opencart\Admin\Controller\Extension\Geozone\Shipping
 */
class Geozone extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/geozone/shipping/geozone');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/geozone/shipping/geozone', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/geozone/shipping/geozone.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->load->model('localisation/tax_class');

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$data['shipping_geozone_methods'] = (array)$this->config->get('shipping_geozone_method');

		$data['shipping_geozone_status'] = $this->config->get('shipping_geozone_status');
		$data['shipping_geozone_sort_order'] = $this->config->get('shipping_geozone_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/geozone/shipping/geozone', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/geozone/shipping/geozone');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/geozone/shipping/geozone')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();

		$methods = [];

		foreach ((array)($this->request->post['shipping_geozone_method'] ?? []) as $key => $method) {
			$row = [
				'status'       => (int)($method['status'] ?? 0),
				'total'        => trim((string)($method['total'] ?? '0')),
				'cost'         => trim((string)($method['cost'] ?? '0')),
				'geo_zone_id'  => (int)($method['geo_zone_id'] ?? 0),
				'tax_class_id' => (int)($method['tax_class_id'] ?? 0),
				'name'         => [],
				'instructions' => []
			];

			foreach ($languages as $language) {
				$row['name'][$language['language_id']] = trim((string)($method['name'][$language['language_id']] ?? ''));
				$row['instructions'][$language['language_id']] = trim((string)($method['instructions'][$language['language_id']] ?? ''));

				if ($row['name'][$language['language_id']] === '') {
					$json['error']['name_' . $key . '_' . $language['language_id']] = $this->language->get('error_name');
				}
			}

			if (!is_numeric($row['cost']) || (float)$row['cost'] < 0) {
				$json['error']['cost_' . $key] = $this->language->get('error_cost');
			}

			if (!is_numeric($row['total']) || (float)$row['total'] < 0) {
				$json['error']['total_' . $key] = $this->language->get('error_total');
			}

			$methods[] = $row;
		}

		if (isset($json['error']) && !isset($json['error']['warning'])) {
			$json['error']['warning'] = $this->language->get('error_form');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('shipping_geozone', [
				'shipping_geozone_method'     => $methods,
				'shipping_geozone_status'     => (int)($this->request->post['shipping_geozone_status'] ?? 0),
				'shipping_geozone_sort_order' => (int)($this->request->post['shipping_geozone_sort_order'] ?? 0)
			]);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
