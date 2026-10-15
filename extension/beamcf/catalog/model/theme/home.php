<?php
namespace Opencart\Catalog\Model\Extension\Beamcf\Theme;
/**
 * Class Home
 *
 * Collects the data for the BeamCF homepage sections from existing OpenCart data. A section whose data
 * source is missing or empty is left out of the result, so the template never renders fake content.
 *
 * Can be called from $this->load->model('extension/beamcf/theme/home');
 *
 * @package Opencart\Catalog\Model\Extension\Beamcf\Theme
 */
class Home extends \Opencart\System\Engine\Model {
	/**
	 * @var string
	 */
	private string $language_param = '';

	/**
	 * @var array<int, string> manufacturer names by id, for the product cards
	 */
	private array $manufacturers = [];

	/**
	 * Get Sections
	 *
	 * @return array<string, mixed>
	 */
	public function getSections(): array {
		$this->language_param = 'language=' . $this->config->get('config_language');

		$this->load->model('catalog/product');
		$this->load->model('catalog/manufacturer');
		$this->load->model('tool/image');

		foreach ($this->model_catalog_manufacturer->getManufacturers() as $manufacturer) {
			$this->manufacturers[(int)$manufacturer['manufacturer_id']] = $manufacturer['name'];
		}

		$sections = [];

		$sections['hero'] = $this->getBanner($this->getHeroBannerId(), 1920, 600);
		$sections['promo'] = array_slice($this->getBanner((int)$this->config->get('theme_beamcf_promo_banner_id'), 820, 255), 0, 2);
		$sections['benefits'] = $this->getBenefits();
		$sections['deal'] = $this->getDeal();

		$deal_banners = $this->getBanner((int)$this->config->get('theme_beamcf_deals_banner_id'), 640, 520);

		$sections['deal_tiles'] = $sections['deal'] ? array_slice($deal_banners, 0, 2) : [];
		$sections['categories'] = $this->getCategories();

		// Product tabs. Only tabs with products are kept.
		$tabs = [];

		$latest = $this->getProducts(['sort' => 'p.date_added', 'order' => 'DESC', 'start' => 0, 'limit' => 8]);

		if ($latest) {
			$tabs['latest'] = $latest;
		}

		$featured = $this->getFeatured();

		if ($featured) {
			$tabs['featured'] = $featured;
		}

		$best_sellers = $this->getBestSellers(8);

		if ($best_sellers) {
			$tabs['bestseller'] = $best_sellers;
		}

		$sections['tabs'] = $tabs;

		// Campaign banner: image is the wide dark banner.
		$campaign_id = (int)$this->config->get('theme_beamcf_campaign_banner_id');
		$campaign = $this->getBanner($campaign_id, 1320, 400);

		$sections['campaign'] = $campaign ? $campaign[0] : [];
		$sections['campaign_text'] = trim((string)$this->config->get('theme_beamcf_campaign_text'));

		$sections['specials'] = $this->getSpecialProducts(10);
		$sections['mix_bestsellers'] = $best_sellers ? array_slice($best_sellers, 0, 10) : [];

		$sections['articles'] = $this->getArticles(3);
		$sections['reviews'] = $this->getReviews(5);

		return $sections;
	}

	/**
	 * Hero banner: the one chosen in the theme settings, otherwise the first enabled Banner module placed
	 * in the Home layout's content top (the usual "Homepage Slideshow").
	 *
	 * @return int
	 */
	protected function getHeroBannerId(): int {
		$banner_id = (int)$this->config->get('theme_beamcf_hero_banner_id');

		if ($banner_id) {
			return $banner_id;
		}

		$this->load->model('setting/module');

		$query = $this->db->query("SELECT lm.`code` FROM `" . DB_PREFIX . "layout_module` lm LEFT JOIN `" . DB_PREFIX . "layout_route` lr ON (lm.`layout_id` = lr.`layout_id`) WHERE lr.`route` = 'common/home' AND lr.`store_id` = '" . (int)$this->config->get('config_store_id') . "' AND lm.`position` = 'content_top' AND lm.`code` LIKE 'opencart.banner.%' ORDER BY lm.`sort_order` ASC");

		foreach ($query->rows as $row) {
			$setting = $this->model_setting_module->getModule((int)substr(strrchr($row['code'], '.'), 1));

			if (!empty($setting['status']) && !empty($setting['banner_id'])) {
				return (int)$setting['banner_id'];
			}
		}

		return 0;
	}

	/**
	 * Banner images of a banner, resized. Images that do not exist are skipped.
	 *
	 * @param int $banner_id
	 * @param int $width
	 * @param int $height
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getBanner(int $banner_id, int $width, int $height): array {
		if (!$banner_id) {
			return [];
		}

		$this->load->model('design/banner');

		$banners = [];

		foreach ($this->model_design_banner->getBanner($banner_id) as $result) {
			$image = html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8');

			if ($image && is_file(DIR_IMAGE . $image)) {
				$banners[] = [
					'title' => $result['title'],
					'link'  => $result['link'],
					'image' => $this->model_tool_image->resize($image, $width, $height),
					'width' => $width,
					'height' => $height
				];
			}
		}

		return $banners;
	}

	/**
	 * Store benefits from the theme settings. Entries without a title are not shown.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getBenefits(): array {
		$icons = ['fa-truck-fast', 'fa-headset', 'fa-rotate-left', 'fa-shield-halved'];
		$benefits = [];

		foreach ($icons as $key => $icon) {
			$title = trim((string)$this->config->get('theme_beamcf_benefit_' . ($key + 1) . '_title'));

			if ($title) {
				$benefits[] = [
					'icon'  => $icon,
					'title' => $title,
					'text'  => trim((string)$this->config->get('theme_beamcf_benefit_' . ($key + 1) . '_text'))
				];
			}
		}

		return $benefits;
	}

	/**
	 * Featured deal: an active special, preferring one that has a real end date.
	 *
	 * @return array<string, mixed>
	 */
	protected function getDeal(): array {
		$results = $this->model_catalog_product->getSpecials(['sort' => 'p.sort_order', 'order' => 'ASC', 'start' => 0, 'limit' => 12]);

		if (!$results) {
			return [];
		}

		$result = $results[0];

		foreach ($results as $row) {
			if (!empty($row['date_end']) && $row['date_end'] != '0000-00-00') {
				$result = $row;

				break;
			}
		}

		$deal = ['card' => $this->card($result, 600, 600), 'end' => 0, 'now' => time()];

		// Countdown only for a real expiry. The special stops applying at the start of its end date.
		if (!empty($result['date_end']) && $result['date_end'] != '0000-00-00') {
			$end = strtotime($result['date_end']);

			if ($end && $end > time()) {
				$deal['end'] = $end;
			}
		}

		$deal['name'] = $result['name'];
		$deal['href'] = $this->url->link('product/product', $this->language_param . '&product_id=' . $result['product_id']);

		return $deal;
	}

	/**
	 * Category tiles: top level categories, topped up with sub-categories when the store has few.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getCategories(): array {
		$this->load->model('catalog/category');

		$limit = 12;
		$results = $this->model_catalog_category->getCategories(0);

		if (count($results) < 6) {
			foreach ($this->model_catalog_category->getCategories(0) as $parent) {
				foreach ($this->model_catalog_category->getCategories($parent['category_id']) as $child) {
					$child['parent_id'] = $parent['category_id'];
					$child['path'] = $parent['category_id'] . '_' . $child['category_id'];

					$results[] = $child;
				}
			}
		}

		$categories = [];

		foreach (array_slice($results, 0, $limit) as $result) {
			$path = $result['path'] ?? $result['category_id'];

			$image = html_entity_decode((string)$result['image'], ENT_QUOTES, 'UTF-8');

			if (!$image || !is_file(DIR_IMAGE . $image)) {
				$image = 'placeholder.png';
			}

			$categories[] = [
				'name'  => $result['name'],
				'thumb' => $this->model_tool_image->resize($image, 400, 500),
				'total' => $this->model_catalog_product->getTotalProducts(['filter_category_id' => $result['category_id'], 'filter_sub_category' => true]),
				'href'  => $this->url->link('product/category', $this->language_param . '&path=' . $path)
			];
		}

		return $categories;
	}

	/**
	 * Products of the first enabled Featured module.
	 *
	 * @return array<int, string>
	 */
	protected function getFeatured(): array {
		$this->load->model('setting/module');

		$query = $this->db->query("SELECT `module_id` FROM `" . DB_PREFIX . "module` WHERE `code` = 'opencart.featured' ORDER BY `module_id` ASC");

		foreach ($query->rows as $row) {
			$setting = $this->model_setting_module->getModule((int)$row['module_id']);

			if (!empty($setting['status']) && !empty($setting['product'])) {
				$products = [];

				foreach (array_slice($setting['product'], 0, 8) as $product_id) {
					$product_info = $this->model_catalog_product->getProduct((int)$product_id);

					if ($product_info) {
						$products[] = $this->card($product_info);
					}
				}

				if ($products) {
					return $products;
				}
			}
		}

		return [];
	}

	/**
	 * Best sellers from the Best Seller extension. Empty unless it is installed and has data.
	 *
	 * @param int $limit
	 *
	 * @return array<int, string>
	 */
	protected function getBestSellers(int $limit): array {
		$installed = $this->db->query("SELECT `extension_id` FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` = 'bestseller'")->num_rows;

		if (!$installed || !$this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_bestseller'")->num_rows) {
			return [];
		}

		$this->load->model('extension/opencart/module/bestseller');

		$products = [];

		foreach ($this->model_extension_opencart_module_bestseller->getBestSellers($limit) as $result) {
			$products[] = $this->card($result);
		}

		return $products;
	}

	/**
	 * @param int $limit
	 *
	 * @return array<int, string>
	 */
	protected function getSpecialProducts(int $limit): array {
		$products = [];

		foreach ($this->model_catalog_product->getSpecials(['sort' => 'p.sort_order', 'order' => 'ASC', 'start' => 0, 'limit' => $limit]) as $result) {
			$products[] = $this->card($result);
		}

		return $products;
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, string>
	 */
	protected function getProducts(array $data): array {
		$products = [];

		foreach ($this->model_catalog_product->getProducts($data) as $result) {
			$products[] = $this->card($result);
		}

		return $products;
	}

	/**
	 * Recent blog articles, only when the store has published articles.
	 *
	 * @param int $limit
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getArticles(int $limit): array {
		$this->load->model('cms/article');

		if (!$this->model_cms_article->getTotalArticles()) {
			return [];
		}

		$articles = [];

		foreach ($this->model_cms_article->getArticles(['sort' => 'a.date_added', 'order' => 'DESC', 'start' => 0, 'limit' => $limit]) as $result) {
			$image = html_entity_decode((string)($result['image'] ?? ''), ENT_QUOTES, 'UTF-8');

			$articles[] = [
				'name'  => $result['name'],
				'author' => $result['author'] ?? '',
				'date'  => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'thumb' => $image && is_file(DIR_IMAGE . $image) ? $this->model_tool_image->resize($image, 600, 400) : '',
				'href'  => $this->url->link('cms/blog.info', $this->language_param . '&article_id=' . $result['article_id'])
			];
		}

		return $articles;
	}

	/**
	 * Latest approved product reviews, used as customer testimonials. Empty when there are none.
	 *
	 * @param int $limit
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getReviews(int $limit): array {
		if (!$this->config->get('config_review_status')) {
			return [];
		}

		$query = $this->db->query("SELECT r.`author`, r.`text`, r.`rating`, p.`product_id`, pd.`name` FROM `" . DB_PREFIX . "review` r LEFT JOIN `" . DB_PREFIX . "product` p ON (r.`product_id` = p.`product_id`) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.`product_id` = pd.`product_id`) LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.`product_id` = p2s.`product_id`) WHERE r.`status` = '1' AND p.`status` = '1' AND p.`date_available` <= NOW() AND p2s.`store_id` = '" . (int)$this->config->get('config_store_id') . "' AND pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "' ORDER BY r.`date_added` DESC LIMIT " . (int)$limit);

		$reviews = [];

		foreach ($query->rows as $result) {
			$text = trim(strip_tags(html_entity_decode((string)$result['text'], ENT_QUOTES, 'UTF-8')));

			if ($text) {
				$reviews[] = [
					'text'    => oc_strlen($text) > 260 ? oc_substr($text, 0, 260) . '...' : $text,
					'author'  => $result['author'],
					'rating'  => (int)$result['rating'],
					'product' => $result['name'],
					'href'    => $this->url->link('product/product', $this->language_param . '&product_id=' . $result['product_id'])
				];
			}
		}

		return $reviews;
	}

	/**
	 * Renders one product card with the shared product/thumb component, the same data the core modules pass.
	 *
	 * @param array<string, mixed> $product
	 * @param int                  $width
	 * @param int                  $height
	 *
	 * @return string
	 */
	protected function card(array $product, int $width = 400, int $height = 400): string {
		$image = html_entity_decode((string)$product['image'], ENT_QUOTES, 'UTF-8');

		if (!$image || !is_file(DIR_IMAGE . $image)) {
			$image = 'placeholder.png';
		}

		$currency = $this->session->data['currency'];

		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $currency);
		} else {
			$price = false;
		}

		if ((float)$product['special']) {
			$special = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $currency);
		} else {
			$special = false;
		}

		if ($this->config->get('config_tax')) {
			$tax = $this->currency->format((float)$product['special'] ? $product['special'] : $product['price'], $currency);
		} else {
			$tax = false;
		}

		$data = [
			'product_id'  => $product['product_id'],
			'thumb'       => $this->model_tool_image->resize($image, $width, $height),
			'name'        => $product['name'],
			'description' => oc_substr(trim(strip_tags(html_entity_decode((string)$product['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('config_product_description_length')) . '..',
			'price'       => $price,
			'special'     => $special,
			'tax'         => $tax,
			'minimum'     => $product['minimum'] > 0 ? $product['minimum'] : 1,
			'rating'      => $product['rating'] ?? 0,
			'href'        => $this->url->link('product/product', $this->language_param . '&product_id=' . $product['product_id']),
			'width'       => $width,
			'height'      => $height
		];

		// Raw values for the card badges, see the BeamCF event listener.
		$data['manufacturer'] = $this->manufacturers[(int)($product['manufacturer_id'] ?? 0)] ?? '';

		$data['bcf_raw'] = [
			'price'      => (float)$product['price'],
			'special'    => (float)$product['special'],
			'quantity'   => (int)$product['quantity'],
			'date_added' => (string)($product['date_added'] ?? '')
		];

		return $this->load->controller('product/thumb', $data);
	}
}
