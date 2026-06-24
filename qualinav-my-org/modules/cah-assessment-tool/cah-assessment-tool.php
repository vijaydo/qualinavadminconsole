<?php
/**
 * Plugin Name: QualiNav Assessment & Readiness
 * Description: Data-driven Likert assessments with scoring + stored submissions.
 * Version: 1.1.3
 * Author: Maxim Dascalasu
 */

if (!defined('ABSPATH')) exit;

define('CAH_ASSESS_VERSION', '1.1.3');
define('CAH_ASSESS_PATH', plugin_dir_path(__FILE__));
define('CAH_ASSESS_URL', plugin_dir_url(__FILE__));

/**
 * Optional: require login to submit.
 * Set to true in wp-config.php or MU plugin if needed:
 * define('CAH_ASSESS_REQUIRE_LOGIN', true);
 */
if (!defined('CAH_ASSESS_REQUIRE_LOGIN')) {
  define('CAH_ASSESS_REQUIRE_LOGIN', false);
}

require_once CAH_ASSESS_PATH . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['CAH_Assess\\Plugin', 'activate']);

add_action('plugins_loaded', function () {
  CAH_Assess\Plugin::init();
});
