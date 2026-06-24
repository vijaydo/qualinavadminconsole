<?php
/**
 * Plugin Name: Qualinav QI Projects
 * Plugin URI:  https://qualinav.com
 * Description: Quality Improvement projects with admin-editable canvas templates, multi-tenant org scoping, and structured data tables for cross-platform AI interop.
 * Version:     0.1.5
 * Author:      Grapevine Team / Markelo Rapti
 * Author URI:  https://qualinav.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qualinav-qi-projects
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'QUALINAV_QI_VERSION', '0.8.3' );
define( 'QUALINAV_QI_DB_VERSION', '5' );
define( 'QUALINAV_QI_PLUGIN_FILE', __FILE__ );
define( 'QUALINAV_QI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUALINAV_QI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QUALINAV_QI_TABLE_PREFIX', 'qi_' );

require_once QUALINAV_QI_PLUGIN_DIR . 'includes/class-activator.php';
require_once QUALINAV_QI_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once QUALINAV_QI_PLUGIN_DIR . 'includes/class-plugin.php';
require_once QUALINAV_QI_PLUGIN_DIR . 'includes/class-cpt.php';

register_activation_hook( __FILE__, array( 'Qualinav_QI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Qualinav_QI_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Qualinav_QI_Plugin', 'boot' ) );
