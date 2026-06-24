<?php
defined('ABSPATH') || exit;

/**
 * Do Tank File Manager — Admin (Clean version without Uploads tab)
 */
class DTU_Admin {

    private $notices = [];

    public function __construct() {

        // Menu
        add_action('admin_menu', [$this, 'register_menu']);

        // Settings
        add_action('admin_init', [$this, 'register_settings']);

        // Notices
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Register plugin settings for the Settings tab
     */
    public function register_settings() {

        register_setting(
            'dtu_settings',
            DTU_Config::OPTION_ROOT_FOLDER,
            [
                'type'              => 'string',
                'sanitize_callback' => [$this, 'sanitize_and_initialize_root'],
                'default'           => ''
            ]
        );
    }

    /**
     * Validate root folder + auto-create site folder + set admin notices
     */
    public function sanitize_and_initialize_root($root_id) {
        $root_id = trim($root_id);

        if (!$root_id) {
            $this->notices['error'] = 'Root Folder ID cannot be empty.';
            return '';
        }

        try {
            $client = new Google_Client();
            $client->useApplicationDefaultCredentials();
            $client->setScopes([ Google_Service_Drive::DRIVE ]);

            $svc = new Google_Service_Drive($client);

            // Validate root folder exists
            try {
                $root = $svc->files->get($root_id, [
                    'fields'            => 'id,name',
                    'supportsAllDrives' => true,
                ]);
            } catch (Throwable $e) {
                $this->notices['error'] = 'Invalid Root Folder ID. Please verify and try again.';
                return '';
            }

            // Site folder name (scr, luna, vineposter…)
            $site_folder_name = sanitize_title(get_bloginfo('name'));

            // Check if site folder exists
            $resp = $svc->files->listFiles([
                'q'   => sprintf(
                    "'%s' in parents and name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                    $root_id,
                    $site_folder_name
                ),
                'fields'                    => 'files(id,name)',
                'supportsAllDrives'         => true,
                'includeItemsFromAllDrives' => true,
            ]);

            if (!empty($resp->files)) {
                $this->notices['success'] = 'Root folder validated. Site folder already exists.';
                return $root_id;
            }

            // Create site folder
            $folder_metadata = new Google_Service_Drive_DriveFile([
                'name'     => $site_folder_name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$root_id]
            ]);

            $created = $svc->files->create($folder_metadata, [
                'fields'            => 'id',
                'supportsAllDrives' => true
            ]);

            if ($created && $created->id) {
                $this->notices['success'] = 'Root folder validated. Site folder created successfully.';
            } else {
                $this->notices['error'] = 'Could not create the site folder. Check Drive permissions.';
            }

        } catch (Throwable $e) {
            $this->notices['error'] = 'Unexpected error: ' . $e->getMessage();
        }

        return $root_id;
    }

    /**
     * Display admin notices
     */
    public function render_notices() {
        if (empty($this->notices)) return;

        foreach ($this->notices as $type => $message) {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        }
    }

    /**
     * Register admin menu
     */
    public function register_menu() {

        add_menu_page(
            'Grapevine File Manager',
            'Grapevine File Manager',
            'manage_options',
            'dtu-file-manager',
            [$this, 'render_admin_tabs'],
            'dashicons-portfolio',
            26
        );
    }

    /**
     * Render tab navigation + content
     */
    public function render_admin_tabs() {

        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        // Uploads tab removed
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

        $tabs = [
            'dashboard' => __('Dashboard', 'dtu'),
            'settings'  => __('Settings', 'dtu'),
			'update'  => __('Update', 'dtu'),
        ];

        $base = admin_url('admin.php?page=dtu-file-manager');
        ?>

        <div class="wrap">
            <h1>Grapevine File Manager</h1>

            <h2 class="nav-tab-wrapper" style="margin-top:16px;">
                <?php foreach ($tabs as $slug => $label):
                    $url = esc_url(add_query_arg('tab', $slug, $base));
                    $active = $tab === $slug ? ' nav-tab-active' : '';
                ?>
                    <a href="<?php echo $url; ?>" class="nav-tab<?php echo $active; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div style="margin-top:20px;">
                <?php
                $file = plugin_dir_path(__FILE__) . "tab-$tab.php";

                if (file_exists($file)) {
                    include $file;
                } else {
                    echo '<p>Invalid tab.</p>';
                }
                ?>
            </div>
        </div>

        <?php
    }
}
