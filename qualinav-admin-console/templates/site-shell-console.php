<?php
/**
 * Template Name: QualiNav Organization Setup
 * Description: QualiNav hospital console rendered inside the Grapevine site shell.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(home_url('/organization-setup/')));
    exit;
}

$current_user = isset($current_user) ? $current_user : QN_Users::get_current_user_row();
$brand_organization_id = $current_user && $current_user->organization_id !== null ? absint($current_user->organization_id) : null;
$console_config = QN_Router::site_console_config();

get_header();
QN_Branding::output_css_variables($brand_organization_id);
?>
<style id="qn-site-shell-flush-header">
    body.qn-site-shell-console .gv-main,
    body.qn-site-shell-console .gv-content-wrap,
    body.qn-site-shell-console .qn-site-shell-console-wrap {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
</style>

<div class="qn-site-shell-console-wrap">
    <?php include QN_ADMIN_CONSOLE_DIR . 'templates/partials/hospital-console-content.php'; ?>
</div>

<?php get_footer(); ?>
