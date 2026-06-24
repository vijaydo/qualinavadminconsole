<?php
if (!defined('ABSPATH')) {
    exit;
}

$display_name = $current_user ? $current_user->display_name : '';
$role = $current_user ? $current_user->qualinav_role : '';
$role_label = $role ? QN_Users::role_label($role) : '';
$brand_organization_id = $current_user && $current_user->organization_id !== null ? absint($current_user->organization_id) : null;
$brand = $brand_organization_id ? QN_Branding::get_brand_for_organization($brand_organization_id) : QN_Branding::get_default_brand();
$brand_logo_url = !empty($brand['logo_url']) ? esc_url($brand['logo_url']) : '';
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
<body <?php body_class('qualinav-console-page qn-hospital-console-page'); ?>>
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
                    <span class="qn-brand-text"><?php esc_html_e('QualiNav', 'qualinav-admin-console'); ?></span>
                </div>
                <button class="qn-sidebar-toggle" type="button" data-sidebar-toggle aria-label="<?php esc_attr_e('Keep sidebar expanded', 'qualinav-admin-console'); ?>" aria-pressed="false">
                    <span class="dashicons dashicons-menu-alt"></span>
                </button>
            </div>
            <a class="qn-home-link" href="<?php echo esc_url(home_url('/')); ?>">
                <span class="dashicons dashicons-admin-home"></span><?php esc_html_e('Site Home', 'qualinav-admin-console'); ?>
            </a>
            <nav class="qn-nav" aria-label="<?php esc_attr_e('Hospital console navigation', 'qualinav-admin-console'); ?>">
                <a class="qn-nav-item qn-nav-item-active" href="#dashboard" data-section-target="dashboard" data-title="<?php esc_attr_e('Dashboard', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Hospital Console', 'qualinav-admin-console'); ?>">
                    <span class="dashicons dashicons-dashboard"></span><?php esc_html_e('Dashboard', 'qualinav-admin-console'); ?>
                </a>
                <a class="qn-nav-item" href="#day-0-setup" data-section-target="day-0-setup" data-title="<?php esc_attr_e('Hospital Setup', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Hospital Setup', 'qualinav-admin-console'); ?>">
                    <span class="dashicons dashicons-clipboard"></span><?php esc_html_e('Hospital Setup', 'qualinav-admin-console'); ?>
                </a>
                <a class="qn-nav-item" href="#scout-preview" data-section-target="scout-preview" data-title="<?php esc_attr_e('Scout Setup Preview', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Generated Setup Preview', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e('Scout Setup Preview', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#hospital-users" data-section-target="hospital-users" data-title="<?php esc_attr_e('Hospital Users', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Manage hospital access', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-groups"></span><?php esc_html_e('Users', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#reporting" data-section-target="reporting" data-title="<?php esc_attr_e('Reporting Schedule', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Planned Workflow Area', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-chart-bar"></span><?php esc_html_e('Reporting', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#committees" data-section-target="committees" data-title="<?php esc_attr_e('Committees', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Planned Workflow Area', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-businessperson"></span><?php esc_html_e('Committees', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#plans" data-section-target="plans" data-title="<?php esc_attr_e('Plans & Policies', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Planned Workflow Area', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-media-document"></span><?php esc_html_e('Plans & Policies', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#clinical-monitoring" data-section-target="clinical" data-title="<?php esc_attr_e('Clinical Monitoring', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Planned Workflow Area', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-heart"></span><?php esc_html_e('Clinical Monitoring', 'qualinav-admin-console'); ?></a>
                <a class="qn-nav-item" href="#settings" data-section-target="settings" data-title="<?php esc_attr_e('Hospital Settings', 'qualinav-admin-console'); ?>" data-subtitle="<?php esc_attr_e('Workspace Preferences', 'qualinav-admin-console'); ?>"><span class="dashicons dashicons-admin-settings"></span><?php esc_html_e('Settings', 'qualinav-admin-console'); ?></a>
            </nav>
        </aside>

        <main class="qn-main">
            <header class="qn-topbar">
                <div>
                    <p class="qn-eyebrow" id="qn-page-eyebrow"><?php esc_html_e('Hospital Console', 'qualinav-admin-console'); ?></p>
                    <h1 id="qn-page-title"><?php esc_html_e('Dashboard', 'qualinav-admin-console'); ?></h1>
                </div>
                <div class="qn-user-pill">
                    <?php if (in_array($role, array('qualinav_super_admin', 'qualinav_admin'), true)) : ?>
                        <a class="qn-admin-return-link" href="<?php echo esc_url(home_url('/qualinav/admin')); ?>">
                            <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e('Admin Console', 'qualinav-admin-console'); ?>
                        </a>
                    <?php endif; ?>
                    <span id="qn-current-hospital-name" class="qn-current-hospital-name"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></span>
                    <select id="qn-hospital-switcher" class="qn-hospital-switcher qn-searchable-select-source" hidden></select>
                    <span><?php echo esc_html($display_name); ?></span>
                    <small><?php echo esc_html($role_label); ?></small>
                </div>
            </header>

            <section class="qn-section qn-section-active" id="dashboard" data-section="dashboard" aria-label="<?php esc_attr_e('Hospital dashboard', 'qualinav-admin-console'); ?>">
            <div class="qn-dashboard-shell" id="qn-hospital-dashboard">
                <section class="qn-dashboard-hero qn-dashboard-loading" id="qn-dashboard-hero">
                    <div class="qn-dashboard-hero-main">
                        <p class="qn-eyebrow"><?php esc_html_e('Hospital Workspace', 'qualinav-admin-console'); ?></p>
                        <h2 data-dashboard="hospital_name"><span class="qn-skeleton qn-skeleton-title"></span></h2>
                        <p class="qn-dashboard-subtitle" data-dashboard="hospital_subtitle"><span class="qn-skeleton qn-skeleton-line"></span></p>
                        <div class="qn-chip-row qn-dashboard-meta" data-dashboard="hero_chips">
                            <span class="qn-skeleton qn-skeleton-chip"></span>
                            <span class="qn-skeleton qn-skeleton-chip"></span>
                            <span class="qn-skeleton qn-skeleton-chip"></span>
                        </div>
                    </div>
                    <div class="qn-dashboard-hero-side">
                        <span class="qn-status-pill qn-status-active" data-dashboard="role_badge"><?php echo esc_html($role_label ? $role_label : __('Workspace role', 'qualinav-admin-console')); ?></span>
                        <div class="qn-dashboard-progress">
                            <span><?php esc_html_e('Hospital Setup', 'qualinav-admin-console'); ?></span>
                            <strong data-dashboard="setup_percent"><?php esc_html_e('Loading', 'qualinav-admin-console'); ?></strong>
                            <div class="qn-progress-shell"><span data-dashboard="setup_bar"></span></div>
                        </div>
                        <div class="qn-dashboard-scout-status">
                            <span><?php esc_html_e('Scout Preview', 'qualinav-admin-console'); ?></span>
                            <strong data-dashboard="scout_status"><?php esc_html_e('Checking status', 'qualinav-admin-console'); ?></strong>
                        </div>
                    </div>
                </section>

                <section class="qn-panel qn-dashboard-context-card" id="qn-hospital-context-card">
                    <div class="qn-panel-header">
                        <div>
                            <p class="qn-eyebrow"><?php esc_html_e('Current Hospital Context', 'qualinav-admin-console'); ?></p>
                            <h2><?php esc_html_e('Workspace profile', 'qualinav-admin-console'); ?></h2>
                        </div>
                    </div>
                    <div class="qn-dashboard-context-grid">
                        <div><p class="qn-label"><?php esc_html_e('Hospital System', 'qualinav-admin-console'); ?></p><span class="qn-chip" data-context="system"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></span></div>
                        <div><p class="qn-label"><?php esc_html_e('Hospital Type', 'qualinav-admin-console'); ?></p><span class="qn-chip" data-context="type"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></span></div>
                        <div><p class="qn-label"><?php esc_html_e('Service Model', 'qualinav-admin-console'); ?></p><span class="qn-chip" data-context="service"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></span></div>
                        <div><p class="qn-label"><?php esc_html_e('Payment Model', 'qualinav-admin-console'); ?></p><span class="qn-chip" data-context="payment"><?php esc_html_e('Available after setup', 'qualinav-admin-console'); ?></span></div>
                        <div><p class="qn-label"><?php esc_html_e('State', 'qualinav-admin-console'); ?></p><span class="qn-chip" data-context="state"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></span></div>
                        <div><p class="qn-label"><?php esc_html_e('Current Workspace', 'qualinav-admin-console'); ?></p><span class="qn-chip" data-context="workspace"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></span></div>
                    </div>
                </section>

                <section class="qn-workspace-guide-card" id="qn-workspace-guide-card">
                    <div class="qn-workspace-guide-icon"><span class="dashicons dashicons-lightbulb"></span></div>
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('New to this workspace?', 'qualinav-admin-console'); ?></p>
                        <h3><?php esc_html_e('Start with a quick orientation', 'qualinav-admin-console'); ?></h3>
                        <p id="qn-workspace-guide-card-copy"><?php esc_html_e('See how Hospital Setup, Scout, and the workspace modules fit together.', 'qualinav-admin-console'); ?></p>
                    </div>
                    <div class="qn-workspace-guide-actions">
                        <button class="qn-button qn-button-secondary" type="button" id="qn-workspace-guide-button"><?php esc_html_e('Open workspace guide', 'qualinav-admin-console'); ?></button>
                        <button class="qn-button qn-button-primary" type="button" id="qn-workspace-guide-setup-button"><?php esc_html_e('Continue Hospital Setup', 'qualinav-admin-console'); ?></button>
                    </div>
                </section>

                <div class="qn-dashboard-summary-grid" id="qn-dashboard-summary-grid" aria-label="<?php esc_attr_e('Hospital dashboard summary', 'qualinav-admin-console'); ?>"></div>
                <div class="qn-dashboard-module-grid" id="qn-dashboard-module-grid" aria-label="<?php esc_attr_e('Hospital operating system modules', 'qualinav-admin-console'); ?>"></div>
            </div>
            </section>

            <section class="qn-scout-panel qn-section" id="scout-preview" data-section="scout-preview" hidden>
                <div class="qn-scout-admin-banner" id="qn-scout-admin-banner" hidden>
                    <span class="dashicons dashicons-visibility"></span>
                    <strong><?php esc_html_e('Admin Preview Mode', 'qualinav-admin-console'); ?></strong>
                    <span id="qn-scout-admin-preview-text"><?php esc_html_e('Viewing Scout Preview for this hospital.', 'qualinav-admin-console'); ?></span>
                    <a class="qn-button qn-button-small" href="<?php echo esc_url(home_url('/qualinav/admin')); ?>"><?php esc_html_e('Back to Admin Console', 'qualinav-admin-console'); ?></a>
                </div>
                <div class="qn-scout-page-header">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('Scout Setup Preview', 'qualinav-admin-console'); ?></p>
                        <h2><?php esc_html_e('Scout Setup Preview', 'qualinav-admin-console'); ?></h2>
                        <p><?php esc_html_e('Your hospital-specific quality operating system draft', 'qualinav-admin-console'); ?></p>
                        <div class="qn-scout-context-chips" id="qn-scout-context-chips"></div>
                    </div>
                    <button class="qn-button qn-button-primary" type="button" id="qn-scout-generate-button" hidden>
                        <span class="dashicons dashicons-lightbulb"></span><?php esc_html_e('Generate Scout Preview', 'qualinav-admin-console'); ?>
                    </button>
                </div>
                <div id="qn-scout-preview-body" class="qn-scout-preview-body">
                    <div class="qn-empty-state">
                        <span class="dashicons dashicons-update"></span>
                        <h3><?php esc_html_e('Loading Scout preview...', 'qualinav-admin-console'); ?></h3>
                    </div>
                </div>
            </section>

            <div class="qn-modal" id="qn-scout-detail-modal" hidden>
                <div class="qn-modal-panel qn-scout-detail-modal-panel">
                    <div class="qn-panel-header">
                        <div>
                            <p class="qn-eyebrow"><?php esc_html_e('Scout Preview Details', 'qualinav-admin-console'); ?></p>
                            <h2 id="qn-scout-detail-title"><?php esc_html_e('Preview details', 'qualinav-admin-console'); ?></h2>
                        </div>
                        <button class="qn-icon-button" type="button" data-close-scout-detail aria-label="<?php esc_attr_e('Close Scout details', 'qualinav-admin-console'); ?>"><span>&times;</span></button>
                    </div>
                    <div class="qn-scout-detail-modal-body" id="qn-scout-detail-body"></div>
                </div>
            </div>

            <section class="qn-panel qn-section" id="hospital-users" data-section="hospital-users" hidden>
                <div class="qn-panel-header qn-users-page-header">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('Access Management', 'qualinav-admin-console'); ?></p>
                        <h2><?php esc_html_e('Workspace access roster', 'qualinav-admin-console'); ?></h2>
                        <p id="qn-hospital-users-subtitle"><?php esc_html_e('Manage access for this hospital workspace.', 'qualinav-admin-console'); ?></p>
                        <div class="qn-users-context-chips" id="qn-hospital-users-context"></div>
                    </div>
                    <button class="qn-button qn-button-primary" type="button" id="qn-hospital-invite-user-button"><?php esc_html_e('Invite User', 'qualinav-admin-console'); ?></button>
                </div>
                <div class="qn-users-summary-grid" id="qn-hospital-users-summary"></div>
                <div class="qn-list-tools">
                    <label class="qn-search-field" for="qn-hospital-user-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-hospital-user-search" placeholder="<?php esc_attr_e('Search users, email, roles, status', 'qualinav-admin-console'); ?>">
                    </label>
                    <div class="qn-filter-group">
                        <select id="qn-hospital-user-filter-role" aria-label="<?php esc_attr_e('Filter hospital users by role', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All roles', 'qualinav-admin-console'); ?></option></select>
                        <select id="qn-hospital-user-filter-status" aria-label="<?php esc_attr_e('Filter hospital users by status', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All statuses', 'qualinav-admin-console'); ?></option></select>
                    </div>
                </div>
                <div class="qn-table-wrap">
                    <table class="qn-table qn-hospital-users-table">
                        <thead><tr><th><?php esc_html_e('User', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Hospital Access', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Role', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Status', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th></tr></thead>
                        <tbody id="qn-hospital-users-table-body"><tr><td colspan="5"><?php esc_html_e('Loading users...', 'qualinav-admin-console'); ?></td></tr></tbody>
                    </table>
                </div>
                <section class="qn-card qn-hospital-invitations-card">
                    <div class="qn-panel-header">
                        <div>
                            <p class="qn-eyebrow"><?php esc_html_e('Invitations', 'qualinav-admin-console'); ?></p>
                            <h3><?php esc_html_e('Pending Invitations', 'qualinav-admin-console'); ?></h3>
                        </div>
                    </div>
                    <div class="qn-list-tools">
                        <label class="qn-search-field" for="qn-hospital-invitation-search">
                            <span class="dashicons dashicons-search"></span>
                            <input type="search" id="qn-hospital-invitation-search" placeholder="<?php esc_attr_e('Search invitees, emails, roles', 'qualinav-admin-console'); ?>">
                        </label>
                        <div class="qn-filter-group">
                            <select id="qn-hospital-invitation-filter-role" aria-label="<?php esc_attr_e('Filter hospital invitations by role', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All roles', 'qualinav-admin-console'); ?></option></select>
                            <select id="qn-hospital-invitation-filter-status" aria-label="<?php esc_attr_e('Filter hospital invitations by status', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All statuses', 'qualinav-admin-console'); ?></option></select>
                        </div>
                    </div>
                    <div class="qn-table-wrap">
                        <table class="qn-table qn-hospital-invitations-table">
                            <thead><tr><th><?php esc_html_e('Invitee', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Role', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Email Status', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Invite Status', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Expires', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th></tr></thead>
                            <tbody id="qn-hospital-invitations-table-body"><tr><td colspan="6"><?php esc_html_e('Loading invitations...', 'qualinav-admin-console'); ?></td></tr></tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section class="qn-onboarding-panel qn-section qn-onboarding-loading" id="day-0-setup" data-section="day-0-setup" hidden>
                <div class="qn-onboarding-hero">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('Selected hospital', 'qualinav-admin-console'); ?></p>
                        <h2 id="qn-onboarding-hospital-name"><?php esc_html_e('Loading workspace...', 'qualinav-admin-console'); ?></h2>
                        <p><?php esc_html_e('Hospital profile, setup path, and Scout context.', 'qualinav-admin-console'); ?></p>
                        <div class="qn-onboarding-context">
                            <span id="qn-onboarding-type-context">-</span>
                            <span id="qn-onboarding-system-context">-</span>
                            <span id="qn-onboarding-service-context">-</span>
                            <span id="qn-onboarding-state-context">-</span>
                        </div>
                        <div class="qn-onboarding-guide-actions">
                            <button class="qn-button qn-button-secondary" type="button" id="qn-onboarding-workspace-guide-button"><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e('Workspace guide', 'qualinav-admin-console'); ?></button>
                            <button class="qn-button qn-button-secondary" type="button" id="qn-onboarding-guide-button"><span class="dashicons dashicons-welcome-learn-more"></span><?php esc_html_e('Setup guide', 'qualinav-admin-console'); ?></button>
                            <button class="qn-button qn-button-secondary" type="button" id="qn-onboarding-question-list-button"><span class="dashicons dashicons-media-document"></span><?php esc_html_e('Print / save setup questions', 'qualinav-admin-console'); ?></button>
                        </div>
                    </div>
                    <div class="qn-onboarding-progress-card">
                        <span id="qn-onboarding-save-status"><?php esc_html_e('Ready', 'qualinav-admin-console'); ?></span>
                        <strong id="qn-onboarding-progress-text"><?php esc_html_e('0% complete', 'qualinav-admin-console'); ?></strong>
                        <small id="qn-onboarding-step-summary"><?php esc_html_e('Step 1 of 8', 'qualinav-admin-console'); ?></small>
                        <div class="qn-progress-shell" aria-hidden="true"><span id="qn-onboarding-progress-bar"></span></div>
                    </div>
                </div>
                <div class="qn-onboarding-loading-overlay" role="status" aria-live="polite">
                    <div class="qn-onboarding-loading-card">
                        <span class="dashicons dashicons-update"></span>
                        <strong><?php esc_html_e('Loading Hospital Setup...', 'qualinav-admin-console'); ?></strong>
                        <small><?php esc_html_e('Please wait while the hospital setup workspace loads.', 'qualinav-admin-console'); ?></small>
                    </div>
                </div>
                <div class="qn-onboarding-layout">
                    <nav class="qn-stepper" id="qn-onboarding-stepper" aria-label="<?php esc_attr_e('Onboarding steps', 'qualinav-admin-console'); ?>"></nav>
                    <form class="qn-onboarding-form" id="qn-onboarding-form">
                        <div class="qn-onboarding-step-header">
                            <p class="qn-eyebrow" id="qn-onboarding-step-count"></p>
                            <h3 id="qn-onboarding-step-title"></h3>
                            <p id="qn-onboarding-step-description"></p>
                            <details class="qn-onboarding-scout-guide" id="qn-onboarding-scout-guide">
                                <summary aria-controls="qn-onboarding-help" aria-expanded="false" role="button" tabindex="0">
                                    <span class="qn-scout-guide-label"><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e('How Scout uses this section', 'qualinav-admin-console'); ?></span>
                                    <span class="qn-scout-guide-action"><span class="qn-scout-guide-show"><?php esc_html_e('Show details', 'qualinav-admin-console'); ?></span><span class="qn-scout-guide-hide"><?php esc_html_e('Hide details', 'qualinav-admin-console'); ?></span><span class="dashicons dashicons-arrow-down-alt2"></span></span>
                                </summary>
                                <ul id="qn-onboarding-help"></ul>
                            </details>
                            <div class="qn-phi-warning" id="qn-phi-warning" hidden><?php esc_html_e('Do not enter patient names, MRNs, provider case details, incident narratives, peer-review details, or specific adverse-event details. QualiNav only stores structural information and aggregate/de-identified data.', 'qualinav-admin-console'); ?></div>
                        </div>
                        <div id="qn-onboarding-fields"></div>
                        <div class="qn-onboarding-actions">
                            <button class="qn-button qn-button-secondary" type="button" id="qn-onboarding-prev"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Previous', 'qualinav-admin-console'); ?></button>
                            <div>
                                <p class="qn-form-message" id="qn-onboarding-message"></p>
                                <button class="qn-button qn-button-secondary" type="button" id="qn-onboarding-save"><span class="dashicons dashicons-saved"></span><?php esc_html_e('Save', 'qualinav-admin-console'); ?></button>
                                <button class="qn-button qn-button-primary" type="button" id="qn-onboarding-next"><?php esc_html_e('Save & Continue', 'qualinav-admin-console'); ?><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                                <button class="qn-button qn-button-primary" type="button" id="qn-onboarding-submit" hidden><?php esc_html_e('Submit Final Setup', 'qualinav-admin-console'); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <div class="qn-modal" id="qn-onboarding-guide-modal" hidden>
                <div class="qn-modal-panel qn-onboarding-guide-modal-panel" role="dialog" aria-modal="true" aria-labelledby="qn-onboarding-guide-title">
                    <div class="qn-panel-header">
                        <div>
                            <p class="qn-eyebrow"><?php esc_html_e('Hospital Setup Guide', 'qualinav-admin-console'); ?></p>
                            <h2 id="qn-onboarding-guide-title"><?php esc_html_e('Set up your hospital workspace', 'qualinav-admin-console'); ?></h2>
                        </div>
                        <button class="qn-icon-button" type="button" data-close-onboarding-guide aria-label="<?php esc_attr_e('Close setup guide', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="qn-onboarding-guide-body">
                        <section>
                            <h3><?php esc_html_e('Why we ask these questions', 'qualinav-admin-console'); ?></h3>
                            <p><?php esc_html_e('Hospital Setup helps Scout understand your hospital’s reporting deadlines, committee flow, survey pathway, plans and policies, monitoring activities, QI projects, goals, and preferences. Scout uses this to build your first operating-system preview.', 'qualinav-admin-console'); ?></p>
                        </section>
                        <section>
                            <h3><?php esc_html_e('You do not need everything today', 'qualinav-admin-console'); ?></h3>
                            <p><?php esc_html_e('Complete what you know now. Skip anything you need to look up. You can return later to add details or update your setup when your hospital changes.', 'qualinav-admin-console'); ?></p>
                        </section>
                        <section class="qn-onboarding-materials-card">
                            <h3><?php esc_html_e('What you may want nearby', 'qualinav-admin-console'); ?></h3>
                            <ul id="qn-onboarding-materials-list"></ul>
                        </section>
                        <section>
                            <h3><?php esc_html_e('What happens after you submit', 'qualinav-admin-console'); ?></h3>
                            <p><?php esc_html_e('Scout can generate a preview of your reporting schedule, committee/report flow, survey readiness work, plan and policy priorities, clinical monitoring activities, improvement projects, and learning path.', 'qualinav-admin-console'); ?></p>
                        </section>
                        <section>
                            <h3><?php esc_html_e('Review annually', 'qualinav-admin-console'); ?></h3>
                            <p><?php esc_html_e('QualiNav will prompt an annual review of this setup information. You can update it anytime if meeting structures, reporting duties, contacts, or priorities change.', 'qualinav-admin-console'); ?></p>
                        </section>
                        <section class="qn-onboarding-no-phi-card">
                            <span class="dashicons dashicons-shield"></span>
                            <div>
                                <h3><?php esc_html_e('Data safety', 'qualinav-admin-console'); ?></h3>
                                <p><?php esc_html_e('Do not enter PHI, patient names, MRNs, provider case details, peer-review details, or adverse-event narratives. Use structural, operational, aggregate, or de-identified information only.', 'qualinav-admin-console'); ?></p>
                            </div>
                        </section>
                    </div>
                    <div class="qn-form-actions qn-onboarding-guide-footer">
                        <button class="qn-button qn-button-secondary" type="button" id="qn-onboarding-guide-print"><?php esc_html_e('Print setup question list', 'qualinav-admin-console'); ?></button>
                        <button class="qn-button qn-button-primary" type="button" id="qn-onboarding-guide-start"><?php esc_html_e('Start setup', 'qualinav-admin-console'); ?></button>
                    </div>
                </div>
            </div>

            <div class="qn-modal" id="qn-workspace-welcome-modal" hidden>
                <div class="qn-modal-panel qn-workspace-welcome-panel" role="dialog" aria-modal="true" aria-labelledby="qn-workspace-welcome-title">
                    <div class="qn-workspace-welcome-header">
                        <div class="qn-workspace-welcome-mark"><span class="dashicons dashicons-star-filled"></span></div>
                        <button class="qn-icon-button" type="button" data-close-workspace-welcome aria-label="<?php esc_attr_e('Close workspace guide', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="qn-workspace-welcome-body">
                        <p class="qn-eyebrow"><?php esc_html_e('Quality Director Workspace', 'qualinav-admin-console'); ?></p>
                        <h2 id="qn-workspace-welcome-title"><?php esc_html_e('Welcome to your QualiNav workspace', 'qualinav-admin-console'); ?></h2>
                        <p class="qn-workspace-welcome-subtitle"><?php esc_html_e('Your hospital quality operating system starts here.', 'qualinav-admin-console'); ?></p>
                        <p><?php esc_html_e('QualiNav helps organize the work that keeps your quality program moving: reporting deadlines, committee flow, survey readiness, plans and policies, clinical monitoring, improvement projects, and reminders.', 'qualinav-admin-console'); ?></p>
                        <p id="qn-workspace-welcome-setup-copy"><?php esc_html_e('Hospital Setup is the first step. It helps Scout understand how your hospital works so your workspace can be tailored to your actual reporting obligations, committee structure, monitoring processes, goals, and preferences.', 'qualinav-admin-console'); ?></p>
                        <div class="qn-workspace-value-grid">
                            <article><span class="dashicons dashicons-building"></span><strong><?php esc_html_e('Build your hospital profile', 'qualinav-admin-console'); ?></strong></article>
                            <article><span class="dashicons dashicons-lightbulb"></span><strong><?php esc_html_e('Let Scout tailor your workflow', 'qualinav-admin-console'); ?></strong></article>
                            <article><span class="dashicons dashicons-update"></span><strong><?php esc_html_e('Review and update over time', 'qualinav-admin-console'); ?></strong></article>
                        </div>
                        <div class="qn-workspace-welcome-note">
                            <span class="dashicons dashicons-clock"></span>
                            <p id="qn-workspace-welcome-reassurance"><?php esc_html_e('You do not have to complete everything today. Start with what you know, skip what you need to look up, and come back anytime. Your answers save as you go.', 'qualinav-admin-console'); ?></p>
                        </div>
                        <div class="qn-workspace-safety-note">
                            <span class="dashicons dashicons-shield"></span>
                            <p><?php esc_html_e('Please enter process-level, structural, and aggregate information only. Do not enter PHI, patient names, MRNs, provider case details, peer-review details, or adverse-event narratives.', 'qualinav-admin-console'); ?></p>
                        </div>
                    </div>
                    <div class="qn-form-actions qn-workspace-welcome-actions">
                        <label class="qn-workspace-welcome-dismiss">
                            <input type="checkbox" id="qn-workspace-welcome-dismiss-check" checked>
                            <span>
                                <strong><?php esc_html_e('Don’t show this welcome automatically again on this browser.', 'qualinav-admin-console'); ?></strong>
                                <small><?php esc_html_e('You can reopen it anytime from Open workspace guide on the dashboard.', 'qualinav-admin-console'); ?></small>
                            </span>
                        </label>
                        <button class="qn-button qn-button-secondary" type="button" id="qn-workspace-welcome-print"><?php esc_html_e('Print setup questions', 'qualinav-admin-console'); ?></button>
                        <button class="qn-button qn-button-secondary" type="button" id="qn-workspace-welcome-explore"><?php esc_html_e('Explore workspace first', 'qualinav-admin-console'); ?></button>
                        <button class="qn-button qn-button-primary" type="button" id="qn-workspace-welcome-primary"><?php esc_html_e('Continue Hospital Setup', 'qualinav-admin-console'); ?></button>
                    </div>
                </div>
            </div>

            <div class="qn-modal" id="qn-onboarding-question-list-modal" hidden>
                <div class="qn-modal-panel qn-onboarding-question-list-panel" role="dialog" aria-modal="true" aria-labelledby="qn-onboarding-question-list-title">
                    <div class="qn-panel-header">
                        <div>
                            <p class="qn-eyebrow"><?php esc_html_e('Preparation worksheet', 'qualinav-admin-console'); ?></p>
                            <h2 id="qn-onboarding-question-list-title"><?php esc_html_e('Hospital Setup question list', 'qualinav-admin-console'); ?></h2>
                        </div>
                        <button class="qn-icon-button" type="button" data-close-onboarding-question-list aria-label="<?php esc_attr_e('Close question list', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="qn-onboarding-question-list-body" id="qn-onboarding-question-list-body"></div>
                    <div class="qn-form-actions qn-onboarding-question-list-actions">
                        <button class="qn-button qn-button-secondary" type="button" data-close-onboarding-question-list><?php esc_html_e('Close', 'qualinav-admin-console'); ?></button>
                        <button class="qn-button qn-button-primary" type="button" id="qn-onboarding-print-question-list"><span class="dashicons dashicons-printer"></span><?php esc_html_e('Print / save as PDF', 'qualinav-admin-console'); ?></button>
                    </div>
                </div>
            </div>

            <section class="qn-panel qn-section" id="hospital-invitations" data-section="hospital-invitations" hidden>
                <div class="qn-panel-header">
                    <div>
                        <p class="qn-eyebrow"><?php esc_html_e('Invitations', 'qualinav-admin-console'); ?></p>
                        <h2><?php esc_html_e('Hospital Invites', 'qualinav-admin-console'); ?></h2>
                    </div>
                </div>
                <div class="qn-list-tools">
                    <label class="qn-search-field" for="qn-hospital-invitation-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="qn-hospital-invitation-search-legacy" placeholder="<?php esc_attr_e('Search invitees, emails, roles', 'qualinav-admin-console'); ?>">
                    </label>
                    <div class="qn-filter-group">
                        <select id="qn-hospital-invitation-filter-role-legacy" aria-label="<?php esc_attr_e('Filter hospital invitations by role', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All roles', 'qualinav-admin-console'); ?></option></select>
                        <select id="qn-hospital-invitation-filter-status-legacy" aria-label="<?php esc_attr_e('Filter hospital invitations by status', 'qualinav-admin-console'); ?>"><option value=""><?php esc_html_e('All statuses', 'qualinav-admin-console'); ?></option></select>
                    </div>
                </div>
                <div class="qn-table-wrap">
                        <table class="qn-table qn-hospital-invitations-table">
                        <thead><tr><th><?php esc_html_e('Invitee', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Role', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Status', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Expires', 'qualinav-admin-console'); ?></th><th><?php esc_html_e('Actions', 'qualinav-admin-console'); ?></th></tr></thead>
                        <tbody><tr><td colspan="5"><?php esc_html_e('Open Users to manage pending invitations.', 'qualinav-admin-console'); ?></td></tr></tbody>
                    </table>
                </div>
            </section>
            <section class="qn-section qn-reporting-page" id="reporting" data-section="reporting" hidden></section>
            <section class="qn-section qn-committees-page" id="committees" data-section="committees" hidden></section>
            <section class="qn-section qn-plans-page" id="plans" data-section="plans" hidden></section>
            <section class="qn-panel qn-section qn-future-module" id="clinical" data-section="clinical" data-future-module="clinical" hidden></section>
            <section class="qn-panel qn-section qn-future-module" id="settings" data-section="settings" data-future-module="settings" hidden></section>
        </main>
    </div>

    <div class="qn-modal" id="qn-invite-modal" hidden>
        <div class="qn-modal-panel" role="dialog" aria-modal="true" aria-labelledby="qn-invite-modal-title">
            <div class="qn-panel-header">
                <div>
                    <p class="qn-eyebrow"><?php esc_html_e('Hospital access', 'qualinav-admin-console'); ?></p>
                    <h2 id="qn-invite-modal-title"><?php esc_html_e('Invite hospital user', 'qualinav-admin-console'); ?></h2>
                    <p id="qn-invite-modal-helper"><?php esc_html_e('Send a secure invitation to the selected hospital workspace.', 'qualinav-admin-console'); ?></p>
                </div>
                <button class="qn-icon-button" type="button" data-close-invite aria-label="<?php esc_attr_e('Close', 'qualinav-admin-console'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="qn-invite-form" class="qn-form">
                <input type="hidden" name="context" value="hospital">
                <div class="qn-invite-workspace qn-form-wide">
                    <span><?php esc_html_e('Hospital workspace', 'qualinav-admin-console'); ?></span>
                    <strong id="qn-invite-workspace-name"><?php esc_html_e('Current hospital', 'qualinav-admin-console'); ?></strong>
                </div>
                <label><span><?php esc_html_e('Full name', 'qualinav-admin-console'); ?></span><input type="text" name="full_name"></label>
                <label><span><?php esc_html_e('Email', 'qualinav-admin-console'); ?></span><input type="email" name="email" required></label>
                <label class="qn-form-wide"><span><?php esc_html_e('Role', 'qualinav-admin-console'); ?></span><select name="qualinav_role" id="qn-invite-role"></select><small id="qn-invite-role-description"></small></label>
                <div class="qn-form-actions"><button class="qn-button" type="button" data-close-invite><?php esc_html_e('Cancel', 'qualinav-admin-console'); ?></button><button class="qn-button qn-button-primary" type="submit"><?php esc_html_e('Send Invite', 'qualinav-admin-console'); ?></button></div>
                <p class="qn-form-message" id="qn-invite-form-message"></p>
            </form>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
