<?php
/**
 * Generates SEO URL keywords for catalogue items that do not have one yet.
 *
 * Usage (run from the shop root):
 *   php extension/beamcf/system/cli/generate_seo_keywords.php [--dry-run] [--products] [--categories] [--manufacturers]
 *
 * With no type flag only products are processed. Existing keywords are never changed, so it is safe to re-run
 * after every product import. Keywords come from the item name (lowercase, hyphenated) and are made unique.
 * Database access goes through OpenCart's own DB library, like the rest of the application.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 4) . '/config.php';
require_once DIR_SYSTEM . 'library/db.php';
require_once DIR_SYSTEM . 'library/db/mysqli.php';
require_once __DIR__ . '/../helper/seo_keyword.php';

$options = array_slice($argv, 1);
$dry = in_array('--dry-run', $options, true);
$types = array_values(array_intersect(['products', 'categories', 'manufacturers'], array_map(fn($option) => ltrim($option, '-'), $options)));

if (!$types) {
    $types = ['products'];
}

$db = new \Opencart\System\Library\DB('mysqli', DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

// Every keyword already taken, per store and language (OpenCart requires keywords to be unique).
$taken = [];

foreach ($db->query("SELECT `store_id`, `language_id`, `keyword` FROM `" . DB_PREFIX . "seo_url`")->rows as $row) {
    $taken[$row['store_id'] . '-' . $row['language_id']][strtolower($row['keyword'])] = true;
}

// What to process: SEO key, fallback word, the table holding the names and the table linking items to stores.
$jobs = [
    'products'      => ['key' => 'product_id', 'prefix' => 'product', 'table' => 'product_description', 'id' => 'product_id', 'stores' => 'product_to_store'],
    'categories'    => ['key' => 'path', 'prefix' => 'category', 'table' => 'category_description', 'id' => 'category_id', 'stores' => 'category_to_store'],
    'manufacturers' => ['key' => 'manufacturer_id', 'prefix' => 'brand', 'table' => 'manufacturer', 'id' => 'manufacturer_id', 'stores' => 'manufacturer_to_store']
];

foreach ($types as $type) {
    $job = $jobs[$type];
    $created = $skipped = 0;

    // The manufacturer table has no language column; it is treated as language 1.
    $language_column = $type === 'manufacturers' ? '1' : '`language_id`';

    $items = $db->query("SELECT `" . $job['id'] . "` AS `id`, " . $language_column . " AS `language_id`, `name` FROM `" . DB_PREFIX . $job['table'] . "` ORDER BY `id`")->rows;

    foreach ($items as $item) {
        $id = (int)$item['id'];
        $language_id = (int)$item['language_id'];

        // Categories are keyed by their full path (e.g. 59_75), built from the category_path table.
        $value = (string)$id;

        if ($type === 'categories') {
            $path = [];

            foreach ($db->query("SELECT `path_id` FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . $id . "' ORDER BY `level`")->rows as $row) {
                $path[] = (int)$row['path_id'];
            }

            $value = $path ? implode('_', $path) : (string)$id;
        }

        $item_stores = [];

        foreach ($db->query("SELECT `store_id` FROM `" . DB_PREFIX . $job['stores'] . "` WHERE `" . $job['id'] . "` = '" . $id . "'")->rows as $row) {
            $item_stores[] = (int)$row['store_id'];
        }

        foreach ($item_stores ?: [0] as $store_id) {
            $existing = $db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE `key` = '" . $db->escape($job['key']) . "' AND `value` = '" . $db->escape($value) . "' AND `store_id` = '" . $store_id . "' AND `language_id` = '" . $language_id . "'");

            if ($existing->num_rows) {
                $skipped++;
                continue;
            }

            $bucket = $store_id . '-' . $language_id;
            $keyword = bcfSeoUnique(bcfSeoSlug($item['name']), $id, $job['prefix'], fn(string $candidate): bool => isset($taken[$bucket][$candidate]));

            $taken[$bucket][$keyword] = true;
            $created++;

            echo ($dry ? '[dry] ' : '') . $type . ' #' . $id . ' -> ' . $keyword . "\n";

            if (!$dry) {
                $db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '" . $store_id . "', `language_id` = '" . $language_id . "', `key` = '" . $db->escape($job['key']) . "', `value` = '" . $db->escape($value) . "', `keyword` = '" . $db->escape($keyword) . "', `sort_order` = '0'");
            }
        }
    }

    echo '== ' . $type . ': ' . ($dry ? 'would create' : 'created') . ' ' . $created . ', already had a keyword ' . $skipped . "\n";
}
