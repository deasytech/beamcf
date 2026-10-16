<?php
namespace Opencart\Catalog\Controller\Extension\Beamcf\Product;
/**
 * Class Quickview
 *
 * Returns the HTML fragment for the product quick-view dialog. Product data, options, subscription plans and prices
 * come from the same catalog model calls and tax/currency formatting as product/product; adding to the cart is
 * done by the core checkout/cart.add route, nothing is re-implemented here.
 *
 * Route: extension/beamcf/product/quickview&product_id=N
 *
 * @package Opencart\Catalog\Controller\Extension\Beamcf\Product
 */
class Quickview extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('product/product');
		$this->load->language('extension/beamcf/theme/beamcf');

		$language = 'language=' . $this->config->get('config_language');

		$this->load->model('catalog/product');

		$product_id = (int)($this->request->get['product_id'] ?? 0);
		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
			$this->response->setOutput($this->load->view('extension/beamcf/product/quickview_error', [
				'text_error' => $this->language->get('bcf_qv_unavailable')
			]));

			return;
		}

		$data = [];

		$data['product_id'] = $product_info['product_id'];
		$data['name'] = $product_info['name'];
		$data['href'] = $this->url->link('product/product', $language . '&product_id=' . $product_info['product_id']);
		$data['model'] = $product_info['model'];
		$data['reward'] = $product_info['reward'];
		$data['review_status'] = (int)$this->config->get('config_review_status');
		$data['rating'] = (int)$product_info['rating'];
		$data['text_reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);
		$data['minimum'] = $product_info['minimum'] ?: 1;
		$data['text_minimum'] = sprintf($this->language->get('text_minimum'), $product_info['minimum']);
		$data['language'] = $this->config->get('config_language');

		$data['product_codes'] = [];

		foreach ($this->model_catalog_product->getCodes($product_info['product_id']) as $code) {
			if ($code['status']) {
				$data['product_codes'][] = $code;
			}
		}

		$this->load->model('catalog/manufacturer');

		$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

		$data['manufacturer'] = $manufacturer_info ? $manufacturer_info['name'] : '';

		$data['stock'] = $this->stock($product_info);

		$this->images($product_info, $data);
		$this->prices($product_info, $data);
		$this->options($product_info, $data);
		$this->subscriptions($product_info, $data);

		$this->session->data['upload_token'] = oc_token(32);

		$data['upload'] = $this->url->link('tool/upload', $language . '&upload_token=' . $this->session->data['upload_token']);
		$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);
		$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

		$data['cart_add'] = $this->url->link('checkout/cart.add', $language);
		$data['wishlist_add'] = $this->url->link('account/wishlist.add', $language);

		$this->response->setOutput($this->load->view('extension/beamcf/product/quickview', $data));
	}

	/**
	 * Same availability rule as product/product: the stock status name when the product (or a required option) is
	 * out of stock, otherwise the configured in-stock label, or the quantity when stock display is on.
	 *
	 * @param array<string, mixed> $product_info
	 *
	 * @return string
	 */
	protected function stock(array $product_info): string {
		if ($product_info['quantity'] <= 0 || $this->requiredOptionOutOfStock($product_info)) {
			$stock_status_id = $product_info['stock_status_id'];
		} elseif (!$this->config->get('config_stock_display')) {
			$stock_status_id = (int)$this->config->get('config_stock_status_id');
		} else {
			$stock_status_id = 0;
		}

		$this->load->model('localisation/stock_status');

		$stock_status_info = $this->model_localisation_stock_status->getStockStatus($stock_status_id);

		return $stock_status_info ? $stock_status_info['name'] : (string)$product_info['quantity'];
	}

	/**
	 * True when a required option has no value that can still be chosen.
	 *
	 * @param array<string, mixed> $product_info
	 *
	 * @return bool
	 */
	protected function requiredOptionOutOfStock(array $product_info): bool {
		$master_id = (int)($product_info['master_id'] ?: $product_info['product_id']);

		foreach ($this->model_catalog_product->getOptions($master_id) as $option) {
			if (!$option['required'] || !in_array($option['type'], ['select', 'radio', 'checkbox', 'image'])) {
				continue;
			}

			$available = false;

			foreach ($option['product_option_value'] as $value) {
				if (!$value['subtract'] || $value['quantity'] > 0) {
					$available = true;
					break;
				}
			}

			if (!$available) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $product_info
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	protected function images(array $product_info, array &$data): void {
		$this->load->model('tool/image');

		$data['images'] = [];

		$sources = [];

		if ($product_info['image']) {
			$sources[] = $product_info['image'];
		}

		foreach ($this->model_catalog_product->getImages($product_info['product_id']) as $result) {
			$sources[] = $result['image'];
		}

		$width = (int)$this->config->get('config_image_popup_width');
		$height = (int)$this->config->get('config_image_popup_height');

		foreach ($sources as $source) {
			if ($source && is_file(DIR_IMAGE . html_entity_decode($source, ENT_QUOTES, 'UTF-8'))) {
				$data['images'][] = [
					'main'  => $this->model_tool_image->resize($source, $width, $height),
					'thumb' => $this->model_tool_image->resize($source, 80, 80)
				];
			}
		}

		$data['image_width'] = $width;
		$data['image_height'] = $height;
	}

	/**
	 * @param array<string, mixed> $product_info
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	protected function prices(array $product_info, array &$data): void {
		$currency = $this->session->data['currency'];
		$tax_class_id = $product_info['tax_class_id'];
		$show = $this->customer->isLogged() || !$this->config->get('config_customer_price');

		$data['price'] = $show ? $this->currency->format($this->tax->calculate($product_info['price'], $tax_class_id, $this->config->get('config_tax')), $currency) : false;
		$data['special'] = $show && (float)$product_info['special'] ? $this->currency->format($this->tax->calculate($product_info['special'], $tax_class_id, $this->config->get('config_tax')), $currency) : false;
		$data['tax'] = $show && $this->config->get('config_tax') ? $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price'], $currency) : false;

		$data['discount_percent'] = 0;

		if ($show && (float)$product_info['special'] && (float)$product_info['price'] > 0 && $product_info['special'] < $product_info['price']) {
			$data['discount_percent'] = (int)round((1 - $product_info['special'] / $product_info['price']) * 100);
		}

		$data['discounts'] = [];

		if ($show) {
			foreach ($this->model_catalog_product->getDiscounts($product_info['product_id']) as $discount) {
				$data['discounts'][] = ['price' => $this->currency->format($this->tax->calculate($discount['price'], $tax_class_id, $this->config->get('config_tax')), $currency)] + $discount;
			}
		}
	}

	/**
	 * Options, with the same filtering as product/product: variant-overridden options and unavailable values are left out.
	 *
	 * @param array<string, mixed> $product_info
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	protected function options(array $product_info, array &$data): void {
		$this->load->model('tool/image');

		$master_id = (int)($product_info['master_id'] ?: $product_info['product_id']);
		$show_price = ($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price');

		$data['options'] = [];

		foreach ($this->model_catalog_product->getOptions($master_id) as $option) {
			if (isset($product_info['override']['variant'][$option['product_option_id']])) {
				continue;
			}

			$values = [];

			foreach ($option['product_option_value'] as $option_value) {
				if ($option_value['subtract'] && $option_value['quantity'] <= 0) {
					continue;
				}

				$price = false;

				if ($show_price && (float)$option_value['price']) {
					$price = $this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				}

				$image = '';

				if ($option_value['image'] && is_file(DIR_IMAGE . html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $this->model_tool_image->resize($option_value['image'], 50, 50);
				}

				$values[] = ['image' => $image, 'price' => $price] + $option_value;
			}

			$data['options'][] = ['product_option_value' => $values] + $option;
		}
	}

	/**
	 * @param array<string, mixed> $product_info
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	protected function subscriptions(array $product_info, array &$data): void {
		$data['subscription_plans'] = [];

		$show = $this->customer->isLogged() || !$this->config->get('config_customer_price');

		foreach ($this->model_catalog_product->getSubscriptions($product_info['product_id']) as $result) {
			$description = '';

			if ($show) {
				$base = $product_info['special'] ?: $product_info['price'];
				$price = $result['duration'] ? $base / $result['duration'] : $base;
				$price = $this->currency->format($this->tax->calculate($price, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$frequency = $this->language->get('text_' . $result['frequency']);

				if ($result['duration']) {
					$description = sprintf($this->language->get('text_subscription_duration'), $price, $result['cycle'], $frequency, $result['duration']);
				} else {
					$description = sprintf($this->language->get('text_subscription_cancel'), $price, $result['cycle'], $frequency);
				}
			}

			$data['subscription_plans'][] = ['description' => $description] + $result;
		}
	}
}
