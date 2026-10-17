<?php
namespace Opencart\Admin\Controller\Extension\Beamcf\Event;
/**
 * Class ProductImages
 *
 * Removes a product's image files when the product is deleted in the admin. Images still used by anything else
 * (another product, a category, a brand, a banner or an option value) are kept, and so are folders that still
 * contain files. Only files under image/catalog/ are ever touched.
 *
 * @package Opencart\Admin\Controller\Extension\Beamcf\Event
 */
class ProductImages extends \Opencart\System\Engine\Controller {
    /**
     * Image paths collected before the product rows are deleted, keyed by product_id.
     *
     * @var array<int, array<int, string>>
     */
    private static array $pending = [];

    /**
     * Runs on admin/model/catalog/product.deleteProduct/before: [&$route, &$args], $args[0] is the product_id.
     *
     * @param mixed ...$args
     *
     * @return void
     */
    public function collect(mixed &...$args): void {
        $product_id = (int)($args[1][0] ?? 0);

        if (!$product_id) {
            return;
        }

        $images = [];

        $query = $this->db->query("SELECT `image` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . $product_id . "'");

        foreach ($query->rows as $row) {
            $images[] = (string)$row['image'];
        }

        $query = $this->db->query("SELECT `image` FROM `" . DB_PREFIX . "product_image` WHERE `product_id` = '" . $product_id . "'");

        foreach ($query->rows as $row) {
            $images[] = (string)$row['image'];
        }

        self::$pending[$product_id] = array_values(array_unique(array_filter($images)));
    }

    /**
     * Runs on admin/model/catalog/product.deleteProduct/after: [&$route, &$args, &$output].
     *
     * @param mixed ...$args
     *
     * @return void
     */
    public function remove(mixed &...$args): void {
        $product_id = (int)($args[1][0] ?? 0);
        $images = self::$pending[$product_id] ?? [];

        unset(self::$pending[$product_id]);

        $root = realpath(DIR_IMAGE . 'catalog');

        if (!$root) {
            return;
        }

        foreach ($images as $image) {
            if ($this->inUse($image)) {
                continue;
            }

            $file = realpath(DIR_IMAGE . $image);

            // Never leave image/catalog/, and never follow a path that does not resolve to a file.
            if (!$file || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }

            @unlink($file);

            $this->removeCached($image);
            $this->removeEmptyFolders(dirname($file), $root);
        }
    }

    /**
     * Whether any other record still points at this image.
     *
     * @param string $image
     *
     * @return bool
     */
    private function inUse(string $image): bool {
        $escaped = $this->db->escape($image);

        // The store logo and icon are settings, not table columns.
        if ($this->db->query("SELECT 1 FROM `" . DB_PREFIX . "setting` WHERE `key` IN ('config_logo', 'config_icon') AND `value` = '" . $escaped . "' LIMIT 1")->num_rows) {
            return true;
        }

        foreach (['product', 'product_image', 'category', 'manufacturer', 'banner_image', 'option_value', 'information', 'product_option_value'] as $table) {
            // Not every table has an image column in every release, so skip the ones that do not.
            $column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE 'image'");

            if (!$column->num_rows) {
                continue;
            }

            if ($this->db->query("SELECT 1 FROM `" . DB_PREFIX . $table . "` WHERE `image` = '" . $escaped . "' LIMIT 1")->num_rows) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deletes the resized copies OpenCart made in image/cache/ (name-WxH.ext).
     *
     * @param string $image
     *
     * @return void
     */
    private function removeCached(string $image): void {
        $info = pathinfo($image);

        if (empty($info['extension'])) {
            return;
        }

        $folder = DIR_IMAGE . 'cache/' . ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '');

        if (!is_dir($folder)) {
            return;
        }

        // Only name-WIDTHxHEIGHT.ext: a sibling such as name-2.ext or name-alt-100x100.ext belongs to another image.
        $match = '/^' . preg_quote($info['filename'], '/') . '-\d+x\d+\.' . preg_quote($info['extension'], '/') . '$/i';

        foreach (scandir($folder) ?: [] as $name) {
            if (preg_match($match, $name) && is_file($folder . $name)) {
                @unlink($folder . $name);
            }
        }
    }

    /**
     * Removes the folder and then its parents while they are empty, stopping at image/catalog/.
     *
     * @param string $folder
     * @param string $root
     *
     * @return void
     */
    private function removeEmptyFolders(string $folder, string $root): void {
        while (str_starts_with($folder, $root . DIRECTORY_SEPARATOR)) {
            if (count(scandir($folder)) > 2 || !@rmdir($folder)) {
                return;
            }

            $folder = dirname($folder);
        }
    }
}
