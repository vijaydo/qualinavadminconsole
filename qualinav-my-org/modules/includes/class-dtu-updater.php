<?php
defined('ABSPATH') || exit;

/**
 * Do Tank File Manager — Update Manager
 */
class DTU_Updater {

    const NONCE_ACTION = 'dtu_update_nonce';

    // GitHub Repo (DTU)
    const GITHUB_REPO = 'https://github.com/dotankdo/dotankfilemanger';

    // Raw version file (main plugin header in main branch)
    const RAW_VERSION = 'https://api.github.com/repos/dotankdo/dotankfilemanger/contents/do-tank-uploader.php?ref=main';

    /**
     * Initialize updater
     */
    public static function init() {
        if (!is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_dtu_check_update', [__CLASS__, 'ajax_check_update']);
        add_action('wp_ajax_dtu_do_update',   [__CLASS__, 'ajax_do_update']);
    }

    /**
     * Get GitHub token from wp-config.php
     */
    protected static function get_token() {
        return defined('VP_GITHUB_TOKEN') ? VP_GITHUB_TOKEN : '';
    }

    /**
     * Load JS only on Update tab
     */
    public static function enqueue_assets($hook) {

        if (
            !isset($_GET['page']) ||
            $_GET['page'] !== 'dtu-file-manager' ||
            !isset($_GET['tab']) ||
            $_GET['tab'] !== 'update'
        ) {
            return;
        }

        $handle = 'dtu-updater';
        $src    = DTU_URL . 'admin/assets/js/dtu-update-checker.js';

        wp_register_script($handle, $src, ['jquery'], null, true);

        wp_localize_script($handle, 'dtuUpdater', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce(self::NONCE_ACTION),
            'currentVersion' => self::get_current_version(),
            'githubRepo'     => self::GITHUB_REPO,
            'strings'        => [
                'checking'   => __('Checking for updates…', 'dtu'),
                'installing' => __('Installing update…', 'dtu'),
                'upToDate'   => __('You already have the latest version.', 'dtu'),
                'error'      => __('Something went wrong. Please try again.', 'dtu'),
            ],
        ]);

        wp_enqueue_script($handle);
    }

    /**
     * Current installed version
     */
    public static function get_current_version(): string {

        if (!function_exists('get_file_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_file_data(DTU_DIR . 'do-tank-uploader.php', ['Version' => 'Version']);
        return $data['Version'] ?? '0.0.0';
    }

    /**
     * AJAX – Check for update
     */
    public static function ajax_check_update() {
        self::guard();

        $latest = self::get_latest_version();
        if (is_wp_error($latest)) {
            wp_send_json_error(['message' => $latest->get_error_message()]);
        }

        $current    = self::get_current_version();
        $has_update = version_compare($latest, $current, '>');

        $status  = $has_update ? 'update_available' : 'up_to_date';
        $message = $has_update
            ? "New version {$latest} is available."
            : "You already have the latest version.";

        $changelog = self::fetch_changelog($latest);

        $zip_url = $has_update
            ? "https://api.github.com/repos/dotankdo/dotankfilemanger/zipball/v{$latest}"
            : '';

        wp_send_json_success([
            'status'          => $status,
            'message'         => $message,
            'latest_version'  => $latest,
            'current_version' => $current,
            'download_url'    => $zip_url,
            'changelog'       => $changelog,
        ]);
    }

    /**
     * AJAX – Perform update
     */
    public static function ajax_do_update() {
        self::guard();

        $download_url = esc_url_raw($_POST['download_url'] ?? '');
        if (!$download_url) {
            wp_send_json_error(['message' => 'Missing download URL.']);
        }

        $result = self::perform_update($download_url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        delete_site_transient('update_plugins');

        wp_send_json_success([
            'message'        => 'Do Tank File Manager updated successfully.',
            'latest_version' => self::get_current_version(),
            'reload'         => true,
        ]);
    }

    /**
     * Access + nonce
     */
    protected static function guard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }
    }

    // ----------------------------------------------------------------------
    // Fetch latest version
    // ----------------------------------------------------------------------
    protected static function get_latest_version() {

        $token = self::get_token();
        if (!$token) {
            return new WP_Error('dtu_no_token', 'GitHub token VP_GITHUB_TOKEN missing in wp-config.php.');
        }

        $response = wp_remote_get(self::RAW_VERSION, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => "token {$token}",
                'User-Agent'    => 'DoTankFileManager-Updater',
                'Accept'        => 'application/vnd.github.v3.raw',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        if (!preg_match('/^\s*\*\s*Version:\s*([0-9a-zA-Z.\-]+)/mi', $body, $matches)) {
            return new WP_Error('dtu_parse', 'Cannot read version from GitHub.');
        }

        return trim($matches[1]);
    }

    // ----------------------------------------------------------------------
    // Fetch changelog
    // ----------------------------------------------------------------------
    protected static function fetch_changelog($version) {

        $token = self::get_token();

        $urls = [
            "https://api.github.com/repos/dotankdo/dotankfilemanger/releases/tags/v{$version}",
            "https://api.github.com/repos/dotankdo/dotankfilemanger/releases/tags/{$version}",
        ];

        foreach ($urls as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => "token {$token}",
                    'User-Agent'    => 'DoTankFileManager-Updater',
                ],
            ]);

            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['body'])) {
                    return wpautop(wp_kses_post($data['body']));
                }
            }
        }

        return 'No changelog available.';
    }

    // ----------------------------------------------------------------------
    // Run update: download → extract → replace (preserve vendor/)
    // ----------------------------------------------------------------------
    protected static function perform_update($url) {

        $token = self::get_token();

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $response = wp_remote_get($url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => "token {$token}",
                'User-Agent'    => 'DoTankFileManager-Updater',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        $tmp = wp_tempnam('dtu_update.zip');
        file_put_contents($tmp, $body);

        $working = get_temp_dir() . 'dtu-update-' . time();
        wp_mkdir_p($working);

        $unzipped = unzip_file($tmp, $working);
        @unlink($tmp);

        if (is_wp_error($unzipped)) {
            return $unzipped;
        }

        $src = self::locate_source_dir($working);
        if (!$src) {
            self::cleanup_dir($working);
            return new WP_Error('dtu_missing_src', 'Plugin source folder not found in ZIP.');
        }

        // ----------------------------------------------------
        // SAFE UPDATE: Preserve vendor/ unless ZIP includes it
        // ----------------------------------------------------
        $zip_has_vendor = is_dir($src . '/vendor');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $rel    = str_replace($src, '', $file->getPathname());
            $target = DTU_DIR . $rel;

            // Skip vendor folder if ZIP does NOT contain one
            if (!$zip_has_vendor && strpos($rel, '/vendor') === 0) {
                continue;
            }

            if ($file->isDir()) {
                wp_mkdir_p($target);
                continue;
            }

            if ($file->isFile()) {
                wp_mkdir_p(dirname($target));
                copy($file->getPathname(), $target);
            }
        }

        self::cleanup_dir($working);

        return true;
    }

    protected static function locate_source_dir($working) {
        $dirs = glob($working . '/*', GLOB_ONLYDIR);
        return $dirs[0] ?? false;
    }

    protected static function cleanup_dir($path) {
        if (!file_exists($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        @rmdir($path);
    }
}
