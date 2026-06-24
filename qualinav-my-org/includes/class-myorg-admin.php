<?php
/**
 * My Org — admin settings hub.
 *
 * A single top-level "My Org" menu with a tabbed screen: a Sections tab to
 * enable/disable each module and edit its label, slug and description, plus
 * one tab per section that summarizes its config and deep-links into that
 * plugin's own settings screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Qualinav_My_Org_Admin {

    const MENU_SLUG     = 'qualinav-my-org';
    const SETTINGS_GROUP = 'qualinav_my_org_group';

    public static function boot() {
        // Register our top-level early (priority 9) so it exists as a parent,
        // then re-parent the other plugins' menus very late (priority 9999)
        // once they have all registered their own top-level menus.
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
        add_action( 'admin_menu', array( __CLASS__, 'reparent_menus' ), 9999 );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        // Print a tab bar at the top of every owned page (in place of the
        // sidebar dropdown — pages are still routed at their own URLs).
        add_action( 'all_admin_notices', array( __CLASS__, 'render_tabs_nav' ) );
    }

    public static function menu() {
        add_menu_page(
            'Qualinav My Org',
            'Qualinav My Org',
            'manage_options',
            self::MENU_SLUG,
            array( __CLASS__, 'render' ),
            'dashicons-networking',
            // Sit just below "QualiNav Quality Lab" (position 27). A float
            // avoids the menu-key collision that equal integer positions cause.
            27.7
        );
        // Drop the auto-created first submenu (it's a duplicate of the
        // parent link). Tabs live at the top of the page instead.
        remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
    }

    /**
     * Page slug → tab label map. The tab bar links to these existing pages
     * (registered as hidden submenus below), so internal POSTs / forms in
     * each module keep targeting the same admin.php?page=… URL.
     */
    public static function tabs_map() {
        $tabs = array( self::MENU_SLUG => 'Sections' );

        if ( class_exists( 'Quainav_Qapi_Dasboard' ) ) {
            $tabs['qaqd-dashboard-help'] = 'Data Hub';
        }
        if ( class_exists( 'Qualinav_Data_Hub_Measure_Library' ) ) {
            $tabs['qualinav-data-hub-measures'] = 'Measure Library';
        }
        if ( class_exists( 'Qualinav_Data_Hub_Drive_Settings' ) ) {
            $tabs['data-hub-drive'] = 'Drive Storage';
        }
        if ( class_exists( 'CAH_Assess\\Admin' ) ) {
            $tabs['cah-assessments'] = 'Assessments';
        }
        if ( class_exists( 'Qualinav_QI_Admin_Overview' ) ) {
            $tabs['qualinav-qi'] = 'QI Projects';
        }
        if ( class_exists( 'Qualinav_QI_Admin_Template_Editor' ) ) {
            $tabs['qualinav-qi-template'] = 'QI Template Editor';
        }
        if ( function_exists( 'dttc_render_help_page' ) ) {
            $tabs['dttc-help'] = 'Do Tank Chart';
        }
        $tabs['qualinav-my-org-appearance'] = 'Appearance';

        return $tabs;
    }

    /** Render the tab bar at the top of every owned admin page. */
    public static function render_tabs_nav() {
        $tabs    = self::tabs_map();
        $current = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( ! isset( $tabs[ $current ] ) ) {
            return;
        }
        echo '<h2 class="nav-tab-wrapper" style="margin:18px 0 14px;">';
        foreach ( $tabs as $slug => $label ) {
            $active = $slug === $current ? ' nav-tab-active' : '';
            printf(
                '<a class="nav-tab%s" href="%s">%s</a>',
                esc_attr( $active ),
                esc_url( admin_url( 'admin.php?page=' . $slug ) ),
                esc_html( $label )
            );
        }
        echo '</h2>';
    }

    /**
     * Pull Data Hub, Drive, Assessments and QI Projects under the single
     * "My Org" menu. We resolve each plugin's render callback (guarded), and
     * only after a replacement is confirmed do we drop the plugin's own
     * top-level menu — so a deactivated module never strands its settings.
     *
     * Page slugs are preserved exactly (cah-assessments, qaqd-dashboard-help,
     * data-hub-drive, qualinav-qi, qualinav-qi-template) so every internal
     * admin.php?page=… link and POST target inside those plugins keeps working.
     */
    public static function reparent_menus() {
        $qapi_cb = null;
        if ( class_exists( 'Quainav_Qapi_Dasboard' ) && method_exists( 'Quainav_Qapi_Dasboard', 'instance' ) ) {
            $instance = Quainav_Qapi_Dasboard::instance();
            if ( $instance && method_exists( $instance, 'render_admin_page' ) ) {
                $qapi_cb = array( $instance, 'render_admin_page' );
            }
        }

        // slug => array( page_title, menu_title, callback, parent_top_level_to_remove )
        $moves = array(
            'qaqd-dashboard-help' => array( 'Data Hub', 'Data Hub', $qapi_cb, 'qaqd-dashboard-help' ),
            'data-hub-drive'      => array( 'Drive Storage', 'Drive Storage', array( 'Qualinav_Data_Hub_Drive_Settings', 'render_page' ), null ),
            'cah-assessments'     => array( 'Assessments', 'Assessments', array( 'CAH_Assess\\Admin', 'page' ), 'cah-assessments' ),
            'qualinav-qi'         => array( 'QI Projects', 'QI Projects', array( 'Qualinav_QI_Admin_Overview', 'render' ), 'qualinav-qi' ),
            'qualinav-qi-template'=> array( 'QI Template Editor', 'QI Template Editor', array( 'Qualinav_QI_Admin_Template_Editor', 'render' ), null ),
            'dttc-help'           => array( 'Do Tank Chart', 'Do Tank Chart', 'dttc_render_help_page', 'dttc-help' ),
        );

        foreach ( $moves as $slug => $move ) {
            list( $page_title, $menu_title, $callback, $remove_top ) = $move;
            if ( empty( $callback ) || ! is_callable( $callback ) ) {
                continue; // module inactive — leave its own menu alone.
            }
            if ( $remove_top ) {
                remove_menu_page( $remove_top );
            }
            // Register as a hidden page (parent = null) — keeps the existing
            // admin.php?page=<slug> URL working so module internal forms/
            // POSTs don't break, but it's not listed in the sidebar.
            add_submenu_page(
                null,
                $page_title,
                $menu_title,
                'manage_options',
                $slug,
                $callback
            );
        }
    }

    public static function register_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            Qualinav_My_Org_Settings::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( 'Qualinav_My_Org_Settings', 'sanitize' ),
                'default'           => array(),
            )
        );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $sections = Qualinav_My_Org_Settings::all();
        ?>
        <div class="wrap">
            <h1>My Org</h1>
            <p class="description">
                My Org owns the <code><?php echo esc_html( '/' . Qualinav_My_Org_Settings::MY_ORG_SLUG . '/' ); ?></code>
                hub and orchestrates Data Hub, QI Projects and Assessments. Each module's own
                settings live in the submenus under this menu.
            </p>
            <?php self::render_sections_tab( $sections ); ?>
        </div>
        <?php
    }

    /** The editable grid: built-in sections + a repeater for custom tiles. */
    private static function render_sections_tab( $sections ) {
        $opt    = Qualinav_My_Org_Settings::OPTION;
        $custom = Qualinav_My_Org_Settings::get_custom();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::SETTINGS_GROUP ); ?>

            <h2 style="margin-top:24px;">Built-in sections</h2>
            <table class="widefat striped" style="max-width:980px;">
                <thead>
                    <tr>
                        <th style="width:90px;">Enabled</th>
                        <th>Section</th>
                        <th>Front-end slug</th>
                        <th>Description (tile text)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $sections as $key => $sec ) : ?>
                    <?php if ( ! empty( $sec['is_custom'] ) ) { continue; } ?>
                    <tr>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>][enabled]" value="0">
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>][enabled]"
                                       value="1" <?php checked( $sec['enabled'], 1 ); ?>>
                                On
                            </label>
                        </td>
                        <td>
                            <strong>
                                <input type="text" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>][label]"
                                       value="<?php echo esc_attr( $sec['label'] ); ?>">
                            </strong>
                            <p class="description"><code><?php echo esc_html( $key ); ?></code></p>
                        </td>
                        <td>
                            <code>/</code>
                            <input type="text"
                                   name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>][slug]"
                                   value="<?php echo esc_attr( $sec['slug'] ); ?>" style="width:140px;">
                            <code>/</code>
                        </td>
                        <td>
                            <textarea rows="3" style="width:100%;"
                                      name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>][description]"><?php echo esc_textarea( $sec['description'] ); ?></textarea>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description" style="margin-top:10px;">
                Disabling a section hides its tile on the hub and redirects its front-end slug back to
                <code><?php echo esc_html( '/' . Qualinav_My_Org_Settings::MY_ORG_SLUG . '/' ); ?></code>.
            </p>

            <h2 style="margin-top:32px;">Custom sections</h2>
            <p class="description">
                Add extra tiles to the hub. The link can be a site path
                (e.g. <code>policy-management</code>) or a full URL (e.g. <code>https://example.com</code>).
            </p>
            <table class="widefat striped" style="max-width:980px;">
                <thead>
                    <tr>
                        <th style="width:90px;">Enabled</th>
                        <th>Title</th>
                        <th>Link (path or URL)</th>
                        <th>Description (tile text)</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="myorg-custom-rows">
                <?php
                $i = 0;
                foreach ( $custom as $row ) {
                    self::render_custom_row( $opt, $i, $row );
                    $i++;
                }
                ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" id="myorg-add-section">+ Add section</button>
            </p>

            <script type="text/html" id="myorg-custom-row-tpl">
                <?php
                ob_start();
                self::render_custom_row( $opt, '__INDEX__', array( 'id' => '', 'enabled' => 1, 'label' => '', 'url' => '', 'description' => '' ) );
                echo str_replace( array( '<tr>', '</tr>' ), '', ob_get_clean() ); // phpcs:ignore
                ?>
            </script>

            <script>
            (function(){
                var tbody = document.getElementById('myorg-custom-rows');
                var tpl   = document.getElementById('myorg-custom-row-tpl').innerHTML;
                var idx   = <?php echo (int) count( $custom ); ?>;
                document.getElementById('myorg-add-section').addEventListener('click', function(){
                    var tr = document.createElement('tr');
                    tr.innerHTML = tpl.replace(/__INDEX__/g, idx++);
                    tbody.appendChild(tr);
                });
                tbody.addEventListener('click', function(e){
                    var btn = e.target.closest('.myorg-remove-row');
                    if ( btn ) { btn.closest('tr').remove(); }
                });
            })();
            </script>

            <?php submit_button( 'Save Sections' ); ?>
        </form>
        <?php
    }

    /** One custom-section row (also used as the JS clone template). */
    private static function render_custom_row( $opt, $i, $row ) {
        $base = esc_attr( $opt ) . '[' . esc_attr( Qualinav_My_Org_Settings::CUSTOM_KEY ) . '][' . esc_attr( $i ) . ']';
        ?>
        <tr>
            <td>
                <input type="hidden" name="<?php echo $base; ?>[id]" value="<?php echo esc_attr( $row['id'] ); ?>">
                <input type="hidden" name="<?php echo $base; ?>[enabled]" value="0">
                <label>
                    <input type="checkbox" name="<?php echo $base; ?>[enabled]" value="1" <?php checked( ! empty( $row['enabled'] ), true ); ?>>
                    On
                </label>
            </td>
            <td>
                <input type="text" class="regular-text" placeholder="Tile title"
                       name="<?php echo $base; ?>[label]" value="<?php echo esc_attr( $row['label'] ); ?>">
            </td>
            <td>
                <input type="text" placeholder="policy-management or https://…" style="width:220px;"
                       name="<?php echo $base; ?>[url]" value="<?php echo esc_attr( $row['url'] ); ?>">
            </td>
            <td>
                <textarea rows="3" style="width:100%;"
                          name="<?php echo $base; ?>[description]"><?php echo esc_textarea( $row['description'] ); ?></textarea>
            </td>
            <td>
                <button type="button" class="button-link myorg-remove-row" style="color:#b32d2e;">Remove</button>
            </td>
        </tr>
        <?php
    }

}
