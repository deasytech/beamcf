<?php
namespace Opencart\Admin\Controller\Extension\Beamcf\Theme;
/**
 * Class Beamcf
 *
 * @package Opencart\Admin\Controller\Extension\Beamcf\Theme
 */
class Beamcf extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<string, string>
	 */
	private array $urls = [
		'app_url'   => 'error_app_url',
		'facebook'  => 'error_facebook',
		'x'         => 'error_x',
		'instagram' => 'error_instagram',
		'youtube'   => 'error_youtube'
	];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/beamcf/theme/beamcf');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->request->get['store_id'])) {
			$store_id = (int)$this->request->get['store_id'];
		} else {
			$store_id = 0;
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/beamcf/theme/beamcf', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id)
		];

		$data['save'] = $this->url->link('extension/beamcf/theme/beamcf.save', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme');

		// Setting
		$this->load->model('setting/setting');

		$setting_info = $this->model_setting_setting->getSetting('theme_beamcf', $store_id);

		$keys = ['status', 'announcement', 'app_text', 'app_url', 'facebook', 'x', 'instagram', 'youtube', 'hero_banner_id', 'promo_banner_id', 'deals_banner_id', 'campaign_banner_id', 'campaign_text'];

		for ($i = 1; $i <= 4; $i++) {
			$keys[] = 'benefit_' . $i . '_title';
			$keys[] = 'benefit_' . $i . '_text';
		}

		foreach ($keys as $key) {
			$data['theme_beamcf_' . $key] = $setting_info['theme_beamcf_' . $key] ?? '';
		}

		// Banners (Design > Banner) the homepage sections can use
		$this->load->model('design/banner');

		$data['banners'] = $this->model_design_banner->getBanners();
		$data['benefit_icons'] = ['fa-truck-fast', 'fa-headset', 'fa-rotate-left', 'fa-shield-halved'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/beamcf/theme/beamcf', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/beamcf/theme/beamcf');

		$json = [];

		if (isset($this->request->get['store_id'])) {
			$store_id = (int)$this->request->get['store_id'];
		} else {
			$store_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'extension/beamcf/theme/beamcf')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		foreach (array_keys($this->urls) as $key) {
			$value = trim((string)($this->request->post['theme_beamcf_' . $key] ?? ''));

			if ($value && (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https']))) {
				$json['error'][$key] = $this->language->get('error_url');
			}
		}

		if (!empty($this->request->post['theme_beamcf_app_url']) && empty(trim((string)($this->request->post['theme_beamcf_app_text'] ?? '')))) {
			$json['error']['app_text'] = $this->language->get('error_app_text');
		}

		if (!$json) {
			// Only keys of this theme are stored
			$post = [];

			foreach ($this->request->post as $key => $value) {
				if (str_starts_with($key, 'theme_beamcf_')) {
					$post[$key] = is_string($value) ? trim($value) : $value;
				}
			}

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('theme_beamcf', $post, $store_id);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install
	 *
	 * Registers the template switching event. Nothing in the default theme is changed.
	 *
	 * @return void
	 */
	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/theme')) {
			$this->load->model('setting/event');

			$this->model_setting_event->deleteEventByCode('beamcf_theme');
			$this->model_setting_event->deleteEventByCode('beamcf_catalog_products');
			$this->model_setting_event->deleteEventByCode('beamcf_catalog_total');
			$this->model_setting_event->deleteEventByCode('beamcf_catalog_pagination');

			$this->model_setting_event->addEvent([
				'code'        => 'beamcf_theme',
				'description' => 'BeamCF theme: swap in BeamCF templates when the BeamCF theme is selected.',
				'trigger'     => 'catalog/view/*/before',
				'action'      => 'extension/beamcf/event/theme.view',
				'status'      => true,
				'sort_order'  => 1
			]);

			// Category filters: route the listing queries to the BeamCF filter model while a filter is active.
			$this->model_setting_event->addEvent([
				'code'        => 'beamcf_catalog_products',
				'description' => 'BeamCF theme: apply category filters to the product query.',
				'trigger'     => 'catalog/model/catalog/product.getProducts/before',
				'action'      => 'extension/beamcf/event/catalog.products',
				'status'      => true,
				'sort_order'  => 1
			]);

			$this->model_setting_event->addEvent([
				'code'        => 'beamcf_catalog_total',
				'description' => 'BeamCF theme: apply category filters to the product count.',
				'trigger'     => 'catalog/model/catalog/product.getTotalProducts/before',
				'action'      => 'extension/beamcf/event/catalog.products',
				'status'      => true,
				'sort_order'  => 1
			]);

			$this->model_setting_event->addEvent([
				'code'        => 'beamcf_catalog_pagination',
				'description' => 'BeamCF theme: keep category filters in pagination links.',
				'trigger'     => 'catalog/controller/common/pagination/before',
				'action'      => 'extension/beamcf/event/catalog.pagination',
				'status'      => true,
				'sort_order'  => 1
			]);
		}
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/theme')) {
			$this->load->model('setting/event');

			$this->model_setting_event->deleteEventByCode('beamcf_theme');
			$this->model_setting_event->deleteEventByCode('beamcf_catalog_products');
			$this->model_setting_event->deleteEventByCode('beamcf_catalog_total');
			$this->model_setting_event->deleteEventByCode('beamcf_catalog_pagination');
		}
	}
}
