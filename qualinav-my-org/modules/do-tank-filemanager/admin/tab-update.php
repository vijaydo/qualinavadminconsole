<?php
defined('ABSPATH') || exit;

/**
 * Do Tank File Manager — Update Tab
 */

// Detect plugin version
if (!function_exists('get_file_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugin_data = get_file_data(DTU_DIR . 'do-tank-uploader.php', [
    'Version' => 'Version'
]);

$current_version = $plugin_data['Version'] ?? '0.0.0';
?>

<div class="wrap dtu-admin-wrap">

    <h1>Do Tank File Manager Update Manager</h1>

    <div class="dtu-update-panel" data-current-version="<?php echo esc_attr($current_version); ?>">

        <p class="dtu-update-current">
            <strong>Installed Version:</strong>
            <span id="dtu-current-version"><?php echo esc_html($current_version); ?></span>
        </p>

        <div class="dtu-update-actions">
            <button type="button" class="button button-secondary dtu-admin-btn" id="dtu-check-update">
                Check for Updates
            </button>
            <span class="spinner" id="dtu-update-spinner"></span>
        </div>

        <div id="dtu-update-status" class="dtu-update-status-box">
            Click "Check for Updates" to see if a new version is available.
        </div>

        <div id="dtu-update-changelog" class="dtu-update-changelog" style="display:none;">
            <h3>What's New</h3>
            <div class="dtu-changelog-content">Loading…</div>
        </div>

        <button type="button" class="button button-primary dtu-admin-btn" id="dtu-update-now" style="display:none;">
            Update Now
        </button>

    </div>
</div>

<style>
.dtu-admin-wrap {
    background: #fff;
    padding: 30px;
    margin-top: 20px;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    font-family: "Segoe UI", sans-serif;
}

.dtu-admin-wrap h1 {
    margin-bottom: 24px;
    font-size: 1.6rem;
    font-weight: 600;
}

/* Panel */
.dtu-update-panel {
    max-width: 540px;
    padding: 20px;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    background: #f8f9fb;
}

.dtu-update-current {
    font-size: 15px;
    margin-bottom: 16px;
}

.dtu-update-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

#dtu-update-spinner {
    visibility: hidden;
}

/* Status */
.dtu-update-status-box {
    margin-bottom: 18px;
    padding: 15px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #ccd0d4;
    font-size: 14px;
    line-height: 1.5;
}

.dtu-update-status-box.success {
    border-color: #46b450;
    background: #edf8ee;
    color: #1f5f24;
}

.dtu-update-status-box.error {
    border-color: #dc3232;
    background: #fceaea;
    color: #760e0e;
}

/* Changelog */
.dtu-update-changelog {
    margin-top: 24px;
    padding: 20px 22px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #dcdcdc;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
    max-width: 540px;
}

.dtu-update-changelog h3 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.dtu-update-changelog .dtu-changelog-content {
    font-size: 14px;
    line-height: 1.7;
    color: #2c3338;
    margin-top: 4px;
    padding-left: 4px;
}

.dtu-update-changelog .dtu-changelog-content p {
    margin: 6px 0;
}

.dtu-update-changelog ul,
.dtu-update-changelog ol {
    margin: 0 0 0 20px;
    padding: 0;
}

.dtu-update-changelog li {
    margin-bottom: 4px;
}

#dtu-update-now {
    margin-top: 8px;
    padding: 10px 26px;
    font-size: 15px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.dtu-update-status-box + .dtu-update-changelog {
    margin-top: 14px;
}

.dtu-update-changelog + #dtu-update-now {
    margin-top: 18px;
}
</style>
