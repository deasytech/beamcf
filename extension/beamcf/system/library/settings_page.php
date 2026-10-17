<?php
namespace Opencart\System\Library\Extension\Beamcf;
/**
 * Trait SettingsPage
 *
 * Shared plumbing for the BeamCF admin settings pages (payment and shipping extensions): page chrome,
 * breadcrumbs, and the permission check, setting write and JSON reply of the save action.
 * The using controller supplies the extension route and the extension type.
 *
 * @package Opencart\System\Library\Extension\Beamcf
 */
trait SettingsPage {
    /**
     * @return string e.g. extension/paystack/payment/paystack
     */
    abstract protected function route(): string;

    /**
     * @return string payment or shipping
     */
    abstract protected function extensionType(): string;

    /**
     * Admin link carrying the user token.
     *
     * @param string $route
     * @param string $query extra query string starting with &
     *
     * @return string
     */
    protected function adminLink(string $route, string $query = ''): string {
        return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $query);
    }

    /**
     * Render the settings page around the controller's own data.
     *
     * @param array<string, mixed> $data
     *
     * @return void
     */
    protected function renderPage(array $data): void {
        $this->load->language($this->route());

        $this->document->setTitle($this->language->get('heading_title'));

        $list = $this->adminLink('marketplace/extension', '&type=' . $this->extensionType());

        $trail = [
            'text_home'      => $this->adminLink('common/dashboard'),
            'text_extension' => $list,
            'heading_title'  => $this->adminLink($this->route())
        ];

        $data['breadcrumbs'] = [];

        foreach ($trail as $key => $href) {
            $data['breadcrumbs'][] = ['text' => $this->language->get($key), 'href' => $href];
        }

        $data['save'] = $this->adminLink($this->route() . '.save');
        $data['back'] = $list;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->route(), $data));
    }

    /**
     * Finish a save request: permission check, write the settings when there are no errors, reply as JSON.
     *
     * @param string               $code     setting group, e.g. payment_paystack
     * @param array<string, mixed> $settings values to store
     * @param array<string, mixed> $json      validation errors collected by the controller
     *
     * @return void
     */
    protected function saveSettings(string $code, array $settings, array $json = []): void {
        $this->load->language($this->route());

        if (!$this->user->hasPermission('modify', $this->route())) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting($code, $settings);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
