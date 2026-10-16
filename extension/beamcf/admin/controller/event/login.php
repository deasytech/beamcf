<?php
namespace Opencart\Admin\Controller\Extension\Beamcf\Event;
/**
 * Class Login
 *
 * Shows the company logo on the admin login screen. Runs on admin/view/common/login/after, so the core
 * template is not edited.
 *
 * @package Opencart\Admin\Controller\Extension\Beamcf\Event
 */
class Login extends \Opencart\System\Engine\Controller {
    private const LOGO = 'extension/beamcf/catalog/view/image/beamcf-logo.png';

    /**
     * The view "after" event passes [&$route, &$data, &$output]; only the rendered HTML (the third argument) is used.
     *
     * @param mixed ...$args
     *
     * @return void
     */
    public function index(mixed &...$args): void {
        $output = &$args[2];

        if (!is_string($output) || !is_file(DIR_EXTENSION . 'beamcf/catalog/view/image/beamcf-logo.png')) {
            return;
        }

        $src = HTTP_CATALOG . self::LOGO;
        $alt = htmlspecialchars((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8');

        // The top-left OpenCart logo in the header bar is swapped for the company logo.
        $output = preg_replace('#<img\s[^>]*src="view/image/logo\.png"[^>]*>#', '<img src="' . $src . '" alt="' . $alt . '" style="max-height: 40px; width: auto;"/>', $output, 1);

        // And the same logo is shown above the login box.
        $marker = '<div class="col-sm-10 col-md-8 col-lg-5">';
        $position = strpos($output, $marker);

        if ($position !== false) {
            $position += strlen($marker);
            $logo = '<div class="text-center mb-4"><img src="' . $src . '" alt="' . $alt . '" style="max-height: 120px; width: auto; max-width: 100%;"/></div>';
            $output = substr($output, 0, $position) . $logo . substr($output, $position);
        }
    }
}
