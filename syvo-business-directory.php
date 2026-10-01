<?php
/**
 * Plugin Name: Syvo Business Directory
 * Description: Production-ready local business directory, local search, and SEO/GEO landing engine for Syvo.
 * Version: 1.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Author: Syvo
 * Text Domain: syvo-business-directory
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Syvo\BusinessDirectory;

if (!defined('ABSPATH')) {
    exit;
}

define('SYVO_BD_VERSION', '1.1.0');
define('SYVO_BD_DB_VERSION', '1.1.0');
define('SYVO_BD_LOCATION_DATASET_VERSION', '1402-mralfak-2026-09');
define('SYVO_BD_FILE', __FILE__);
define('SYVO_BD_DIR', plugin_dir_path(__FILE__));
define('SYVO_BD_URL', plugin_dir_url(__FILE__));
define('SYVO_BD_TEXTDOMAIN', 'syvo-business-directory');
define('SYVO_BD_REST_NAMESPACE', 'syvo-bd/v1');
define('SYVO_BD_CPT', 'syvo_business');
define('SYVO_BD_CATEGORY_TAX', 'syvo_bd_category');
define('SYVO_BD_SERVICE_TAX', 'syvo_bd_service');

require_once SYVO_BD_DIR . 'includes/Plugin.php';

add_action('plugins_loaded', static function (): void {
    Plugin::boot();
});

register_activation_hook(SYVO_BD_FILE, [Plugin::class, 'activate']);
register_deactivation_hook(SYVO_BD_FILE, [Plugin::class, 'deactivate']);
