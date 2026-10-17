<?php
namespace Opencart\Admin\Controller\Extension\Geozone\Shipping;
/**
 * Class Geozone
 *
 * @package Opencart\Admin\Controller\Extension\Geozone\Shipping
 */
class Geozone extends \Opencart\System\Engine\Controller {
    use \Opencart\System\Library\Extension\Beamcf\SettingsPage;

    private const ROUTE = 'extension/geozone/shipping/geozone';

    /**
     * @return string
     */
    protected function route(): string {
        return self::ROUTE;
    }

    /**
     * @return string
     */
    protected function extensionType(): string {
        return 'shipping';
    }

    /**
     * Index
     *
     * @return void
     */
    public function index(): void {
        $this->load->model('localisation/language');
        $this->load->model('localisation/geo_zone');
        $this->load->model('localisation/tax_class');

        $this->renderPage([
            'languages'                   => $this->model_localisation_language->getLanguages(),
            'geo_zones'                   => $this->model_localisation_geo_zone->getGeoZones(),
            'tax_classes'                 => $this->model_localisation_tax_class->getTaxClasses(),
            'shipping_geozone_methods'    => (array)$this->config->get('shipping_geozone_method'),
            'shipping_geozone_status'     => $this->config->get('shipping_geozone_status'),
            'shipping_geozone_sort_order' => $this->config->get('shipping_geozone_sort_order')
        ]);
    }

    /**
     * Save
     *
     * @return void
     */
    public function save(): void {
        $this->load->language(self::ROUTE);

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $errors = [];
        $methods = [];

        foreach ((array)($this->request->post['shipping_geozone_method'] ?? []) as $key => $method) {
            $methods[] = $this->cleanMethod((array)$method, $languages, (int)$key, $errors);
        }

        $json = $errors ? ['error' => $errors + ['warning' => $this->language->get('error_form')]] : [];

        $this->saveSettings('shipping_geozone', [
            'shipping_geozone_method'     => $methods,
            'shipping_geozone_status'     => (int)($this->request->post['shipping_geozone_status'] ?? 0),
            'shipping_geozone_sort_order' => (int)($this->request->post['shipping_geozone_sort_order'] ?? 0)
        ], $json);
    }

    /**
     * Normalise one posted method and record its validation errors.
     *
     * @param array<string, mixed>              $method
     * @param array<int, array<string, mixed>>  $languages
     * @param int                               $key
     * @param array<string, string>             $errors
     *
     * @return array<string, mixed>
     */
    private function cleanMethod(array $method, array $languages, int $key, array &$errors): array {
        $row = [
            'status'       => (int)($method['status'] ?? 0),
            'total'        => trim((string)($method['total'] ?? '0')),
            'cost'         => trim((string)($method['cost'] ?? '0')),
            'geo_zone_id'  => (int)($method['geo_zone_id'] ?? 0),
            'tax_class_id' => (int)($method['tax_class_id'] ?? 0),
            'name'         => [],
            'instructions' => []
        ];

        foreach ($languages as $language) {
            $id = $language['language_id'];

            $row['name'][$id] = trim((string)($method['name'][$id] ?? ''));
            $row['instructions'][$id] = trim((string)($method['instructions'][$id] ?? ''));

            if ($row['name'][$id] === '') {
                $errors['name_' . $key . '_' . $id] = $this->language->get('error_name');
            }
        }

        foreach (['cost', 'total'] as $field) {
            if (!is_numeric($row[$field]) || (float)$row[$field] < 0) {
                $errors[$field . '_' . $key] = $this->language->get('error_' . $field);
            }
        }

        return $row;
    }
}
