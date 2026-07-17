<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = isset($current_user) ? $current_user : QN_Users::get_current_user_row();
$brand_organization_id = $current_user && $current_user->organization_id !== null ? absint($current_user->organization_id) : null;
$brand = $brand_organization_id ? QN_Branding::get_brand_for_organization($brand_organization_id) : QN_Branding::get_default_brand();
$is_myorg_embedded_shell = isset($console_config) && !empty($console_config['isEmbeddedShell']) && isset($console_config['shellMode']) && $console_config['shellMode'] === 'my-org';
$body_classes = 'qualinav-console-page qn-hospital-console-page' . ($is_myorg_embedded_shell ? ' qn-myorg-embedded-console' : '');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('QualiNav Console', 'qualinav-admin-console'); ?></title>
    <?php wp_head(); ?>
    <?php QN_Branding::output_css_variables($brand_organization_id); ?>
</head>
<body <?php body_class($body_classes); ?>>
    <?php include QN_ADMIN_CONSOLE_DIR . 'templates/partials/hospital-console-content.php'; ?>
    <?php wp_footer(); ?>
</body>
</html>
