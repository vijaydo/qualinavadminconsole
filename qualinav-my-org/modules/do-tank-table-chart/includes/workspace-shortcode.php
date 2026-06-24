<?php
/**
 * Workspace shortcode providing multi-chart UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dttc_workspace_shortcode() : string {
    if ( ! is_user_logged_in() ) {
        return '<div class="dttc-workspace dttc-not-logged">' . esc_html__( 'Please log in to manage charts.', 'do-tank-table-chart' ) . '</div>';
    }

    // Ensure base assets are available (table + chart UI logic).
    wp_enqueue_style( 'dttc-style' );
    wp_enqueue_script( 'dttc-script' );

    // Workspace assets.
    wp_enqueue_style( 'dttc-workspace-style', DTTC_URL . 'assets/css/dttc-workspace.css', array(), DTTC_VERSION );
    wp_enqueue_script( 'dttc-workspace-script', DTTC_URL . 'assets/js/dttc-workspace.js', array( 'dttc-script' ), DTTC_VERSION, true );

    wp_localize_script(
        'dttc-workspace-script',
        'DTTC_WORKSPACE',
        array(
            'restUrl'         => esc_url_raw( rest_url( 'dttc/v1' ) ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'cmNonce'         => wp_create_nonce( 'cm_nonce' ),
            'myDataUrl'       => esc_url_raw( home_url( '/my-data/' ) ),
            'publicChartsUrl' => esc_url_raw( home_url( '/public-charts/' ) ),
        )
    );

    ob_start();
    ?>
    <div class="dttc-workspace" id="dttc-workspace">
        <div class="dttc-ws-sidebar">
            <div class="dttc-ws-brand">
                <div class="dttc-ws-title">
                    <div class="dttc-ws-icon-circle">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <span><?php echo esc_html__( 'Welcome to Charts', 'do-tank-table-chart' ); ?></span>
                </div>
                <button type="button" class="dttc-btn dttc-btn-primary" id="dttc-new-chart">
                    <i class="fas fa-plus"></i> <?php echo esc_html__( 'New Chart', 'do-tank-table-chart' ); ?>
                </button>
            </div>

            <div class="dttc-ws-search">
                <input type="search" id="dttc-chart-search" placeholder="<?php echo esc_attr__( 'Search charts…', 'do-tank-table-chart' ); ?>" />
                <i class="fas fa-search"></i>
            </div>

            <div class="dttc-ws-list" id="dttc-chart-list" aria-label="<?php echo esc_attr__( 'Charts list', 'do-tank-table-chart' ); ?>">
                <div class="dttc-ws-empty" id="dttc-no-charts"><?php echo esc_html__( 'No charts yet. Click New Chart to get started.', 'do-tank-table-chart' ); ?></div>
            </div>
        </div>

        <div class="dttc-ws-main">
            <div class="dttc-ws-header">
                <input type="text" class="dttc-ws-chart-title" id="dttc-chart-title" value="" placeholder="<?php echo esc_attr__( 'Chart title…', 'do-tank-table-chart' ); ?>" />
                <div class="dttc-ws-actions">
                    <button type="button" class="dttc-btn dttc-btn-primary" id="dttc-save-chart"><?php echo esc_html__( 'Save', 'do-tank-table-chart' ); ?></button>
                    
                    <div class="dttc-ws-dropdown" id="dttc-header-menu">
                        <button type="button" class="dttc-ws-icon-btn" id="dttc-menu-toggle" title="<?php echo esc_attr__( 'Chart options', 'do-tank-table-chart' ); ?>">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dttc-ws-dropdown-content" id="dttc-menu-dropdown">
                            <button type="button" id="dttc-rename-chart-trigger">
                                <i class="fas fa-edit"></i> <span><?php echo esc_html__( 'Rename', 'do-tank-table-chart' ); ?></span>
                            </button>
                            <button type="button" id="dttc-share-chart-trigger">
                                <i class="fas fa-share-alt"></i> <span id="dttc-share-label"><?php echo esc_html__( 'Share to Public Charts', 'do-tank-table-chart' ); ?></span>
                            </button>
                            <button type="button" id="dttc-tag-member-trigger">
                                <i class="fas fa-user-tag"></i> <span><?php echo esc_html__( 'Tag Member', 'do-tank-table-chart' ); ?></span>
                            </button>
                            <div class="dttc-ws-dropdown-divider"></div>
                            <button type="button" id="dttc-delete-chart-trigger" class="dttc-ws-danger">
                                <i class="fas fa-trash"></i> <span><?php echo esc_html__( 'Delete', 'do-tank-table-chart' ); ?></span>
                            </button>
                        </div>
                    </div>

                    <a href="<?php echo esc_url( home_url( '/data-hub/#dashboard' ) ); ?>" class="gv-back-btn" style="margin-left: 12px;">
                        <i class="fas fa-arrow-left"></i> Data Hub
                    </a>

                    <span class="dttc-ws-status" id="dttc-save-status" aria-live="polite"></span>
                </div>
            </div>

            <div class="dttc-ws-editor" id="dttc-editor">
                <?php
                // Reuse existing single chart shortcode UI inside workspace.
                echo do_shortcode( '[dotank_table_chart id="workspace" title="" rows="10" combined_label="Series"]' );
                ?>
            </div>

            <div class="dttc-ws-notes">
                <label for="dttc-chart-notes"><?php echo esc_html__( 'Notes', 'do-tank-table-chart' ); ?></label>
                <textarea id="dttc-chart-notes" rows="6" placeholder="<?php echo esc_attr__( 'Add notes about this chart…', 'do-tank-table-chart' ); ?>"></textarea>
            </div>
        </div>

        <!-- Tag Member Modal -->
        <div class="dttc-tag-modal-overlay" id="dttc-tag-overlay" style="display:none;">
            <div class="dttc-tag-modal">
                <div class="dttc-tag-modal-header">
                    <h3><i class="fas fa-user-tag"></i> <?php echo esc_html__( 'Tag Members', 'do-tank-table-chart' ); ?></h3>
                    <button type="button" class="dttc-tag-modal-close" id="dttc-tag-close">&times;</button>
                </div>
                <div class="dttc-tag-modal-body">
                    <div class="dttc-tag-search-wrap">
                        <input type="text" id="dttc-tag-search" placeholder="<?php echo esc_attr__( 'Search members by name…', 'do-tank-table-chart' ); ?>" autocomplete="off" />
                        <i class="fas fa-search dttc-tag-search-icon"></i>
                        <div class="dttc-tag-results" id="dttc-tag-results"></div>
                    </div>
                    <div class="dttc-tag-current" id="dttc-tag-current">
                        <h4><?php echo esc_html__( 'Tagged Members', 'do-tank-table-chart' ); ?></h4>
                        <div class="dttc-tag-list" id="dttc-tag-list"></div>
                        <div class="dttc-tag-empty" id="dttc-tag-empty"><?php echo esc_html__( 'No members tagged yet.', 'do-tank-table-chart' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

add_shortcode( 'dttc_chart_workspace', 'dttc_workspace_shortcode' );
