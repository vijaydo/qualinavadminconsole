<?php
//class-dtu-page.php

defined('ABSPATH') || exit;

class DTU_Page
{
    private const ROUTE_UPLOADER = 'do-tank-uploader';
    private const ROUTE_EMBED = 'qualinav-org-documents-embed';

    public function __construct()
    {
        add_action('init', [$this, 'register_route']);
        add_action('template_redirect', [$this, 'intercept_route']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_route()
    {
        add_rewrite_rule('^do-tank-uploader/?$', 'index.php?dtu_page=1', 'top');
        add_rewrite_rule('^qualinav-org-documents-embed/?$', 'index.php?dtu_page=1&dtu_embedded_page=1', 'top');
        add_rewrite_tag('%dtu_page%', '1');
        add_rewrite_tag('%dtu_embedded_page%', '1');
    }

    public function enqueue_assets()
    {
        if (!$this->is_uploader_request())
            return;

        $base = plugin_dir_url(__DIR__);

        if (!wp_style_is('qualinav-font-awesome', 'enqueued')) {
            wp_enqueue_style('qualinav-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        }

        $asset_version = defined('DTU_VERSION') ? DTU_VERSION : '0.3.3';

        wp_enqueue_style('dtu-css', $base . 'assets/css/uploader.css', [], $asset_version);
        wp_enqueue_script('dtu-js', $base . 'assets/js/uploader.js', [], $asset_version, true);

        $current_user = wp_get_current_user();
        $hub_name = sanitize_title(get_bloginfo('name'));

        wp_add_inline_script('dtu-js', 'const DTU_AJAX = ' . json_encode([
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dtu_upload'),
            'list_nonce' => wp_create_nonce('dtu_list_files'),
            'hub' => $hub_name,
            'user' => $current_user->display_name ?: $current_user->user_login,
        ]), 'before');
    }

    /**
     * Instead of printing HTML directly, intercept the route and load our custom template.
     */
    public function intercept_route()
    {
        if (!$this->is_uploader_request())
            return;

        if (!is_user_logged_in()) {
            wp_die('<div class="dtu-locked"><h2>Restricted Access</h2><p>Please log in to upload files.</p></div>');
        }

        global $wp_query;

        if ($wp_query) {
            $wp_query->is_404 = false;
        }

        status_header(200);
        add_filter('pre_get_document_title', function () {
            return 'File Upload Portal';
        });

        add_filter('template_include', function () {
            return plugin_dir_path(dirname(__FILE__)) . 'templates/dtu-blank.php';
        });
    }

    private function is_uploader_request()
    {
        if (get_query_var('dtu_page')) {
            return true;
        }

        $route = $this->get_request_route_slug();

        return in_array($route, [self::ROUTE_UPLOADER, self::ROUTE_EMBED], true);
    }

    private function get_request_route_slug()
    {
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        if ('' === $path) {
            return '';
        }

        $segments = explode('/', $path);

        return (string) end($segments);
    }

    /**
     * This method prints ONLY the inner HTML — used by dtu-blank.php template.
     */
    public function render_page_contents()
    {
        ?>
        <main class="dtu-wrapper">
            <div id="dtu-card">
                <h1>File Upload Portal</h1>
                <p class="dtu-subtext">You can upload up to 10 PDF or DOCX files at once.</p>

                <div id="dtu-upload-ui">
                    <form id="dtu-form">
                        <div id="dtu-dropzone">
                            <input type="file" id="dtu-file-input" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple>
                            <label for="dtu-file-input" class="dtu-dropzone-content">
                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"
                                    fill="#5f6368">
                                    <path d="M0 0h24v24H0V0z" fill="none" />
                                    <path
                                        d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z" />
                                </svg>
                                <p>Drag & drop files or click to select</p>
                            </label>
                        </div>
                        <div id="dtu-file-list-area"></div>
                        <button type="button" id="dtu-upload-btn" style="display: none;">Upload</button>
                    </form>
                </div>
            </div>

            <div id="dtu-file-list" class="dtu-card">
                <h2>Your Uploaded Files</h2>

                <div class="dtu-file-search">
                    <label for="dtu-file-search-input" class="screen-reader-text">Search uploaded files</label>
                    <span class="dtu-file-search-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                    </span>
                    <input type="search" id="dtu-file-search-input" placeholder="Search by document name or uploaded by">
                </div>

                <div id="dtu-file-list-container">
                    <table id="dtu-file-list-table">
                        <thead>
                            <tr>
                                <th class="file-icon-cell"></th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Uploaded By</th>
                                <th>Uploaded</th>
                                <th>Size</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <div id="dtu-file-list-loading">
                        <p>Loading your files...</p>
                    </div>

                    <div id="dtu-file-list-empty" style="display:none;">
                        <p>You haven't uploaded any files yet.</p>
                    </div>

                    <div id="dtu-file-list-no-results" style="display:none;">
                        <p>No files match your search.</p>
                    </div>
                </div>
            </div>
        </main>

        <div id="dtu-overlay-loader" style="display:none;">
            <div class="dtu-loader-spinner"></div>
        </div>
        <?php
    }
}
