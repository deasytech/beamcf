<?php
namespace Opencart\Catalog\Model\Extension\Geozone\Shipping;
/**
 * Class Geozone
 *
 * Can be called from $this->load->model('extension/geozone/shipping/geozone');
 *
 * @package Opencart\Catalog\Model\Extension\Geozone\Shipping
 */
class Geozone extends \Opencart\System\Engine\Model {
	/**
	 * Get Quote
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/geozone/shipping/geozone');

		$this->load->model('localisation/geo_zone');

		$language_id = (int)$this->config->get('config_language_id');
		$sub_total = $this->cart->getSubTotal();

		$quote_data = [];

		foreach ((array)$this->config->get('shipping_geozone_method') as $key => $method) {
			if (empty($method['status']) || $sub_total < (float)$method['total']) {
				continue;
			}

			if ($method['geo_zone_id'] && !$this->model_localisation_geo_zone->getGeoZone((int)$method['geo_zone_id'], (int)$address['country_id'], (int)$address['zone_id'])) {
				continue;
			}

			$name = $method['name'][$language_id] ?? reset($method['name']);

			if (!empty($method['instructions'][$language_id])) {
				$name .= ' (' . $method['instructions'][$language_id] . ')';
			}

			$cost = (float)$method['cost'];

			$quote_data['m' . $key] = [
				'code'         => 'geozone.m' . $key,
				'name'         => $name,
				'cost'         => $cost,
				'tax_class_id' => (int)$method['tax_class_id'],
				'text'         => $cost > 0 ? $this->currency->format($this->tax->calculate($cost, (int)$method['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : $this->language->get('text_free')
			];
		}

		$method_data = [];

		if ($quote_data) {
			$method_data = [
				'code'       => 'geozone',
				'name'       => $this->language->get('heading_title'),
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_geozone_sort_order'),
				'error'      => false
			];
		}

		return $method_data;
	}
}
