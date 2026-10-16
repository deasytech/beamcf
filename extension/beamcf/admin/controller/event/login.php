<?php
namespace Opencart\Admin\Controller\Extension\Beamcf\Event;
/**
 * Class Login
 *
 * Shows the company logo above the admin login form. Runs on admin/view/common/login/after, so the core
 * template is not edited.
 *
 * @package Opencart\Admin\Controller\Extension\Beamcf\Event
 */
class Login extends \Opencart\System\Engine\Controller {
	/**
	 * @param string $route
	 * @param array<string, mixed> $args
	 * @param mixed $output
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args, mixed &$output): void {
		$file = 'extension/beamcf/catalog/view/image/beamcf-logo.png';

		if (!is_file(DIR_EXTENSION . 'beamcf/catalog/view/image/beamcf-logo.png') || !is_string($output)) {
			return;
		}

		$logo = '<div class="text-center mb-4"><img src="' . HTTP_CATALOG . $file . '" alt="' . htmlspecialchars((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . '" style="max-height: 120px; width: auto; max-width: 100%;"/></div>';

		// The top-left OpenCart logo in the header bar is swapped for the company logo as well.
		$output = preg_replace('#<img src="view/image/logo\.png" alt="OpenCart" title="OpenCart"/>#', '<img src="' . HTTP_CATALOG . $file . '" alt="' . htmlspecialchars((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . '" style="max-height: 40px; width: auto;"/>', $output, 1);

		$marker = '<div class="col-sm-10 col-md-8 col-lg-5">';
		$position = strpos($output, $marker);

		if ($position !== false) {
			$position += strlen($marker);
			$output = substr($output, 0, $position) . $logo . substr($output, $position);
		}
	}
}
