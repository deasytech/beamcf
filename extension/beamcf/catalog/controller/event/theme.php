<?php
namespace Opencart\Catalog\Controller\Extension\Beamcf\Event;
/**
 * Class Theme
 *
 * Event listener on catalog/view/* /before. When the BeamCF theme is the selected store theme it:
 * 1. points the view at a BeamCF template if one exists (otherwise the default template is used), and
 * 2. adds extra template variables for the shared header, menu, cart and footer.
 *
 * @package Opencart\Catalog\Controller\Extension\Beamcf\Event
 */
class Theme extends \Opencart\System\Engine\Controller {
	private const ROUTE_HOME = 'common/home';
	private const ROUTE_CATEGORY = 'product/category';
	private const ROUTE_PRODUCT = 'product/product';

	/**
	 * View
	 *
	 * @param string            $route
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	public function view(string &$route, array &$args): void {
		if ($this->config->get('config_theme') != 'beamcf' || !$this->config->get('theme_beamcf_status')) {
			return;
		}

		// Already resolved to an extension template (BeamCF or ocmod), nothing to do.
		if (str_starts_with($route, 'extension/beamcf/') || str_starts_with($route, 'extension/ocmod/')) {
			return;
		}

		$file = DIR_EXTENSION . 'beamcf/catalog/view/template/theme/' . $route . '.twig';

		if (!is_file($file)) {
			return;
		}

		$this->load->language('extension/beamcf/theme/beamcf');

		switch ($route) {
			case 'common/header':
				$this->header($args);
				break;
			case 'common/menu':
				$this->menu($args);
				break;
			case 'common/cart':
				$args['bcf_cart_count'] = $this->cart->countProducts();
				break;
			case 'common/footer':
				$this->footer($args);
				break;
			case self::ROUTE_HOME:
				$this->home($args);
				break;
			case 'product/thumb':
				$this->thumb($args);
				break;
			case self::ROUTE_CATEGORY:
				$this->category($args);
				break;
			case self::ROUTE_PRODUCT:
				$this->product($args);
				break;
			case 'checkout/checkout':
				$args['bcf_checkout_ready'] = $this->checkoutReady();
				break;
			case 'information/contact':
				$args['bcf_email'] = trim((string)$this->config->get('config_email'));
				break;
		}

		$this->text($args);

		$route = 'extension/beamcf/theme/' . $route;
	}

	/**
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function header(array &$args): void {
		$language = 'language=' . $this->config->get('config_language');

		$args['bcf_announcement'] = trim((string)$this->config->get('theme_beamcf_announcement'));
		$args['bcf_app_text'] = trim((string)$this->config->get('theme_beamcf_app_text'));
		$args['bcf_app_url'] = trim((string)$this->config->get('theme_beamcf_app_url'));
		$args['bcf_compare_url'] = $this->url->link('product/compare', $language);
		$args['bcf_compare_count'] = isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0;

		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$args['bcf_wishlist_count'] = $this->model_account_wishlist->getTotalWishlist($this->customer->getId());
		} else {
			$args['bcf_wishlist_count'] = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0;
		}

		$this->load->model('catalog/category');

		$args['bcf_has_categories'] = (bool)$this->model_catalog_category->getCategories(0);

		// Theme logo, so the store's configured logo (used by the default theme) is left alone.
		$logo = $this->logo();

		if ($logo) {
			$args['logo'] = $logo;
		}

		$args['bcf_stylesheet'] = 'extension/beamcf/catalog/view/stylesheet/beamcf.css?v=' . (int)@filemtime(DIR_EXTENSION . 'beamcf/catalog/view/stylesheet/beamcf.css');
	}

	/**
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function menu(array &$args): void {
		$language = 'language=' . $this->config->get('config_language');

		$args['bcf_home_url'] = $this->url->link(self::ROUTE_HOME, $language);
		$args['bcf_special_url'] = $this->url->link('product/special', $language);
		$args['bcf_contact_url'] = $this->url->link('information/contact', $language);

		$this->load->model('cms/article');

		$args['bcf_blog_url'] = $this->model_cms_article->getTotalArticles() ? $this->url->link('cms/blog', $language) : '';

		$args['bcf_route'] = $this->request->get['route'] ?? self::ROUTE_HOME;

		// Top level category of the current category page, to mark the matching menu item.
		$path = explode('_', (string)($this->request->get['path'] ?? ''));
		$args['bcf_path_root'] = (int)$path[0];
	}

	/**
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function footer(array &$args): void {
		$language = 'language=' . $this->config->get('config_language');

		$args['bcf_socials'] = [];

		$socials = [
			'facebook'  => 'fa-brands fa-facebook-f',
			'x'         => 'fa-brands fa-x-twitter',
			'instagram' => 'fa-brands fa-instagram',
			'youtube'   => 'fa-brands fa-youtube'
		];

		foreach ($socials as $key => $icon) {
			$url = trim((string)$this->config->get('theme_beamcf_' . $key));

			if ($url) {
				$args['bcf_socials'][] = ['name' => ucfirst($key == 'x' ? 'X' : $key), 'url' => $url, 'icon' => $icon];
			}
		}

		// Store details come from General Settings. OpenCart stores admin input already HTML-encoded, like the default templates these are printed as-is.
		$args['bcf_store_name'] = $this->config->get('config_name');
		$args['bcf_address'] = nl2br(trim((string)$this->config->get('config_address')));
		$args['bcf_telephone'] = trim((string)$this->config->get('config_telephone'));
		$args['bcf_email'] = trim((string)$this->config->get('config_email'));

		// Brands
		$this->load->model('catalog/manufacturer');

		$args['bcf_manufacturers'] = [];

		foreach ($this->model_catalog_manufacturer->getManufacturers() as $manufacturer) {
			$args['bcf_manufacturers'][] = [
				'name' => $manufacturer['name'],
				'href' => $this->url->link('product/manufacturer.info', $language . '&manufacturer_id=' . $manufacturer['manufacturer_id'])
			];
		}

		$args['bcf_compare_url'] = $this->url->link('product/compare', $language);
		$args['bcf_script'] = 'extension/beamcf/catalog/view/javascript/beamcf.js?v=' . (int)@filemtime(DIR_EXTENSION . 'beamcf/catalog/view/javascript/beamcf.js');
		$args['bcf_cart_info'] = $this->url->link('common/cart.info', 'language=' . $this->config->get('config_language'));
		$args['bcf_logo'] = $this->logo() ?: (is_file(DIR_IMAGE . $this->config->get('config_logo')) ? $this->config->get('config_url') . 'image/' . $this->config->get('config_logo') : '');
		$args['bcf_name'] = $this->config->get('config_name');
		$args['bcf_home_url'] = $this->url->link(self::ROUTE_HOME, $language);
	}

	/**
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function home(array &$args): void {
		$this->load->model('extension/beamcf/theme/home');

		$args['bcf'] = $this->model_extension_beamcf_theme_home->getSections();
		$args['bcf_deal_text'] = $args['bcf']['deal'] ? $this->language->get('bcf_deal_ends') : '';
		$args['bcf_special_url'] = $this->url->link('product/special', 'language=' . $this->config->get('config_language'));
		$args['bcf_blog_url'] = $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'));
		$args['bcf_script_home'] = 'extension/beamcf/catalog/view/javascript/home.js';

	}

	/**
	 * Filter panel, active filter chips and listing count for the category page.
	 *
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function category(array &$args): void {
		$get = $this->request->get;

		$parts = explode('_', (string)($get['path'] ?? ''));

		$this->load->model('extension/beamcf/theme/catalog');
		$this->load->model('extension/beamcf/theme/facet');
		$this->load->model('catalog/product');

		$core = ['filter_category_id' => (int)array_pop($parts), 'filter_sub_category' => false, 'filter_filter' => $get['filter'] ?? ''];

		$active = $this->model_extension_beamcf_theme_catalog->getActive();
		$facets = $this->model_extension_beamcf_theme_facet->getFacets($core);

		// Parameters that always travel with the listing, and the filter parameters.
		$keep = array_intersect_key($get, array_flip(['path', 'sort', 'order', 'limit', 'filter']));
		$filters = array_intersect_key($get, array_flip(['price_min', 'price_max', 'brand', 'stock', 'preorder', 'color', 'size', 'discount', 'rating', 'q']));

		$chips = $this->chips($active, $facets, $keep, $filters);

		$this->addFiltersToLinks($args, $filters);

		$args['bcf_filter'] = [
			'action'          => $this->config->get('config_url') . 'index.php',
			'hidden'          => ['route' => self::ROUTE_CATEGORY, 'language' => $this->config->get('config_language')] + $keep,
			'active'          => $active,
			'selected_brands' => $active['brand'],
			'facets'          => $facets,
			'chips'           => $chips,
			'reset'           => $this->categoryLink($keep),
			'show_price'      => $facets['price'] !== null && $facets['price']['max'] > $facets['price']['min'],
			'show_rating'     => (bool)$this->config->get('config_review_status') && array_sum($facets['rating']) > 0,
			'currency_symbol' => $this->currencySymbol(),
			'any'             => (bool)($facets['brands'] || $facets['discount'] || $facets['stock'] || $facets['preorder'] || $facets['color'] || $facets['size'] || $facets['price'] !== null)
		];

		$args['bcf_count'] = $this->model_catalog_product->getTotalProducts($core);
		$args['bcf_filtered'] = (bool)$chips;
		$args['bcf_has_products'] = $facets['total'] > 0;
	}

	/**
	 * Removable chips for the active filters, each linking to the listing without that filter.
	 *
	 * @param array<string, mixed> $active
	 * @param array<string, mixed> $facets
	 * @param array<string, mixed> $keep
	 * @param array<string, mixed> $filters
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function chips(array $active, array $facets, array $keep, array $filters): array {
		$without = fn (array $names): string => $this->categoryLink($keep + array_diff_key($filters, array_flip($names)));

		$chips = [];

		$price = $this->priceLabel($active);

		if ($price !== '') {
			$chips[] = ['text' => $price, 'href' => $without(['price_min', 'price_max'])];
		}

		if ($active['q'] !== '') {
			$chips[] = ['text' => '“' . $active['q'] . '”', 'href' => $without(['q'])];
		}

		foreach (['stock' => 'bcf_in_stock', 'preorder' => 'bcf_pre_order', 'discount' => 'bcf_discount_up', 'rating' => 'bcf_rating_up'] as $key => $text) {
			if ($active[$key]) {
				$chips[] = ['text' => sprintf($this->language->get($text), $active[$key]), 'href' => $without([$key])];
			}
		}

		return array_merge($chips, $this->valueChips($active, $facets, $keep, $filters));
	}

	/**
	 * Chips for the selected brands, colours and sizes. Removing one keeps the others selected.
	 *
	 * @param array<string, mixed> $active
	 * @param array<string, mixed> $facets
	 * @param array<string, mixed> $keep
	 * @param array<string, mixed> $filters
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function valueChips(array $active, array $facets, array $keep, array $filters): array {
		$chips = [];

		foreach (['brand' => 'brands', 'color' => 'color', 'size' => 'size'] as $group => $facet) {
			foreach ($facets[$facet] as $value) {
				if (!in_array($value['id'], $active[$group], true)) {
					continue;
				}

				$rest = array_diff($active[$group], [$value['id']]);
				$params = $keep + array_diff_key($filters, [$group => 1]) + ($rest ? [$group => implode(',', $rest)] : []);

				$chips[] = ['text' => $value['name'], 'href' => $this->categoryLink($params)];
			}
		}

		return $chips;
	}

	/**
	 * @param array<string, mixed> $active
	 *
	 * @return string e.g. "$10 – $50", "≥ $10", "≤ $50", or empty when no price filter is active
	 */
	protected function priceLabel(array $active): string {
		$currency = $this->session->data['currency'];

		$min = $active['price_min'] !== null ? $this->currency->format($active['price_min'], $currency, 1.0) : '';
		$max = $active['price_max'] !== null ? $this->currency->format($active['price_max'], $currency, 1.0) : '';

		if ($min !== '' && $max !== '') {
			return $min . ' – ' . $max;
		}

		if ($min !== '') {
			return '≥ ' . $min;
		}

		return $max !== '' ? '≤ ' . $max : '';
	}

	/**
	 * The core sort and limit links only carry the parameters it knows about, so add the filters to them.
	 *
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $filters
	 *
	 * @return void
	 */
	protected function addFiltersToLinks(array &$args, array $filters): void {
		if (!$filters) {
			return;
		}

		$extra = '&amp;' . str_replace('&', '&amp;', http_build_query($filters));

		foreach (['sorts', 'limits'] as $key) {
			foreach ($args[$key] ?? [] as $i => $item) {
				$args[$key][$i]['href'] = $item['href'] . $extra;
			}
		}
	}

	/**
	 * @param array<string, mixed> $params
	 *
	 * @return string
	 */
	protected function categoryLink(array $params): string {
		return $this->url->link(self::ROUTE_CATEGORY, 'language=' . $this->config->get('config_language') . ($params ? '&' . http_build_query($params) : ''));
	}

	/**
	 * @return string
	 */
	protected function currencySymbol(): string {
		$currency = $this->session->data['currency'];

		return trim($this->currency->getSymbolLeft($currency) ?: $this->currency->getSymbolRight($currency));
	}

	/**
	 * Extra data for the product page: gallery image sizes, discount percentage and the availability flag. Every
	 * value is derived from the same catalog record the core controller used.
	 *
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function product(array &$args): void {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$product_info = $this->model_catalog_product->getProduct((int)($args['product_id'] ?? 0));

		if (!$product_info) {
			return;
		}

		$width = (int)$this->config->get('config_image_thumb_width');
		$height = (int)$this->config->get('config_image_thumb_height');
		$popup_width = (int)$this->config->get('config_image_popup_width');
		$popup_height = (int)$this->config->get('config_image_popup_height');

		$args['bcf_thumb_width'] = $width;
		$args['bcf_thumb_height'] = $height;
		$args['bcf_in_stock'] = $product_info['quantity'] > 0;
		$args['bcf_first_small'] = $product_info['image'] ? $this->model_tool_image->resize($product_info['image'], 80, 80) : '';

		$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');

		$args['bcf_checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
		$args['bcf_brand_image'] = '';

		$this->load->model('catalog/manufacturer');

		$brand = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

		if ($brand && $brand['image'] && is_file(DIR_IMAGE . html_entity_decode($brand['image'], ENT_QUOTES, 'UTF-8'))) {
			$args['bcf_brand_image'] = $this->model_tool_image->resize($brand['image'], 80, 80);
		}

		$args['bcf_discount_percent'] = 0;

		if ($show_price && (float)$product_info['special'] && (float)$product_info['price'] > 0 && $product_info['special'] < $product_info['price']) {
			$args['bcf_discount_percent'] = (int)round((1 - $product_info['special'] / $product_info['price']) * 100);
		}

		// Gallery thumbnails and the main image share the core image order; add the main-size URL for the swap.
		$args['images'] = [];

		foreach ($this->model_catalog_product->getImages($product_info['product_id']) as $result) {
			if (!$result['image'] || !is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				continue;
			}

			$args['images'][] = [
				'bcf_main' => $this->model_tool_image->resize($result['image'], $width, $height),
				'popup'    => $this->model_tool_image->resize($result['image'], $popup_width, $popup_height),
				'thumb'    => $this->model_tool_image->resize($result['image'], 80, 80)
			];
		}
	}

	/**
	 * Badges for the shared product card. Raw values are passed by the BeamCF homepage; for cards built by
	 * other modules only the sale badge can be told from the formatted special price.
	 *
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function thumb(array &$args): void {
		$badges = [];
		$raw = $args['bcf_raw'] ?? [];

		if (isset($raw['quantity']) && $raw['quantity'] <= 0) {
			$badges[] = ['class' => 'bcf-badge-out', 'text' => $this->language->get('bcf_out_of_stock')];
		}

		if (!empty($args['special'])) {
			$text = $this->language->get('bcf_sale');

			if (!empty($raw['price']) && !empty($raw['special']) && $raw['special'] < $raw['price']) {
				$text = '-' . round((1 - $raw['special'] / $raw['price']) * 100) . '%';
			}

			$badges[] = ['class' => 'bcf-badge-sale', 'text' => $text];
		}

		if (!empty($raw['date_added']) && strtotime($raw['date_added']) > strtotime('-30 days')) {
			$badges[] = ['class' => 'bcf-badge-new', 'text' => $this->language->get('bcf_new')];
		}

		$args['bcf_badges'] = $badges;
		$args['bcf_quickview'] = $this->url->link('extension/beamcf/product/quickview', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)($args['product_id'] ?? 0));
	}

	/**
	 * @return string
	 */
	protected function logo(): string {
		if (is_file(DIR_EXTENSION . 'beamcf/catalog/view/image/beamcf-logo.png')) {
			return $this->config->get('config_url') . 'extension/beamcf/catalog/view/image/beamcf-logo.png';
		}

		return '';
	}

	/**
	 * Whether the customer details (and shipping address when needed) are already saved in the session.
	 * Mirrors the checks the core shipping and payment method controllers make, so the locked state of the
	 * checkout side panel never disagrees with what those controllers will accept.
	 *
	 * @return bool
	 */
	protected function checkoutReady(): bool {
		$payment_ok = !$this->config->get('config_checkout_payment_address') || isset($this->session->data['payment_address']);
		$shipping_ok = !$this->cart->hasShipping() || isset($this->session->data['shipping_address']['address_id']);

		return isset($this->session->data['customer']) && $payment_ok && $shipping_ok;
	}

	/**
	 * Copies the theme language strings into the template data.
	 *
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function text(array &$args): void {
		foreach (['skip', 'shop_by_category', 'categories', 'menu', 'close', 'home', 'special', 'products', 'blog', 'contact', 'compare', 'search', 'cart', 'items', 'all', 'my_account', 'quick_links', 'information', 'brands', 'address', 'call_us', 'email_us', 'follow', 'back_to_top', 'shop_now', 'prev', 'next', 'slide', 'shop_by_category_title', 'view_all', 'latest', 'featured', 'bestseller', 'deals', 'deal_ends', 'days', 'hours', 'minutes', 'seconds', 'special_products', 'best_sellers', 'from_blog', 'read_more', 'brands_title', 'sale', 'new', 'out_of_stock', 'our_products', 'testimonials', 'view_more', 'filters', 'filter_by', 'price', 'price_min', 'price_max', 'brand', 'availability', 'in_stock', 'on_sale', 'rating', 'rating_up', 'apply', 'reset', 'clear_all', 'active_filters', 'remove_filter', 'showing', 'showing_one', 'sort_by', 'show', 'grid_view', 'list_view', 'no_match', 'no_match_help', 'no_filters', 'category_empty', 'subcategories', 'filter_open', 'search_label', 'search_placeholder', 'filter_title', 'to', 'show_sub', 'pre_order', 'color', 'size', 'discount', 'discount_up', 'quick_view', 'qv_loading', 'qv_failed', 'qv_retry', 'qv_wishlist_failed', 'pd_zoom', 'pd_buy_now', 'pd_compare', 'qty_less', 'qty_more', 'order_summary', 'step', 'continue_label', 'locked_hint', 'locked_changed', 'locked_go'] as $key) {
			$args['bcf_text_' . $key] = $this->language->get('bcf_' . $key);
		}
	}
}
