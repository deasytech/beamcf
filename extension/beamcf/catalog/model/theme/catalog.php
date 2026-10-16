<?php
namespace Opencart\Catalog\Model\Extension\Beamcf\Theme;
/**
 * Class Catalog
 *
 * Adds price, brand, availability, colour, size, discount and rating filters to the category listing without touching core files.
 * The Catalog event redirects catalog/product.getProducts and getTotalProducts here only for the category being
 * listed and only while a BeamCF filter is in the request. This class extends the core product model, so the
 * unfiltered rows and their ordering come from OpenCart itself; the filters are then applied to those rows using
 * the same price, tax and currency logic the product card uses.
 *
 * Can be called from $this->load->model('extension/beamcf/theme/catalog');
 *
 * @package Opencart\Catalog\Model\Extension\Beamcf\Theme
 */
class Catalog extends \Opencart\Catalog\Model\Catalog\Product {
	/**
	 * Rows already loaded in this request, so the listing, its count and the facets share one load per data set.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $rows = [];

	/**
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $filtered = [];

	/**
	 * Get Active
	 *
	 * Reads and sanitises the filter values from the request.
	 *
	 * @return array<string, mixed>
	 */
	public function getActive(): array {
		$get = $this->request->get;

		$active = [
			'price_min' => isset($get['price_min']) && is_numeric($get['price_min']) && (float)$get['price_min'] > 0 ? (float)$get['price_min'] : null,
			'price_max' => isset($get['price_max']) && is_numeric($get['price_max']) && (float)$get['price_max'] > 0 ? (float)$get['price_max'] : null,
			'brand'     => [],
			'stock'     => !empty($get['stock']),
			'rating'    => isset($get['rating']) ? min(5, max(0, (int)$get['rating'])) : 0,
			'preorder'  => !empty($get['preorder']),
			'color'     => $this->ids($get['color'] ?? []),
			'size'      => $this->ids($get['size'] ?? []),
			'discount'  => isset($get['discount']) ? min(95, max(0, (int)$get['discount'])) : 0,
			'q'         => isset($get['q']) ? trim(mb_substr((string)$get['q'], 0, 100)) : ''
		];

		$active['brand'] = $this->ids($get['brand'] ?? []);

		return $active;
	}

	/**
	 * @param mixed $value array or comma separated list
	 *
	 * @return array<int, int>
	 */
	protected function ids($value): array {
		$value = is_array($value) ? $value : explode(',', (string)$value);

		return array_values(array_unique(array_filter(array_map('intval', $value))));
	}

	/**
	 * Has Active
	 *
	 * @param array<string, mixed> $active
	 *
	 * @return bool
	 */
	public function hasActive(array $active): bool {
		return $active['price_min'] !== null || $active['price_max'] !== null || $active['brand'] || $active['stock'] || $active['preorder'] || $active['color'] || $active['size'] || $active['discount'] || $active['rating'] || $active['q'] !== '';
	}

	/**
	 * Get Products
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProducts(array $data = []): array {
		$rows = $this->filterRows($data);

		if (isset($data['start']) || isset($data['limit'])) {
			$start = max(0, (int)($data['start'] ?? 0));
			$limit = (int)($data['limit'] ?? 20);

			$rows = array_slice($rows, $start, $limit > 0 ? $limit : 20);
		}

		return $rows;
	}

	/**
	 * Get Total Products
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function getTotalProducts(array $data = []): int {
		return count($this->filterRows($data));
	}

	/**
	 * Get Facets
	 *
	 * Describes what the filter panel can offer, based on the unfiltered product set of the category.
	 *
	 * @param array<string, mixed> $data core filter data of the listing (category, filter groups)
	 *
	 * @return array<string, mixed>
	 */
	public function getFacets(array $data): array {
		// The unsearched total tells an empty category from a search or filter that matches nothing.
		$total = count($this->getRows($data));

		// Facets describe the searched set, so their counts match what the shopper is looking at.
		$active = $this->getActive();

		if ($active['q'] !== '') {
			$data['filter_search'] = $active['q'];
		}

		$rows = $this->getRows($data);

		$facets = [
			'total'    => $total,
			'price'    => null,
			'brands'   => [],
			'stock'    => 0,
			'preorder' => 0,
			'color'    => [],
			'size'     => [],
			'discount' => [10 => 0, 20 => 0, 30 => 0, 40 => 0, 50 => 0],
			'rating'   => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
			'currency' => $this->session->data['currency']
		];

		$prices = [];
		$brands = [];
		$preorder = $this->getPreorderStatusIds();

		foreach ($rows as $row) {
			if ($this->canShowPrice()) {
				$prices[] = $this->getDisplayPrice($row);
			}

			if ($row['manufacturer_id']) {
				$brands[(int)$row['manufacturer_id']] = ($brands[(int)$row['manufacturer_id']] ?? 0) + 1;
			}

			if ($row['quantity'] > 0) {
				$facets['stock']++;
			} elseif (in_array((int)$row['stock_status_id'], $preorder, true)) {
				$facets['preorder']++;
			}

			$percent = $this->getDiscountPercent($row);

			foreach (array_keys($facets['discount']) as $tier) {
				if ($percent >= $tier) {
					$facets['discount'][$tier]++;
				}
			}

			if ($this->config->get('config_review_status')) {
				for ($i = 1; $i <= (int)round((float)$row['rating']); $i++) {
					$facets['rating'][$i]++;
				}
			}
		}

		$facets['discount'] = array_filter($facets['discount']);

		// Colour and size come from product options named Color/Colour (or plural) and Size.
		$ids = array_column($rows, 'product_id');
		$counts = ['color' => [], 'size' => []];

		foreach (['color' => ['color', 'colour', 'colors', 'colours'], 'size' => ['size']] as $key => $names) {
			foreach ($this->getValuesByOption($names, $ids) as $values) {
				foreach ($values as $value_id => $name) {
					$counts[$key][$value_id] = ['id' => $value_id, 'name' => $name, 'count' => ($counts[$key][$value_id]['count'] ?? 0) + 1];
				}
			}

			// A swatch colour is only used when the value name is a plain CSS colour (name or hex); otherwise the grey default shows.
			foreach ($counts[$key] as $value_id => $value) {
				$name = preg_replace('/\s+/', '', $value['name']);

				$counts[$key][$value_id]['css'] = preg_match('/^([a-zA-Z]{3,20}|#[0-9a-fA-F]{3,8})$/', $name) ? $name : '';
			}

			$facets[$key] = array_values($counts[$key]);
		}

		if ($prices) {
			$facets['price'] = ['min' => floor(min($prices)), 'max' => ceil(max($prices))];
		}

		if ($brands) {
			$this->load->model('catalog/manufacturer');

			foreach ($brands as $manufacturer_id => $count) {
				$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($manufacturer_id);

				if ($manufacturer_info) {
					$facets['brands'][] = ['id' => $manufacturer_id, 'name' => $manufacturer_info['name'], 'count' => $count];
				}
			}

			usort($facets['brands'], fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
		}

		return $facets;
	}

	/**
	 * Can Show Price
	 *
	 * Mirrors the product controller: prices are hidden from guests when the store requires login for prices.
	 *
	 * @return bool
	 */
	public function canShowPrice(): bool {
		return $this->customer->isLogged() || !$this->config->get('config_customer_price');
	}

	/**
	 * Get Rows
	 *
	 * The core result for the listing with paging removed, so filters see the whole set.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getRows(array $data): array {
		unset($data['start'], $data['limit']);

		$key = md5(serialize($data));

		return $this->rows[$key] ??= parent::getProducts($data);
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function filterRows(array $data): array {
		unset($data['start'], $data['limit']);

		$active = $this->getActive();

		$key = md5(serialize([$data, $active]));

		return $this->filtered[$key] ??= $this->applyFilters($data, $active);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $active
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function applyFilters(array $data, array $active): array {
		// Name search inside the category uses the core search, so it matches the same way as the site search.
		if ($active['q'] !== '') {
			$data['filter_search'] = $active['q'];
		}

		$rows = $this->getRows($data);

		if (!$this->hasActive($active)) {
			return $rows;
		}

		$review = (bool)$this->config->get('config_review_status');
		$price = $this->canShowPrice();
		$preorder = $this->getPreorderStatusIds();
		$ids = array_column($rows, 'product_id');
		$colors = $active['color'] ? $this->getValuesByOption(['color', 'colour', 'colors', 'colours'], $ids) : [];
		$sizes = $active['size'] ? $this->getValuesByOption(['size'], $ids) : [];

		return array_values(array_filter($rows, function (array $row) use ($active, $review, $price, $preorder, $colors, $sizes): bool {
			if ($active['brand'] && !in_array((int)$row['manufacturer_id'], $active['brand'], true)) {
				return false;
			}

			// In stock and Pre-order combine as "either".
			if ($active['stock'] || $active['preorder']) {
				$in_stock = $row['quantity'] > 0;
				$is_preorder = !$in_stock && in_array((int)$row['stock_status_id'], $preorder, true);

				if (!(($active['stock'] && $in_stock) || ($active['preorder'] && $is_preorder))) {
					return false;
				}
			}

			if ($active['color'] && !array_intersect($active['color'], array_keys($colors[$row['product_id']] ?? []))) {
				return false;
			}

			if ($active['size'] && !array_intersect($active['size'], array_keys($sizes[$row['product_id']] ?? []))) {
				return false;
			}

			if ($active['discount'] && $this->getDiscountPercent($row) < $active['discount']) {
				return false;
			}

			if ($active['rating'] && (!$review || round((float)$row['rating']) < $active['rating'])) {
				return false;
			}

			if ($price && ($active['price_min'] !== null || $active['price_max'] !== null)) {
				$amount = $this->getDisplayPrice($row);

				if ($active['price_min'] !== null && $amount < $active['price_min']) {
					return false;
				}

				if ($active['price_max'] !== null && $amount > $active['price_max']) {
					return false;
				}
			}

			return true;
		}));
	}

	/**
	 * Get Discount Percent
	 *
	 * How far the special price is below the regular price, as shown on the card.
	 *
	 * @param array<string, mixed> $row
	 *
	 * @return float
	 */
	protected function getDiscountPercent(array $row): float {
		if (!(float)$row['special'] || (float)$row['price'] <= 0 || (float)$row['special'] >= (float)$row['price']) {
			return 0.0;
		}

		return (1 - (float)$row['special'] / (float)$row['price']) * 100;
	}

	/**
	 * Get Pre-order Status IDs
	 *
	 * Stock statuses named "Pre-Order": a product with no stock and one of these statuses is a pre-order.
	 *
	 * @return array<int, int>
	 */
	protected function getPreorderStatusIds(): array {
		$query = $this->db->query("SELECT `stock_status_id` FROM `" . DB_PREFIX . "stock_status` WHERE `language_id` = '" . (int)$this->config->get('config_language_id') . "' AND REPLACE(REPLACE(LCASE(`name`), '-', ''), ' ', '') = 'preorder'");

		return array_map('intval', array_column($query->rows, 'stock_status_id'));
	}

	/**
	 * Get Option Values
	 *
	 * Option values by product for options with the given names (case-insensitive).
	 *
	 * @param array<int, string> $names
	 * @param array<int, int>    $product_ids
	 *
	 * @return array<int, array<int, string>> [product_id => [option_value_id => name]]
	 */
	protected function getValuesByOption(array $names, array $product_ids): array {
		if (!$product_ids) {
			return [];
		}

		$names = array_map(fn (string $name): string => "'" . $this->db->escape($name) . "'", $names);

		$query = $this->db->query("SELECT DISTINCT `pov`.`product_id`, `pov`.`option_value_id`, `ovd`.`name` FROM `" . DB_PREFIX . "product_option_value` `pov` LEFT JOIN `" . DB_PREFIX . "option_description` `od` ON (`od`.`option_id` = `pov`.`option_id` AND `od`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "option_value_description` `ovd` ON (`ovd`.`option_value_id` = `pov`.`option_value_id` AND `ovd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "option_value` `ov` ON (`ov`.`option_value_id` = `pov`.`option_value_id`) WHERE `pov`.`product_id` IN (" . implode(',', array_map('intval', $product_ids)) . ") AND LCASE(`od`.`name`) IN (" . implode(',', $names) . ") ORDER BY `ov`.`sort_order`, `ovd`.`name`");

		$values = [];

		foreach ($query->rows as $row) {
			$values[(int)$row['product_id']][(int)$row['option_value_id']] = html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8');
		}

		return $values;
	}

	/**
	 * Get Display Price
	 *
	 * The price a shopper sees on the card: special price when set, with tax as configured, in the session currency.
	 *
	 * @param array<string, mixed> $row
	 *
	 * @return float
	 */
	protected function getDisplayPrice(array $row): float {
		$price = (float)$row['special'] ? (float)$row['special'] : (float)$row['price'];

		$price = $this->tax->calculate($price, $row['tax_class_id'], $this->config->get('config_tax'));

		return round($this->currency->convert($price, $this->config->get('config_currency'), $this->session->data['currency']), 2);
	}
}
