<?php
/**
 * Generates SEO URL keywords for catalogue items that do not have one yet.
 *
 * Usage (run from the shop root):
 *   php extension/beamcf/system/cli/generate_seo_keywords.php [--dry-run] [--products] [--categories] [--manufacturers]
 *
 * With no type flag only products are processed. Existing keywords are never changed, so it is safe to re-run
 * after every product import. Keywords come from the item name (lowercase, hyphenated) and are made unique.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 4);

require_once $root . '/config.php';
require_once __DIR__ . '/../helper/seo_keyword.php';

$options = array_slice($argv, 1);
$dry = in_array('--dry-run', $options, true);
$types = array_values(array_intersect(['products', 'categories', 'manufacturers'], array_map(fn($o) => ltrim($o, '-'), $options)));

if (!$types) {
    $types = ['products'];
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
$db->set_charset('utf8mb4');

$p = DB_PREFIX;

// Every keyword already taken, per store and language (OpenCart requires keywords to be unique).
$taken = [];

foreach ($db->query("SELECT store_id, language_id, keyword FROM `{$p}seo_url`") as $row) {
    $taken[$row['store_id'] . '-' . $row['language_id']][strtolower($row['keyword'])] = true;
}

$stores = [];

foreach ($db->query("SELECT store_id FROM `{$p}store`") as $row) {
    $stores[] = (int)$row['store_id'];
}

$stores[] = 0;

// What to process: seo key, name query returning id/name/language_id, store link table (null = default store only).
$jobs = [
    'products'      => ['key' => 'product_id', 'prefix' => 'product', 'sql' => "SELECT product_id AS id, language_id, name FROM `{$p}product_description`", 'stores' => "SELECT store_id FROM `{$p}product_to_store` WHERE product_id = %d"],
    'categories'    => ['key' => 'path', 'prefix' => 'category', 'sql' => "SELECT category_id AS id, language_id, name FROM `{$p}category_description`", 'stores' => "SELECT store_id FROM `{$p}category_to_store` WHERE category_id = %d"],
    'manufacturers' => ['key' => 'manufacturer_id', 'prefix' => 'brand', 'sql' => "SELECT manufacturer_id AS id, 1 AS language_id, name FROM `{$p}manufacturer`", 'stores' => "SELECT store_id FROM `{$p}manufacturer_to_store` WHERE manufacturer_id = %d"]
];

$insert = $db->prepare("INSERT INTO `{$p}seo_url` (store_id, language_id, `key`, `value`, keyword, sort_order) VALUES (?, ?, ?, ?, ?, 0)");

foreach ($types as $type) {
    $job = $jobs[$type];
    $created = $skipped = 0;

    foreach ($db->query($job['sql'] . ' ORDER BY id') as $item) {
        $id = (int)$item['id'];
        $language_id = (int)$item['language_id'];

        // Categories are keyed by their full path (e.g. 59_75), so build it from the category_path table.
        $value = (string)$id;

        if ($type === 'categories') {
            $parts = [];

            foreach ($db->query("SELECT path_id FROM `{$p}category_path` WHERE category_id = {$id} ORDER BY level") as $path) {
                $parts[] = $path['path_id'];
            }

            $value = $parts ? implode('_', $parts) : (string)$id;
        }

        $item_stores = [];

        foreach ($db->query(sprintf($job['stores'], $id)) as $row) {
            $item_stores[] = (int)$row['store_id'];
        }

        foreach ($item_stores ?: [0] as $store_id) {
            $existing = $db->prepare("SELECT 1 FROM `{$p}seo_url` WHERE `key` = ? AND `value` = ? AND store_id = ? AND language_id = ?");
            $existing->bind_param('ssii', $job['key'], $value, $store_id, $language_id);
            $existing->execute();

            if ($existing->get_result()->num_rows) {
                $skipped++;
                continue;
            }

            $bucket = $store_id . '-' . $language_id;
            $keyword = bcf_seo_unique(bcf_seo_slug($item['name']), $id, $job['prefix'], fn(string $k): bool => isset($taken[$bucket][$k]));

            $taken[$bucket][$keyword] = true;
            $created++;

            echo ($dry ? '[dry] ' : '') . "{$type} #{$id} -> {$keyword}\n";

            if (!$dry) {
                $insert->bind_param('iisss', $store_id, $language_id, $job['key'], $value, $keyword);
                $insert->execute();
            }
        }
    }

    echo "== {$type}: " . ($dry ? 'would create' : 'created') . " {$created}, already had a keyword {$skipped}\n";
}
