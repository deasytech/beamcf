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

		$args['bcf_stylesheet'] = 'extension/beamcf/catalog/view/stylesheet/beamcf.css';
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
		$args['bcf_script'] = 'extension/beamcf/catalog/view/javascript/beamcf.js';
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
	 * Copies the theme language strings into the template data.
	 *
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	protected function text(array &$args): void {
		foreach (['skip', 'shop_by_category', 'categories', 'menu', 'close', 'home', 'special', 'products', 'blog', 'contact', 'compare', 'search', 'cart', 'items', 'all', 'my_account', 'quick_links', 'information', 'brands', 'address', 'call_us', 'email_us', 'follow', 'back_to_top', 'shop_now', 'prev', 'next', 'slide', 'shop_by_category_title', 'view_all', 'latest', 'featured', 'bestseller', 'deals', 'deal_ends', 'days', 'hours', 'minutes', 'seconds', 'special_products', 'best_sellers', 'from_blog', 'read_more', 'brands_title', 'sale', 'new', 'out_of_stock', 'our_products', 'testimonials', 'view_more'] as $key) {
			$args['bcf_text_' . $key] = $this->language->get('bcf_' . $key);
		}
	}
}
