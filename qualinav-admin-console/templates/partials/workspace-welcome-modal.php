<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="qn-modal qn-home-welcome-modal" id="qn-workspace-welcome-modal" hidden>
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
            <p id="qn-workspace-welcome-setup-copy"><?php esc_html_e('Hospital Setup gives Scout durable hospital context. Measures, deadlines, submissions, and performance stay in Data Hub.', 'qualinav-admin-console'); ?></p>
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
                    <strong><?php esc_html_e('Don\'t show this welcome automatically again on this browser.', 'qualinav-admin-console'); ?></strong>
                    <small><?php esc_html_e('You can start setup later from My Org, Organization Setup.', 'qualinav-admin-console'); ?></small>
                </span>
            </label>
            <button class="qn-button qn-button-secondary" type="button" id="qn-workspace-welcome-explore"><?php esc_html_e('Explore QualiNav', 'qualinav-admin-console'); ?></button>
            <button class="qn-button qn-button-primary" type="button" id="qn-workspace-welcome-primary"><?php esc_html_e('Continue Hospital Setup', 'qualinav-admin-console'); ?></button>
        </div>
    </div>
</div>
