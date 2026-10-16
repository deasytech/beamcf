<?php
namespace Opencart\Catalog\Controller\Extension\Beamcf\Event;
/**
 * Class Catalog
 *
 * Event listeners that make the BeamCF category filters work while the core category controller stays untouched.
 *
 * 1. products(): redirects the category product query to the BeamCF filter model, only for the category being listed
 *    and only while a BeamCF filter is in the request.
 * 2. pagination(): carries the BeamCF filter parameters into the pagination links, which the core controller builds
 *    from a fixed list of parameters.
 *
 * @package Opencart\Catalog\Controller\Extension\Beamcf\Event
 */
class Catalog extends \Opencart\System\Engine\Controller {
	/**
	 * Filter parameters owned by the BeamCF category filters.
	 *
	 * @var array<int, string>
	 */
	private const PARAMS = ['price_min', 'price_max', 'brand', 'stock', 'rating', 'preorder', 'color', 'size', 'discount', 'q'];

	/**
	 * Products
	 *
	 * Listens on catalog/model/catalog/product.getProducts/before and getTotalProducts/before.
	 *
	 * @param string               $route
	 * @param array<string, mixed> $args
	 *
	 * @return void
	 */
	public function products(string &$route, array &$args): void {
		if (!$this->active() || !in_array($route, ['catalog/product.getProducts', 'catalog/product.getTotalProducts'])) {
			return;
		}

		$data = $args[0] ?? [];

		// Only the main listing query: sub-category counts and other modules use a different category or none.
		if (empty($data['filter_category_id']) || (int)$data['filter_category_id'] !== $this->categoryId()) {
			return;
		}

		$this->load->model('extension/beamcf/theme/catalog');

		if (!$this->model_extension_beamcf_theme_catalog->hasActive($this->model_extension_beamcf_theme_catalog->getActive())) {
			return;
		}

		$route = 'extension/beamcf/theme/catalog.' . substr($route, strrpos($route, '.') + 1);
	}

	/**
	 * Pagination
	 *
	 * Listens on catalog/controller/common/pagination/before.
	 *
	 * @param string               $route
	 * @param array<int, mixed>    $args
	 *
	 * @return void
	 */
	public function pagination(string &$route, array &$args): void {
		if (!$this->active() || !isset($args[0]['url'])) {
			return;
		}

		$query = http_build_query(array_intersect_key($this->request->get, array_flip(self::PARAMS)));

		// Insert before the {page} placeholder so the URL keeps its shape. Links normally carry escaped ampersands, but the raw form is handled too.
		if ($query) {
			$escaped = '&amp;' . str_replace('&', '&amp;', $query);
			$raw = '&' . $query;

			$args[0]['url'] = str_replace(
				['&amp;page={page}', '&amp;page=%7Bpage%7D', '&page={page}', '&page=%7Bpage%7D'],
				[$escaped . '&amp;page={page}', $escaped . '&amp;page=%7Bpage%7D', $raw . '&page={page}', $raw . '&page=%7Bpage%7D'],
				$args[0]['url']
			);
		}
	}

	/**
	 * @return bool
	 */
	private function active(): bool {
		return $this->config->get('config_theme') == 'beamcf' && $this->config->get('theme_beamcf_status') && ($this->request->get['route'] ?? '') == 'product/category';
	}

	/**
	 * @return int
	 */
	private function categoryId(): int {
		$parts = explode('_', (string)($this->request->get['path'] ?? ''));

		return (int)array_pop($parts);
	}
}
