<?php
/**
 * Plugin Name: QualiNav Admin Console
 * Description: Frontend console foundation, user model, routing, and access control for QualiNav.
 * Version: 0.1.325
 * Author: QualiNav
 * Text Domain: qualinav-admin-console
 */

if (!defined('ABSPATH')) {
    exit;
}

define('QN_ADMIN_CONSOLE_VERSION', '0.1.325');
define('QN_ADMIN_CONSOLE_FILE', __FILE__);
define('QN_ADMIN_CONSOLE_DIR', plugin_dir_path(__FILE__));
define('QN_ADMIN_CONSOLE_URL', plugin_dir_url(__FILE__));

function qn_admin_console_document_uploads_enabled()
{
    if (defined('QN_ADMIN_CONSOLE_DOCUMENT_UPLOADS_ENABLED')) {
        return (bool) QN_ADMIN_CONSOLE_DOCUMENT_UPLOADS_ENABLED;
    }

    return true;
}

require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-db.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-activator.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-org-setup-data.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-users.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-permissions.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-organizations.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-health-systems.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-branding.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-email.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-invitations.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-questionnaire.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-data-hub-integration.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-onboarding.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-scout.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-auth.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-router.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-audit-log.php';
require_once QN_ADMIN_CONSOLE_DIR . 'includes/class-qn-rest-api.php';

register_activation_hook(__FILE__, array('QN_Activator', 'activate'));

add_action('plugins_loaded', 'qn_admin_console_boot');

function qn_admin_console_boot()
{
    QN_Activator::maybe_upgrade();
    QN_Auth::init();
    QN_Router::init();
    QN_REST_API::init();
    add_filter('gv_onboarding_completion_redirect_url', array('QN_Invitations', 'onboarding_completion_redirect'), 20, 2);
    add_action('gv_onboarding_user_reset', array('QN_Invitations', 'handle_grapevine_onboarding_reset'), 10, 1);
}
