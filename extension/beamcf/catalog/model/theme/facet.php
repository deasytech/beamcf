<?php
namespace Opencart\Catalog\Model\Extension\Beamcf\Theme;
/**
 * Class Facet
 *
 * Describes what the category filter panel can offer: price range, brands, availability, colours, sizes, discount
 * tiers and ratings, counted from the real products of the category. The products and the price, stock and discount
 * rules come from the Catalog model, so the panel and the filtered listing always agree.
 *
 * Can be called from $this->load->model('extension/beamcf/theme/facet');
 *
 * @package Opencart\Catalog\Model\Extension\Beamcf\Theme
 */
class Facet extends \Opencart\System\Engine\Model {
    /**
     * Get Facets
     *
     * Counts describe the product set of the category narrowed by the in-category search, so they match what the
     * shopper is looking at; `total` is the unsearched category total, which tells an empty category from a search
     * or filter that matches nothing.
     *
     * @param array<string, mixed> $data core filter data of the listing (category, filter groups)
     *
     * @return array<string, mixed>
     */
    public function getFacets(array $data): array {
        $this->load->model('extension/beamcf/theme/catalog');

        $total = count($this->model_extension_beamcf_theme_catalog->getRows($data));

        $active = $this->model_extension_beamcf_theme_catalog->getActive();

        if ($active['q'] !== '') {
            $data['filter_search'] = $active['q'];
        }

        $rows = $this->model_extension_beamcf_theme_catalog->getRows($data);

        $facets = $this->countRows($rows) + [
            'total'    => $total,
            'currency' => $this->session->data['currency']
        ];

        return $facets + $this->getOptionFacets(array_column($rows, 'product_id'));
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

        $catalog = $this->model_extension_beamcf_theme_catalog;

        $preorder = $catalog->getPreorderStatusIds();
        $review = (bool)$this->config->get('config_review_status');
        $show_price = $catalog->canShowPrice();
        $prices = [];
        $brands = [];

        foreach ($rows as $row) {
            if ($show_price) {
                $prices[] = $catalog->getDisplayPrice($row);
            }

            $brands[(int)$row['manufacturer_id']] = ($brands[(int)$row['manufacturer_id']] ?? 0) + 1;

            $this->countAvailability($facets, $row, $preorder);
            $this->countDiscount($facets['discount'], $row);

            if ($review) {
                $this->countRating($facets['rating'], $row);
            }
        }

        $facets['discount'] = array_filter($facets['discount']);

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
        $percent = $this->model_extension_beamcf_theme_catalog->getDiscountPercent($row);

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
            'color' => $this->getOptionValueFacets('color', $ids),
            'size'  => $this->getOptionValueFacets('size', $ids)
        ];
    }

    /**
     * @param string          $group color or size
     * @param array<int, int> $ids   product IDs
     *
     * @return array<int, array<string, mixed>>
     */
    private function getOptionValueFacets(string $group, array $ids): array {
        $counts = [];

        foreach ($this->model_extension_beamcf_theme_catalog->getValuesByOption($group, $ids) as $values) {
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
}
