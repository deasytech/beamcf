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
    private const COLOR_OPTIONS = ['color', 'colour', 'colors', 'colours'];
    private const SIZE_OPTIONS = ['size'];

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

        return [
            'price_min' => $this->price($get['price_min'] ?? null),
            'price_max' => $this->price($get['price_max'] ?? null),
            'brand'     => $this->ids($get['brand'] ?? []),
            'stock'     => !empty($get['stock']),
            'rating'    => min(5, max(0, (int)($get['rating'] ?? 0))),
            'preorder'  => !empty($get['preorder']),
            'color'     => $this->ids($get['color'] ?? []),
            'size'      => $this->ids($get['size'] ?? []),
            'discount'  => min(95, max(0, (int)($get['discount'] ?? 0))),
            'q'         => trim(mb_substr((string)($get['q'] ?? ''), 0, 100))
        ];
    }

    /**
     * Has Active
     *
     * @param array<string, mixed> $active
     *
     * @return bool
     */
    public function hasActive(array $active): bool {
        return $active['price_min'] !== null || $active['price_max'] !== null || $active['q'] !== '' || array_filter(array_diff_key($active, array_flip(['price_min', 'price_max', 'q']))) !== [];
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
     * Describes what the filter panel can offer. Counts describe the product set of the category narrowed by the
     * in-category search, so they match what the shopper is looking at; `total` is the unsearched category total,
     * which tells an empty category from a search or filter that matches nothing.
     *
     * @param array<string, mixed> $data core filter data of the listing (category, filter groups)
     *
     * @return array<string, mixed>
     */
    public function getFacets(array $data): array {
        $total = count($this->getRows($data));

        $active = $this->getActive();

        if ($active['q'] !== '') {
            $data['filter_search'] = $active['q'];
        }

        $rows = $this->getRows($data);

        $facets = $this->countRows($rows) + [
            'total'    => $total,
            'currency' => $this->session->data['currency']
        ];

        return $facets + $this->getOptionFacets(array_column($rows, 'product_id'));
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
     * @param mixed $value
     *
     * @return float|null a positive amount, or null when the value is not one
     */
    protected function price($value): ?float {
        return is_numeric($value) && (float)$value > 0 ? (float)$value : null;
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

        $key = hash('sha256', serialize($data));

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

        $key = hash('sha256', serialize([$data, $active]));

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

        $ids = array_column($rows, 'product_id');

        $context = [
            'preorder' => $this->getPreorderStatusIds(),
            'colors'   => $active['color'] ? $this->getValuesByOption(self::COLOR_OPTIONS, $ids) : [],
            'sizes'    => $active['size'] ? $this->getValuesByOption(self::SIZE_OPTIONS, $ids) : []
        ];

        return array_values(array_filter($rows, fn (array $row): bool => $this->matches($row, $active, $context)));
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $active
     * @param array<string, mixed> $context
     *
     * @return bool
     */
    private function matches(array $row, array $active, array $context): bool {
        return $this->matchesBrand($row, $active)
            && $this->matchesAvailability($row, $active, $context['preorder'])
            && $this->matchesOption($row, $active['color'], $context['colors'])
            && $this->matchesOption($row, $active['size'], $context['sizes'])
            && $this->matchesDiscountAndRating($row, $active)
            && $this->matchesPrice($row, $active);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $active
     *
     * @return bool
     */
    private function matchesBrand(array $row, array $active): bool {
        return !$active['brand'] || in_array((int)$row['manufacturer_id'], $active['brand'], true);
    }

    /**
     * In stock and Pre-order combine as "either".
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $active
     * @param array<int, int>      $preorder
     *
     * @return bool
     */
    private function matchesAvailability(array $row, array $active, array $preorder): bool {
        if (!$active['stock'] && !$active['preorder']) {
            return true;
        }

        $in_stock = $row['quantity'] > 0;
        $is_preorder = !$in_stock && in_array((int)$row['stock_status_id'], $preorder, true);

        return ($active['stock'] && $in_stock) || ($active['preorder'] && $is_preorder);
    }

    /**
     * @param array<string, mixed>             $row
     * @param array<int, int>                  $selected
     * @param array<int, array<int, string>>   $values option values by product
     *
     * @return bool
     */
    private function matchesOption(array $row, array $selected, array $values): bool {
        return !$selected || array_intersect($selected, array_keys($values[$row['product_id']] ?? [])) !== [];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $active
     *
     * @return bool
     */
    private function matchesDiscountAndRating(array $row, array $active): bool {
        if ($active['discount'] && $this->getDiscountPercent($row) < $active['discount']) {
            return false;
        }

        return !$active['rating'] || ($this->config->get('config_review_status') && round((float)$row['rating']) >= $active['rating']);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $active
     *
     * @return bool
     */
    private function matchesPrice(array $row, array $active): bool {
        if (!$this->canShowPrice() || ($active['price_min'] === null && $active['price_max'] === null)) {
            return true;
        }

        $amount = $this->getDisplayPrice($row);

        return ($active['price_min'] === null || $amount >= $active['price_min']) && ($active['price_max'] === null || $amount <= $active['price_max']);
    }

    /**
     * Counts the rows into the facets that need no extra query.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function countRows(array $rows): array {
        $facets = [
            'price'    => null,
            'brands'   => [],
            'stock'    => 0,
            'preorder' => 0,
            'discount' => [10 => 0, 20 => 0, 30 => 0, 40 => 0, 50 => 0],
            'rating'   => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
        ];

        $preorder = $this->getPreorderStatusIds();
        $review = (bool)$this->config->get('config_review_status');
        $prices = [];
        $brands = [];

        foreach ($rows as $row) {
            $prices[] = $this->canShowPrice() ? $this->getDisplayPrice($row) : null;
            $brands[(int)$row['manufacturer_id']] = ($brands[(int)$row['manufacturer_id']] ?? 0) + 1;

            $this->countAvailability($facets, $row, $preorder);
            $this->countDiscount($facets['discount'], $row);

            if ($review) {
                $this->countRating($facets['rating'], $row);
            }
        }

        $facets['discount'] = array_filter($facets['discount']);

        $prices = array_filter($prices, fn ($price): bool => $price !== null);

        if ($prices) {
            $facets['price'] = ['min' => floor(min($prices)), 'max' => ceil(max($prices))];
        }

        unset($brands[0]);

        $facets['brands'] = $this->getBrandFacets($brands);

        return $facets;
    }

    /**
     * @param array<string, mixed> $facets
     * @param array<string, mixed> $row
     * @param array<int, int>      $preorder
     *
     * @return void
     */
    private function countAvailability(array &$facets, array $row, array $preorder): void {
        if ($row['quantity'] > 0) {
            $facets['stock']++;
        } elseif (in_array((int)$row['stock_status_id'], $preorder, true)) {
            $facets['preorder']++;
        }
    }

    /**
     * @param array<int, int>      $tiers percent tier => products at or above it
     * @param array<string, mixed> $row
     *
     * @return void
     */
    private function countDiscount(array &$tiers, array $row): void {
        $percent = $this->getDiscountPercent($row);

        foreach (array_keys($tiers) as $tier) {
            if ($percent >= $tier) {
                $tiers[$tier]++;
            }
        }
    }

    /**
     * @param array<int, int>      $stars stars => products rated at least that many stars
     * @param array<string, mixed> $row
     *
     * @return void
     */
    private function countRating(array &$stars, array $row): void {
        for ($i = 1; $i <= (int)round((float)$row['rating']); $i++) {
            $stars[$i]++;
        }
    }

    /**
     * @param array<int, int> $counts manufacturer ID => product count
     *
     * @return array<int, array<string, mixed>>
     */
    private function getBrandFacets(array $counts): array {
        if (!$counts) {
            return [];
        }

        $this->load->model('catalog/manufacturer');

        $brands = [];

        foreach ($counts as $manufacturer_id => $count) {
            $manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($manufacturer_id);

            if ($manufacturer_info) {
                $brands[] = ['id' => $manufacturer_id, 'name' => $manufacturer_info['name'], 'count' => $count];
            }
        }

        usort($brands, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $brands;
    }

    /**
     * Colour and size facets, from product options named Color/Colour (or plural) and Size.
     *
     * @param array<int, int> $ids product IDs
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getOptionFacets(array $ids): array {
        return [
            'color' => $this->getOptionValueFacets(self::COLOR_OPTIONS, $ids),
            'size'  => $this->getOptionValueFacets(self::SIZE_OPTIONS, $ids)
        ];
    }

    /**
     * @param array<int, string> $names option names
     * @param array<int, int>    $ids   product IDs
     *
     * @return array<int, array<string, mixed>>
     */
    private function getOptionValueFacets(array $names, array $ids): array {
        $counts = [];

        foreach ($this->getValuesByOption($names, $ids) as $values) {
            foreach ($values as $value_id => $name) {
                $counts[$value_id] = [
                    'id'    => $value_id,
                    'name'  => $name,
                    'count' => ($counts[$value_id]['count'] ?? 0) + 1,
                    'css'   => $this->getSwatchColor($name)
                ];
            }
        }

        return array_values($counts);
    }

    /**
     * A swatch colour is only used when the value name is a plain CSS colour (name or hex); otherwise the text is shown.
     *
     * @param string $name
     *
     * @return string
     */
    private function getSwatchColor(string $name): string {
        $name = preg_replace('/\s+/', '', $name);

        return preg_match('/^([a-zA-Z]{3,20}|#[0-9a-fA-F]{3,8})$/', $name) ? $name : '';
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
