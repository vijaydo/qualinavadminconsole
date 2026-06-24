<?php
/**
 * Plugin Name: Do Tank File Manager
 * Description: DHCI-standard uploader for all users.
 * Version: 0.3.18
 * Author: Vijay Koushik
 */

defined('ABSPATH') || exit;

if (!defined('DTU_VERSION')) {
    define('DTU_VERSION', '0.3.18');
}

/**
 * Core path / URL constants
 */
if (!defined('DTU_DIR')) {
    define('DTU_DIR', plugin_dir_path(__FILE__));
}
if (!defined('DTU_URL')) {
    define('DTU_URL', plugin_dir_url(__FILE__));
}

// -----------------------------------------------------
// LOAD DTU_Config FIRST (REQUIRED FOR SETTINGS & ADMIN)
// -----------------------------------------------------
require_once DTU_DIR . 'includes/class-dtu-config.php';

// -----------------------------------------------------
// LOAD GOOGLE SDK
// -----------------------------------------------------
require_once DTU_DIR . 'vendor/autoload.php';

define('DTU_GDRIVE_PUBLIC_FILES', true);

// -----------------------------------------------------
// AUTO-LOADER (DTU_* from BOTH includes/ and admin/)
// -----------------------------------------------------
spl_autoload_register(function ($class) {

    if (strpos($class, 'DTU_') !== 0) {
        return;
    }

    $filename = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

    $paths = [
        DTU_DIR . 'includes/' . $filename,
        DTU_DIR . 'admin/' . $filename,
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// -----------------------------------------------------
// LOAD ADMIN FILES (NEEDED ALSO FOR AJAX OUTSIDE)
// -----------------------------------------------------
$admin_main = DTU_DIR . 'admin/class-dtu-admin.php';
if (file_exists($admin_main)) {
    require_once $admin_main;
}

// -----------------------------------------------------
// INITIALIZE CORE COMPONENTS
// -----------------------------------------------------
global $DTU;

add_action('plugins_loaded', function () {
    global $DTU;

    $DTU = new stdClass();

    // Frontend uploader page
    if (class_exists('DTU_Page')) {
        $DTU->page = new DTU_Page();
    }

    // AJAX / REST Endpoints
    if (class_exists('DTU_Endpoints')) {
        $DTU->endpoints = new DTU_Endpoints();
    }

    // Organization document index table.
    if (class_exists('DTU_Documents_DB')) {
        DTU_Documents_DB::ensure_schema();
    }

    // Admin UI (tabbed)
    if (class_exists('DTU_Admin')) {
        $DTU->admin = new DTU_Admin();
    }

    // Updater
    if (class_exists('DTU_Updater')) {
        DTU_Updater::init();
    }
});
