<?php
if (!defined('ABSPATH')) {
    exit;
}

$email = $invitation ? $invitation['email'] : '';
$full_name = $invitation && !empty($invitation['full_name']) ? $invitation['full_name'] : '';
$brand = QN_Branding::get_default_brand();
$brand_logo_url = !empty($brand['logo_url']) ? esc_url($brand['logo_url']) : '';
$site_name = get_bloginfo('name') ? wp_strip_all_tags(get_bloginfo('name')) : __('QualiNav', 'qualinav-admin-console');
$organization_name = $invitation && !empty($invitation['organization_name']) ? $invitation['organization_name'] : __('Your hospital workspace', 'qualinav-admin-console');
$role_label = $invitation && !empty($invitation['qualinav_role']) ? QN_Users::role_label($invitation['qualinav_role']) : __('QualiNav user', 'qualinav-admin-console');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Accept QualiNav Invitation', 'qualinav-admin-console'); ?></title>
    <?php QN_Branding::output_css_variables(); ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('qualinav-console-page qn-accept-invite-page'); ?>>
    <main class="qn-auth-page">
        <section class="qn-auth-panel">
            <div class="qn-auth-hero" aria-label="<?php esc_attr_e('QualiNav invitation', 'qualinav-admin-console'); ?>">
                <div class="qn-auth-logo">
                    <?php if ($brand_logo_url) : ?>
                        <img src="<?php echo $brand_logo_url; ?>" alt="<?php echo esc_attr($site_name); ?>">
                    <?php else : ?>
                        <span><?php echo esc_html($site_name); ?></span>
                    <?php endif; ?>
                </div>
                <span class="qn-auth-kicker"><?php esc_html_e('QualiNav Invitation', 'qualinav-admin-console'); ?></span>
            </div>

            <div class="qn-auth-content">
                <?php if ($invite_error) : ?>
                    <p class="qn-eyebrow"><?php esc_html_e('Invitation unavailable', 'qualinav-admin-console'); ?></p>
                    <h1><?php esc_html_e('We could not open this invitation.', 'qualinav-admin-console'); ?></h1>
                    <p class="qn-auth-copy"><?php esc_html_e('For security, invitation links can expire or be revoked. Ask your QualiNav administrator to send a fresh invitation if needed.', 'qualinav-admin-console'); ?></p>
                    <p class="qn-form-message qn-form-error"><?php echo esc_html($invite_error); ?></p>
                <?php else : ?>
                    <p class="qn-eyebrow"><?php esc_html_e('Accept Invitation', 'qualinav-admin-console'); ?></p>
                    <h1><?php esc_html_e('Set up your QualiNav account.', 'qualinav-admin-console'); ?></h1>
                    <p class="qn-auth-copy"><?php esc_html_e('Create your password to access the secure QualiNav console for your hospital workspace.', 'qualinav-admin-console'); ?></p>

                    <div class="qn-invite-summary">
                        <div>
                            <span><?php esc_html_e('Email', 'qualinav-admin-console'); ?></span>
                            <strong><?php echo esc_html($email); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Workspace', 'qualinav-admin-console'); ?></span>
                            <strong><?php echo esc_html($organization_name); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Role', 'qualinav-admin-console'); ?></span>
                            <strong><?php echo esc_html($role_label); ?></strong>
                        </div>
                    </div>

                    <form method="post" class="qn-form qn-auth-form">
                        <?php wp_nonce_field('qn_accept_invite', 'qn_accept_invite_nonce'); ?>
                        <label>
                            <span><?php esc_html_e('Full name', 'qualinav-admin-console'); ?></span>
                            <input type="text" name="display_name" value="<?php echo esc_attr($full_name); ?>" autocomplete="name">
                        </label>
                        <label>
                            <span><?php esc_html_e('Password', 'qualinav-admin-console'); ?></span>
                            <span class="qn-password-field">
                                <input type="password" name="password" minlength="10" autocomplete="new-password" required>
                                <button class="qn-password-toggle" type="button" data-toggle-password aria-label="<?php esc_attr_e('Show password', 'qualinav-admin-console'); ?>" aria-pressed="false">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                </button>
                            </span>
                        </label>
                        <label>
                            <span><?php esc_html_e('Confirm password', 'qualinav-admin-console'); ?></span>
                            <span class="qn-password-field">
                                <input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required>
                                <button class="qn-password-toggle" type="button" data-toggle-password aria-label="<?php esc_attr_e('Show password', 'qualinav-admin-console'); ?>" aria-pressed="false">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                </button>
                            </span>
                        </label>
                        <p class="qn-password-note"><?php esc_html_e('Use at least 10 characters. Do not reuse a password from another clinical or hospital system.', 'qualinav-admin-console'); ?></p>
                        <div class="qn-form-actions">
                            <button class="qn-button qn-button-primary" type="submit"><?php esc_html_e('Accept Invitation', 'qualinav-admin-console'); ?></button>
                        </div>
                        <?php if ($accept_error) : ?>
                            <p class="qn-form-message qn-form-error"><?php echo esc_html($accept_error); ?></p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>
