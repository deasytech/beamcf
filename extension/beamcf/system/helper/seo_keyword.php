<?php
/**
 * Builds URL-safe, unique SEO keywords. Shared by the backfill script and the admin event.
 */

/**
 * @param string $text
 * @param int    $max Keyword length limit (OpenCart allows 1 to 64 characters).
 *
 * @return string
 */
function bcfSeoSlug(string $text, int $max = 64): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $text = str_replace('&', ' and ', $text);

    if (function_exists('transliterator_transliterate')) {
        $text = (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    } elseif (function_exists('iconv')) {
        $text = (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');

    if (strlen($text) > $max) {
        $text = substr($text, 0, $max);

        // Cut at the last whole word where there is one.
        $pos = strrpos($text, '-');

        if ($pos !== false && $pos > $max / 2) {
            $text = substr($text, 0, $pos);
        }

        $text = trim($text, '-');
    }

    return $text;
}

/**
 * First free keyword for $base: "base", then "base-2", "base-3", then "base-id" (and "base-id-2"...) as a last resort.
 *
 * @param string   $base
 * @param int      $id     Used as the final fallback suffix and when $base is empty.
 * @param string   $prefix Fallback word when there is nothing usable in $base, e.g. "product".
 * @param callable $exists fn(string $keyword): bool
 * @param int      $max
 *
 * @return string
 */
function bcfSeoUnique(string $base, int $id, string $prefix, callable $exists, int $max = 64): string {
    if ($base === '') {
        $base = $prefix . '-' . $id;
    }

    $candidate = $base;

    for ($i = 2; $exists($candidate) && $i <= 50; $i++) {
        $suffix = '-' . $i;
        $candidate = substr($base, 0, $max - strlen($suffix)) . $suffix;
    }

    // Last resort: the id, then id plus a counter, until the keyword is free.
    for ($i = 1; $i <= 1000 && $exists($candidate); $i++) {
        $suffix = '-' . $id . ($i > 1 ? '-' . $i : '');
        $candidate = substr($base, 0, $max - strlen($suffix)) . $suffix;
    }

    return $candidate;
}
