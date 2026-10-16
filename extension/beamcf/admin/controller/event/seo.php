<?php
namespace Opencart\Admin\Controller\Extension\Beamcf\Event;
/**
 * Class Seo
 *
 * Gives a new product an SEO keyword built from its name when it was saved without one (imports, scripts and
 * any code that calls the product model directly). Products saved with a keyword in the admin form are untouched.
 *
 * @package Opencart\Admin\Controller\Extension\Beamcf\Event
 */
class Seo extends \Opencart\System\Engine\Controller {
    /**
     * Runs on admin/model/catalog/product.addProduct/after: [&$route, &$args, &$output], $output is the new product_id.
     *
     * @param mixed ...$args
     *
     * @return void
     */
    public function product(mixed &...$args): void {
        $product_id = (int)($args[2] ?? 0);

        if (!$product_id) {
            return;
        }

        require_once DIR_EXTENSION . 'beamcf/system/helper/seo_keyword.php';

        $query = $this->db->query("SELECT `language_id`, `name` FROM `" . DB_PREFIX . "product_description` WHERE `product_id` = '" . $product_id . "'");

        foreach ($query->rows as $row) {
            $language_id = (int)$row['language_id'];

            $has = $this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'product_id' AND `value` = '" . $product_id . "' AND `language_id` = '" . $language_id . "'");

            if ($has->num_rows) {
                continue;
            }

            $keyword = bcf_seo_unique(bcf_seo_slug((string)$row['name']), $product_id, 'product', function (string $keyword) use ($language_id): bool {
                return (bool)$this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `store_id` = '0' AND `language_id` = '" . $language_id . "'")->num_rows;
            });

            $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '0', `language_id` = '" . $language_id . "', `key` = 'product_id', `value` = '" . $product_id . "', `keyword` = '" . $this->db->escape($keyword) . "', `sort_order` = '0'");
        }
    }
}
