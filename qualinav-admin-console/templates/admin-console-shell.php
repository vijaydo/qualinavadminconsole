<?php
if (!defined('ABSPATH')) {
    exit;
}

$display_name = $current_user ? $current_user->display_name : '';
$role = $current_user ? $current_user->qualinav_role : '';
$role_label = $role ? QN_Users::role_label($role) : '';
$admin_context_label = $role === 'qualinav_super_admin' ? __('Super Admin Console', 'qualinav-admin-console') : __('Admin Console', 'qualinav-admin-console');
$brand = QN_Branding::get_default_brand();
$brand_logo_url = !empty($brand['logo_url']) ? esc_url($brand['logo_url']) : '';
$initial_admin_data = array(
    'hospitals' => QN_Organizations::get_hospitals(array('limit' => 100)),
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('QualiNav Admin Console', 'qualinav-admin-console'); ?></title>
    <?php wp_head(); ?>
    <?php QN_Branding::output_css_variables(); ?>
</head>
<body <?php body_class('qualinav-console-page qn-admin-console-page'); ?>>
    <div class="qn-app-shell">
        <aside class="qn-sidebar">
            <div class="qn-sidebar-head">
                <div class="qn-brand<?php echo $brand_logo_url ? ' qn-brand-has-logo' : ''; ?>">
                    <span class="qn-brand-mark">
                        <?php if ($brand_logo_url) : ?>
                            <img src="<?php echo $brand_logo_url; ?>" alt="<?php esc_attr_e('QualiNav logo', 'qualinav-admin-console'); ?>">
                        <?php else : ?>
                            Q
                        <?php endif; ?>
                    </span>
                    <span class="qn-brand-text"><?php esc_html_e('QualiNav Admin', 'qualinav-admin-console'); ?></span>
                </div>
                <button class="qn-sidebar-toggle" type="button" data-sidebar-toggle aria-label="<?php esc_attr_e('Keep sidebar expanded', 'qualinav-admin-console'); ?>" aria-pressed="false">
                    <span class="dashicons dashicons-menu-alt"></span>
                </button>
            </div>
            <a class="qn-home-link" href="<?php echo esc_url(home_url('/')); ?>">
                <span class="dashicons dashicons-admin-home"></span><?php esc_html_e('Site Home', 'qualinav-admin-console'); ?>
            </a>
            <nav class="qn-nav" aria-label="<?php esc_attr_e('Admin console navigation', 'qualinav-admin-console'); ?>">
                <a class="qn-nav-item qn-nav-item-active" href="#overview" data-section-target="overview" data-title="<?php esc_attr_e('Overview', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Internal Administration', 'qualinav-admin-console'); ?>">
                    <span class="dashicons dashicons-dashboard"></span><?php esc_html_e('Overview', 'qualinav-admin-console'); ?>
                </a>
                <a class="qn-nav-item" href="#hospitals" data-section-target="hospitals" data-title="<?php esc_attr_e('Hospitals', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Hospital Management', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-building"></span><?php esc_html_e('Hospitals', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#health-systems" data-section-target="health-systems" data-title="<?php esc_attr_e('Health Systems', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Systems & Networks', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-networking"></span><?php esc_html_e('Health Systems', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#users" data-section-target="users" data-title="<?php esc_attr_e('Users', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('User Management', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-groups"></span><?php esc_html_e('Users', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#invitations" data-section-target="invitations" data-title="<?php esc_attr_e('Invitations', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Invite Tracking', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-email-alt"></span><?php esc_html_e('Invitations', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#brand" data-section-target="brand" data-title="<?php esc_attr_e('Brand Settings', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Theme Preview', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-admin-customizer"></span><?php esc_html_e('Brand Settings', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#audit" data-section-target="audit" data-title="<?php esc_attr_e('Audit Logs', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Activity Review', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-visibility"></span><?php esc_html_e('Audit Logs', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#system-check" data-section-target="system-check" data-title="<?php esc_attr_e('System Check', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Local Diagnostics', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-admin-tools"></span><?php esc_html_e('System Check', 'qualinav-admin-console'); ?></a>
            </nav>
        </aside>

        <main class="qn-main">
            <header class="qn-topbar">
                <div>
                    <p class="qn-eyebrow" id="qn-page-eyebrow"><?php esc_html_e('Internal Administration', 'qualinav-admin-console'); ?></p>
                    <h1 id="qn-page-title"><?php esc_html_e('Overview', 'qualinav-admin-console'); ?></h1>
                </div>
                <div class="qn-user-pill">
                    <span class="qn-env-pill"><?php echo esc_html($admin_context_label); ?></span>
                    <span><?php echo esc_html($display_name); ?></span>
                    <small><?php echo esc_html($role_label); ?></small>
                </div>
            </header>

            <section class="qn-section qn-section-active" id="overview" data-section="overview" aria-label="<?php esc_attr_e('Admin overview', 'qualinav-admin-console'); ?>">
            <div class="qn-metric-grid" id="qn-admin-metrics" aria-label="<?php esc_attr_e('Admin dashboard metrics', 'qualinav-admin-console'); ?>">
                <article class="qn-card qn-metric-card"><span class="dashicons dashicons-building"></span><p class="qn-label"><?php esc_html_e('Total Hospitals', 'qualinav-admin-console'); ?></p><strong data-metric="total_hospitals">-</strong><small><?php esc_html_e('Configured facilities', 'qualinav-admin-console'); ?></small></article>
                <article class="qn-card qn-metric-card"><span class="dashicons dashicons-yes-alt"></span><p class="qn-label"><?php esc_html_e('Active Hospitals', 'qualinav-admin-console'); ?></p><strong data-metric="active_hospitals">-</strong><small><?php esc_html_e('Currently enabled', 'qualinav-admin-console'); ?></small></article>
                <article class="qn-card qn-metric-card"><span class="dashicons dashicons-networking"></span><p class="qn-label"><?php esc_html_e('Health Systems', 'qualinav-admin-console'); ?></p><strong id="qn-metric-health-systems">-</strong><small><?php esc_html_e('Systems and networks', 'qualinav-admin-console'); ?></small></article>
                <article class="qn-card qn-metric-card"><span class="dashicons dashicons-groups"></span><p class="qn-label"><?php esc_html_e('Quality Directors', 'qualinav-admin-console'); ?></p><strong data-metric="total_quality_directors">-</strong><small><?php esc_html_e('Hospital leaders', 'qualinav-admin-console'); ?></small></article>
                <article class="qn-card qn-metric-card"><span class="dashicons dashicons-email-alt"></span><p class="qn-label"><?php esc_html_e('Pending Invitations', 'qualinav-admin-console'); ?></p><strong id="qn-metric-pending-invites">-</strong><small><?php esc_html_e('Awaiting acceptance', 'qualinav-admin-console'); ?></small></article>
                <article class="qn-card qn-metric-card"><span class="dashicons dashicons-clipboard"></span><p class="qn-label"><?php esc_html_e('Setup Pending', 'qualinav-admin-console'); ?></p><strong data-metric="onboarding_pending">-</strong><small><?php esc_html_e('Setup still open', 'qualinav-admin-console'); ?></small></article>
            </div>
            </section>

            <section class="qn-panel qn-system-check-panel qn-section" id="system-check" data-section="system-check" hidden>
                <div class="qn-panel-header">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('Local Diagnostics', 'qualinav-admin-console'); ?></p>
                        <h2><?php esc_html_e('System Check', 'qualinav-admin-console'); ?></h2>
                    </div>
                    <span class="qn-status-pill qn-status-active"><?php esc_html_e('Admin Only', 'qualinav-admin-console'); ?></span>
                </div>
                <div class="qn-system-check-grid" id="qn-system-check-grid">
                    <article><span><?php esc_html_e('Plugin Version', 'qualinav-admin-console'); ?></span><strong data-system-check="plugin_version">-</strong></article>
                    <article><span><?php esc_html_e('Environment', 'qualinav-admin-console'); ?></span><strong data-system-check="environment">-</strong></article>
                    <article><span><?php esc_html_e('DB Prefix', 'qualinav-admin-console'); ?></span><strong data-system-check="db_prefix">-</strong></article>
                    <article><span><?php esc_html_e('Current Role', 'qualinav-admin-console'); ?></span><strong data-system-check="current_role">-</strong></article>
                    <article><span><?php esc_html_e('User Columns', 'qualinav-admin-console'); ?></span><strong data-system-check="user_columns">-</strong></article>
                    <article><span><?php esc_html_e('Plugin Tables', 'qualinav-admin-console'); ?></span><strong data-system-check="plugin_tables">-</strong></article>
                    <article><span><?php esc_html_e('Org Classification', 'qualinav-admin-console'); ?></span><strong data-system-check="org_columns">-</strong></article>
                    <article><span><?php esc_html_e('Questionnaire', 'qualinav-admin-console'); ?></span><strong data-system-check="questionnaire">-</strong></article>
                    <article><span><?php esc_html_e('GrapevineAI Bridge', 'qualinav-admin-console'); ?></span><strong data-system-check="scout_bridge">-</strong></article>
                    <article><span><?php esc_html_e('Scout Runs', 'qualinav-admin-console'); ?></span><strong data-system-check="scout_runs">-</strong></article>
                </div>
            </section>

            <section class="qn-panel qn-section" id="hospitals" data-section="hospitals" hidden>
                <div class="qn-section-toolbar">
                    <div>
                        <p><?php esc_html_e('Manage facility records, classifications, onboarding visibility, and Quality Director assignment.', 'qualinav-admin-console'); ?></p>
                    </div>
                    <button class="qn-button qn-button-primary" type="button" id="qn-create-hospital-button"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Create Hospital', 'qualinav-admin-console'); ?></button>
                </div>
                <div class="qn-filter-context" id="qn-hospital-filter-context" hidden>
                    <div class="qn-filter-main">
                        <button class="qn-filter-back" type="button" id="qn-return-health-systems"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Health Systems', 'qualinav-admin-console'); ?></button>
                        <span class="qn-filter-separator">/</span>
                        <span class="dashicons dashicons-networking"></span>
                        <strong id="qn-hospital-filter-title"><?php esc_html_e('Filtered hospitals', 'qualinav-admin-console'); ?></strong>
                        <small id="qn-hospital-filter-note"></small>
                    </div>
                    <div class="qn-filter-actions">
                        <button class="qn-button qn-button-small" type="button" id="qn-assign-system-hospitals"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Assign Hospitals', 'qualinav-admin-console'); ?></button>
                        <button class="qn-filter-link" type="button" id="qn-clear-hospital-filter"><span class="dashicons dashicons-list-view"></span><?php esc_html_e('Show all hospitals', 'qualinav-admin-console'); ?></button>
                    </div>
                </div>

                <div class="qn-list-tools">
                    <label class="qn-search-field" for="qn-hospital-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-hospital-search" placeholder="<?php esc_attr_e('Search hospitals, systems, states, or directors', 'qualinav-admin-console'); ?>">
                    </label>
                    <div class="qn-pagination" aria-label="<?php esc_attr_e('Hospitals pagination', 'qualinav-admin-console'); ?>">
                        <span id="qn-hospital-pagination-summary"><?php esc_html_e('Loading hospitals...', 'qualinav-admin-console'); ?></span>
                        <button class="qn-button qn-button-small" type="button" id="qn-hospital-prev-page"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Previous', 'qualinav-admin-console'); ?></button>
                        <button class="qn-button qn-button-small" type="button" id="qn-hospital-next-page"><?php esc_html_e('Next', 'qualinav-admin-console'); ?><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                    </div>
                </div>

                <div class="qn-table-wrap">
                    <table class="qn-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Hospital', 'qualinav-admin-console'); ?></th>
                                <th><?php esc_html_e('Classification', 'qualinav-admin-console'); ?></th>
                                <th><?php esc_html_e('State', 'qualinav-admin-console'); ?></th>
                                <th><?php esc_html_e('Status & Setup', 'qualinav-admin-console'); ?></th>
                                <th><?php esc_html_e('Primary Quality Director', 'qualinav-admin-console'); ?></th>
                                <th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="qn-hospitals-table-body">
                            <tr><td colspan="6"><?php esc_html_e('Loading hospitals...', 'qualinav-admin-console'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="qn-panel qn-section" id="health-systems" data-section="health-systems" hidden>
                <div class="qn-section-toolbar">
                    <div>
                        <p><?php esc_html_e('Group hospitals by health system or network. Independent hospitals can remain unassigned.', 'qualinav-admin-console'); ?></p>
                    </div>
                    <button class="qn-button qn-button-primary" type="button" id="qn-create-system-button"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Create System', 'qualinav-admin-console'); ?></button>
                </div>
                <div class="qn-list-tools">
                    <label class="qn-search-field" for="qn-system-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-system-search" placeholder="<?php esc_attr_e('Search systems or states', 'qualinav-admin-console'); ?>">
                    </label>
                    <div class="qn-filter-group">
                        <select id="qn-system-filter-status" aria-label="<?php esc_attr_e('Filter systems by status', 'qualinav-admin-console'); ?>">
                            <option value=""><?php esc_html_e('All statuses', 'qualinav-admin-console'); ?></option>
                            <option value="active"><?php esc_html_e('Active', 'qualinav-admin-console'); ?></option>
                            <option value="inactive"><?php esc_html_e('Inactive', 'qualinav-admin-console'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="qn-table-wrap">
                    <table class="qn-table">
                        <thead><tr><th><?php esc_html_e('System Name', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Headquarters State', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Hospitals', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('QD Coverage', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Active', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th></tr></thead>
                        <tbody id="qn-health-systems-table-body"><tr><td colspan="6"><?php esc_html_e('Loading systems...', 'qualinav-admin-console'); ?></td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="qn-panel qn-brand-preview-panel qn-section" id="brand" data-section="brand" hidden>
                <div>
                    <p class="qn-eyebrow"><?php esc_html_e('Brand Preview', 'qualinav-admin-console'); ?></p>
                    <h2 id="qn-brand-preview-title"><?php esc_html_e('Default QualiNav Theme', 'qualinav-admin-console'); ?></h2>
                    <p class="qn-section-copy"><?php esc_html_e('Preview how the active brand colors, logo, cards, buttons, and status styles appear inside the QualiNav console.', 'qualinav-admin-console'); ?></p>
                </div>
                <div class="qn-brand-preview" id="qn-brand-preview">
                    <div class="qn-empty-state"><?php esc_html_e('Loading brand preview...', 'qualinav-admin-console'); ?></div>
                </div>
            </section>

            <section class="qn-panel qn-section" id="users" data-section="users" hidden>
                <div class="qn-panel-header">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('User Management', 'qualinav-admin-console'); ?></p>
                        <h2><?php esc_html_e('QualiNav Users', 'qualinav-admin-console'); ?></h2>
                    </div>
                    <button class="qn-button qn-button-primary" type="button" id="qn-admin-invite-user-button"><?php esc_html_e('Invite User', 'qualinav-admin-console'); ?></button>
                </div>
                <div class="qn-list-tools">
                    <label class="qn-search-field" for="qn-user-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-user-search" placeholder="<?php esc_attr_e('Search users, email, hospitals, roles', 'qualinav-admin-console'); ?>">
                    </label>
                    <div class="qn-filter-group">
                        <select id="qn-user-filter-organization" class="qn-searchable-select-source" aria-label="<?php esc_attr_e('Filter users by hospital', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All hospitals', 'qualinav-admin-console'); ?></option></select>
                        <select id="qn-user-filter-role" aria-label="<?php esc_attr_e('Filter users by role', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All roles', 'qualinav-admin-console'); ?></option></select>
                        <select id="qn-user-filter-status" aria-label="<?php esc_attr_e('Filter users by status', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All statuses', 'qualinav-admin-console'); ?></option></select>
                    </div>
                </div>
                <div class="qn-table-wrap">
                    <table class="qn-table">
                        <thead><tr><th><?php esc_html_e('User', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Hospitals', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('State', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Role', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Status', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th></tr></thead>
                        <tbody id="qn-admin-users-table-body"><tr><td colspan="6"><?php esc_html_e('Loading users...', 'qualinav-admin-console'); ?></td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="qn-panel qn-section" id="invitations" data-section="invitations" hidden>
                <div class="qn-panel-header">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('Invitations', 'qualinav-admin-console'); ?></p>
                        <h2><?php esc_html_e('Pending and Historical Invites', 'qualinav-admin-console'); ?></h2>
                    </div>
                </div>
                <div class="qn-list-tools">
                    <label class="qn-search-field" for="qn-admin-invitation-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-admin-invitation-search" placeholder="<?php esc_attr_e('Search invitees, emails, roles, hospitals', 'qualinav-admin-console'); ?>">
                    </label>
                    <div class="qn-filter-group">
                        <select id="qn-admin-invitation-filter-role" aria-label="<?php esc_attr_e('Filter invitations by role', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All roles', 'qualinav-admin-console'); ?></option></select>
                        <select id="qn-admin-invitation-filter-status" aria-label="<?php esc_attr_e('Filter invitations by status', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All statuses', 'qualinav-admin-console'); ?></option></select>
                    </div>
                </div>
                <div class="qn-table-wrap">
                    <table class="qn-table">
                        <thead><tr><th><?php esc_html_e('Invitee', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Role', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Status', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Expires', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Invited By', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th></tr></thead>
                        <tbody id="qn-admin-invitations-table-body"><tr><td colspan="6"><?php esc_html_e('Loading invitations...', 'qualinav-admin-console'); ?></td></tr></tbody>
                    </table>
                </div>
            </section>
            <section class="qn-panel qn-section" id="audit" data-section="audit" hidden>
                <div class="qn-empty-state">
                    <span class="dashicons dashicons-visibility"></span>
                    <h3><?php esc_html_e('Audit log viewer is coming next.', 'qualinav-admin-console'); ?></h3>
                    <p><?php esc_html_e('Audit events are already being written. A filtered review experience will be added in a later pass.', 'qualinav-admin-console'); ?></p>
                </div>
            </section>
        </main>
    </div>

    <div class="qn-modal" id="qn-hospital-modal" hidden>
        <div class="qn-modal-panel" role="dialog" aria-modal="true" aria-labelledby="qn-hospital-modal-title">
            <div class="qn-panel-header">
                <h2 id="qn-hospital-modal-title"><?php esc_html_e('Create Hospital', 'qualinav-admin-console'); ?></h2>
                <button class="qn-icon-button" type="button" id="qn-hospital-modal-close" aria-label="<?php esc_attr_e('Close', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="qn-hospital-form" class="qn-form">
                <input type="hidden" name="id" id="qn-hospital-id">
                <label>
                    <span><?php esc_html_e('Hospital Name', 'qualinav-admin-console'); ?></span>
                    <input type="text" name="organization_name" id="qn-hospital-name" required>
                </label>
                <label>
                    <span><?php esc_html_e('City', 'qualinav-admin-console'); ?></span>
                    <input type="text" name="city" id="qn-hospital-city">
                </label>
                <label>
                    <span><?php esc_html_e('ZIP', 'qualinav-admin-console'); ?></span>
                    <input type="text" name="zip" id="qn-hospital-zip">
                </label>
                <label>
                    <span><?php esc_html_e('State', 'qualinav-admin-console'); ?></span>
                    <select name="state_id" id="qn-hospital-state" class="qn-searchable-select-source"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Licensed Beds', 'qualinav-admin-console'); ?></span>
                    <input type="number" min="0" step="1" inputmode="numeric" name="beds" id="qn-hospital-beds">
                </label>
                <label>
                    <span><?php esc_html_e('Hospital System', 'qualinav-admin-console'); ?></span>
                    <select name="parent_system_id" id="qn-hospital-system" class="qn-searchable-select-source"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Hospital Type', 'qualinav-admin-console'); ?></span>
                    <select name="hospital_type" id="qn-hospital-type"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Service Model', 'qualinav-admin-console'); ?></span>
                    <select name="service_model" id="qn-hospital-service-model"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Payment Model', 'qualinav-admin-console'); ?></span>
                    <select name="payment_model" id="qn-hospital-payment-model"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Status', 'qualinav-admin-console'); ?></span>
                    <select name="status" id="qn-hospital-status">
                        <option value="active"><?php esc_html_e('Active', 'qualinav-admin-console'); ?></option>
                        <option value="inactive"><?php esc_html_e('Inactive', 'qualinav-admin-console'); ?></option>
                        <option value="disabled"><?php esc_html_e('Disabled', 'qualinav-admin-console'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Timezone', 'qualinav-admin-console'); ?></span>
                    <input type="text" name="timezone" id="qn-hospital-timezone">
                </label>
                <label>
                    <span><?php esc_html_e('CCN', 'qualinav-admin-console'); ?></span>
                    <input type="text" name="ccn" id="qn-hospital-ccn">
                </label>
                <div class="qn-form-actions">
                    <button class="qn-button" type="button" id="qn-hospital-cancel"><?php esc_html_e('Cancel', 'qualinav-admin-console'); ?></button>
                    <button class="qn-button qn-button-primary" type="submit"><?php esc_html_e('Save Hospital', 'qualinav-admin-console'); ?></button>
                </div>
                <p class="qn-form-message" id="qn-hospital-form-message"></p>
            </form>
        </div>
    </div>

    <div class="qn-modal" id="qn-system-modal" hidden>
        <div class="qn-modal-panel" role="dialog" aria-modal="true" aria-labelledby="qn-system-modal-title">
            <div class="qn-panel-header">
                <h2 id="qn-system-modal-title"><?php esc_html_e('Create System', 'qualinav-admin-console'); ?></h2>
                <button class="qn-icon-button" type="button" id="qn-system-modal-close" aria-label="<?php esc_attr_e('Close', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="qn-system-form" class="qn-form qn-system-form">
                <input type="hidden" name="id" id="qn-system-id">
                <label><span><?php esc_html_e('System Name', 'qualinav-admin-console'); ?></span><input type="text" name="name" id="qn-system-name" required></label>
                <label><span><?php esc_html_e('Headquarters State', 'qualinav-admin-console'); ?></span><select name="headquarters_state_id" id="qn-system-state" class="qn-searchable-select-source"></select></label>
                <label><span><?php esc_html_e('Active', 'qualinav-admin-console'); ?></span><select name="is_active" id="qn-system-active"><option value="1"><?php esc_html_e('Active', 'qualinav-admin-console'); ?></option><option value="0"><?php esc_html_e('Inactive', 'qualinav-admin-console'); ?></option></select></label>
                <label class="qn-system-description-field"><span><?php esc_html_e('Description', 'qualinav-admin-console'); ?></span><textarea name="description" id="qn-system-description"></textarea></label>
                <div class="qn-form-actions"><button class="qn-button" type="button" id="qn-system-cancel"><?php esc_html_e('Cancel', 'qualinav-admin-console'); ?></button><button class="qn-button qn-button-primary" type="submit"><?php esc_html_e('Save System', 'qualinav-admin-console'); ?></button></div>
                <p class="qn-form-message" id="qn-system-form-message"></p>
            </form>
        </div>
    </div>

    <div class="qn-modal" id="qn-system-hospitals-modal" hidden>
        <div class="qn-modal-panel qn-system-hospitals-modal-panel" role="dialog" aria-modal="true" aria-labelledby="qn-system-hospitals-modal-title">
            <div class="qn-panel-header">
                <div>
                    <p class="qn-eyebrow"><?php esc_html_e('Health System Assignment', 'qualinav-admin-console'); ?></p>
                    <h2 id="qn-system-hospitals-modal-title"><?php esc_html_e('Assign Hospitals', 'qualinav-admin-console'); ?></h2>
                </div>
                <button class="qn-icon-button" type="button" id="qn-system-hospitals-modal-close" aria-label="<?php esc_attr_e('Close', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="qn-system-hospitals-form" class="qn-system-hospitals-form">
                <input type="hidden" id="qn-system-hospitals-system-id" name="system_id">
                <div class="qn-system-hospitals-tools">
                    <label class="qn-search-field" for="qn-system-hospitals-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-system-hospitals-search" placeholder="<?php esc_attr_e('Search hospitals, states, or current systems', 'qualinav-admin-console'); ?>">
                    </label>
                    <span class="qn-status-pill qn-status-neutral" id="qn-system-hospitals-selected-count"><?php esc_html_e('0 selected', 'qualinav-admin-console'); ?></span>
                </div>
                <div class="qn-system-hospitals-list" id="qn-system-hospitals-list"></div>
                <div class="qn-form-actions">
                    <button class="qn-button" type="button" id="qn-system-hospitals-cancel"><?php esc_html_e('Cancel', 'qualinav-admin-console'); ?></button>
                    <button class="qn-button qn-button-primary" type="submit"><?php esc_html_e('Save Assignments', 'qualinav-admin-console'); ?></button>
                </div>
                <p class="qn-form-message" id="qn-system-hospitals-message"></p>
            </form>
        </div>
    </div>

    <div class="qn-modal" id="qn-invite-modal" hidden>
        <div class="qn-modal-panel" role="dialog" aria-modal="true" aria-labelledby="qn-invite-modal-title">
            <div class="qn-panel-header">
                <h2 id="qn-invite-modal-title"><?php esc_html_e('Invite User', 'qualinav-admin-console'); ?></h2>
                <button class="qn-icon-button" type="button" data-close-invite aria-label="<?php esc_attr_e('Close', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="qn-invite-form" class="qn-form">
                <input type="hidden" name="context" value="admin">
                <div class="qn-invite-fixed-context qn-form-wide" id="qn-invite-fixed-context" hidden>
                    <div id="qn-invite-fixed-hospital-row" hidden>
                        <span><?php esc_html_e('Hospital workspace', 'qualinav-admin-console'); ?></span>
                        <strong id="qn-invite-fixed-hospital"><?php esc_html_e('Selected hospital', 'qualinav-admin-console'); ?></strong>
                    </div>
                    <div id="qn-invite-fixed-role-row" hidden>
                        <span><?php esc_html_e('Access role', 'qualinav-admin-console'); ?></span>
                        <strong id="qn-invite-fixed-role"><?php esc_html_e('Selected role', 'qualinav-admin-console'); ?></strong>
                    </div>
                </div>
                <label><span><?php esc_html_e('Full name', 'qualinav-admin-console'); ?></span><input type="text" name="full_name"></label>
                <label><span><?php esc_html_e('Email', 'qualinav-admin-console'); ?></span><input type="email" name="email" required></label>
                <label id="qn-invite-organization-field"><span><?php esc_html_e('Hospital', 'qualinav-admin-console'); ?></span><select name="organization_id" id="qn-invite-organization" class="qn-searchable-select-source"></select></label>
                <label id="qn-invite-role-field"><span><?php esc_html_e('Role', 'qualinav-admin-console'); ?></span><select name="qualinav_role" id="qn-invite-role"></select></label>
                <div class="qn-form-actions"><button class="qn-button" type="button" data-close-invite><?php esc_html_e('Cancel', 'qualinav-admin-console'); ?></button><button class="qn-button qn-button-primary" type="submit"><?php esc_html_e('Send Invite', 'qualinav-admin-console'); ?></button></div>
                <p class="qn-form-message" id="qn-invite-form-message"></p>
            </form>
        </div>
    </div>
    <script type="application/json" id="qn-initial-admin-data"><?php echo wp_json_encode($initial_admin_data); ?></script>
    <?php wp_footer(); ?>
</body>
</html>
