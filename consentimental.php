<?php
/**
 * Plugin Name:       Consentimental
 * Plugin URI:        https://github.com/lvfeili/consentimental-public
 * Description:       Consent management that enforces blocking, not just a banner. GDPR/ePrivacy-compatible consent collection with prior blocking of consent-dependent technologies.
 * Version:           0.3.5
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Philipp Krüger
 * Author URI:        https://www.philippkrueger.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        consentimental
 * Domain Path:        /languages
 */

declare(strict_types=1);

namespace Consentimental;

if (!defined('ABSPATH')) {
	exit;
}

const CMP_VERSION = '0.3.5';
const CMP_PLUGIN_FILE = __FILE__;

define(__NAMESPACE__ . '\CMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define(__NAMESPACE__ . '\CMP_PLUGIN_URL', plugin_dir_url(__FILE__));

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(static function (string $class): void {
		if (!str_starts_with($class, __NAMESPACE__ . '\\')) {
			return;
		}

		$file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen(__NAMESPACE__) + 1)) . '.php';

		if (is_readable($file)) {
			require_once $file;
		}
	});
}

require_once __DIR__ . '/src/functions.php';

register_activation_hook(__FILE__, [Activation::class, 'activate']);
register_deactivation_hook(__FILE__, [Activation::class, 'deactivate']);

add_action('init', static function (): void {
	Plugin::instance()->boot();
});

function consentimental(): Plugin
{
	return Plugin::instance();
}
