<?php
/**
 * Template Name: My Org (Hub)
 * Description: Organization hub — tile launcher driven by My Org settings.
 *
 * Served by the Qualinav My Org plugin for the /my-org/ page. Tiles are
 * generated from Qualinav_My_Org_Settings (only enabled sections appear);
 * label, slug and description are admin-configurable.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

get_header();

$icons_dir   = QUALINAV_MY_ORG_PLUGIN_DIR . 'assets/images/icons';
$sections    = Qualinav_My_Org_Settings::all();

// --- Stats (best-effort; guarded so a missing module never fatals) ---
global $wpdb;
$myorg_user_id  = (int) get_current_user_id();
$myorg_org_name = '';
if ( function_exists( 'qualinav_data_hub_get_org_context' ) ) {
    $myorg_org_context = qualinav_data_hub_get_org_context( $myorg_user_id );
    $myorg_org_name    = trim( (string) ( $myorg_org_context['org_name'] ?? '' ) );
}
if ( '' === $myorg_org_name && $myorg_user_id > 0 ) {
    $myorg_org_name = trim( (string) $wpdb->get_var( $wpdb->prepare(
        "SELECT o.name
           FROM {$wpdb->users} u
           LEFT JOIN {$wpdb->prefix}organizations o ON o.id = u.organization_id
          WHERE u.ID = %d
          LIMIT 1",
        $myorg_user_id
    ) ) );
}
if ( '' === $myorg_org_name && $myorg_user_id > 0 ) {
    $myorg_org_name = trim( (string) get_user_meta( $myorg_user_id, 'organization', true ) );
}
$myorg_header_label = '' !== $myorg_org_name ? $myorg_org_name : 'My Org';
$myorg_documents_label = '' !== $myorg_org_name ? sprintf( '%s Files', $myorg_org_name ) : 'Organisation Files';
$shared_chart_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
     WHERE p.post_type   = 'dttc_chart'
       AND pm.meta_key   = 'dttc_chart_shared'
       AND pm.meta_value = '1'"
);

$quality_metric_count = 0;
$latest_report = get_posts( array(
    'post_type'      => 'qd_report',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => array(
        'relation' => 'OR',
        array( 'key' => '_qd_report_type', 'value' => 'assessment', 'compare' => '!=' ),
        array( 'key' => '_qd_report_type', 'compare' => 'NOT EXISTS' ),
    ),
    'fields' => 'ids',
) );
if ( ! empty( $latest_report ) ) {
    $metrics_arr = json_decode( get_post_meta( $latest_report[0], '_qd_report_metrics', true ), true );
    if ( is_array( $metrics_arr ) ) {
        $quality_metric_count = count( $metrics_arr );
    }
}

$org_assessment_avg = '&mdash;';
$readiness_avg      = '&mdash;';
if ( class_exists( '\\CAH_Assess\\Storage' ) ) {
    $org_value       = \CAH_Assess\Storage::get_average_overall( 'org-assessment' );
    $readiness_value = \CAH_Assess\Storage::get_average_overall( 'tjc-readiness-2026' );
    if ( null !== $org_value ) {
        $org_assessment_avg = number_format_i18n( $org_value, 2 );
    }
    if ( null !== $readiness_value ) {
        $readiness_avg = number_format_i18n( $readiness_value, 2 );
    }
}

$qi_project_count = post_type_exists( 'qi_project' ) ? (int) wp_count_posts( 'qi_project' )->publish : 0;

// --- Smart Scout suggestions ---
$scout_suggestions = array();
if ( $shared_chart_count > 0 ) {
    $scout_suggestions[] = "Your organization has <strong>{$shared_chart_count} public chart" . ( $shared_chart_count > 1 ? 's' : '' ) . "</strong>. Review them to identify new quality improvement opportunities.";
} else {
    $scout_suggestions[] = "Your organization hasn't published any public charts. Share your insights to keep stakeholders informed.";
}
if ( $quality_metric_count > 0 ) {
    $scout_suggestions[] = "You have <strong>{$quality_metric_count} metrics</strong> in your latest Quality Dashboard. Ensure they align with your strategic goals.";
} else {
    $scout_suggestions[] = "No recent Quality Dashboard metrics found. Generate a new report to maintain oversight.";
}
if ( 0 === $qi_project_count ) {
    $scout_suggestions[] = "Your team hasn't started any <strong>QI Projects</strong>. Coordinate a new initiative to improve organizational performance!";
} else {
    $scout_suggestions[] = "Review the <strong>ORG Assessment</strong> dashboard to discover action areas that will keep the plate spinning proactively.";
}
if ( count( $scout_suggestions ) < 3 ) {
    $scout_suggestions[] = "It's always a good time to review your Readiness assessment to stay prepared.";
}
$display_suggestions_org = array_slice( $scout_suggestions, 0, 3 );

$scout_logo_url = get_option( 'roundtable_logo_url', '' );
$scout_fallback = QUALINAV_MY_ORG_PLUGIN_URL . 'assets/images/icons/scout_agent.png';
$scout_img      = $scout_logo_url ? $scout_logo_url : $scout_fallback;

$myorg_tile_matches_label = static function( $section, $labels ) {
    $section_label = strtolower( trim( (string) ( $section['label'] ?? '' ) ) );
    foreach ( (array) $labels as $label ) {
        if ( $section_label === strtolower( trim( (string) $label ) ) ) {
            return true;
        }
    }
    return false;
};

$myorg_primary_sections = array();
$myorg_extra_sections = array();
$myorg_used_section_keys = array();
$myorg_tile_order = array(
    array( 'keys' => array( 'data_hub' ), 'labels' => array( 'Data Hub' ) ),
    array( 'keys' => array( 'qi_projects' ), 'labels' => array( 'QI Projects' ) ),
    array( 'keys' => array( 'assessments' ), 'labels' => array( 'Assessments' ) ),
    array( 'keys' => array( 'regulatory_readiness' ), 'labels' => array( 'Regulatory Readiness' ) ),
    array( 'keys' => array( 'safety_huddles' ), 'labels' => array( 'Safety Huddles' ) ),
);

foreach ( $myorg_tile_order as $tile_target ) {
    foreach ( $sections as $section_key => $section ) {
        if ( empty( $section['enabled'] ) || isset( $myorg_used_section_keys[ $section_key ] ) ) {
            continue;
        }
        $key_match   = in_array( $section_key, $tile_target['keys'], true );
        $label_match = $myorg_tile_matches_label( $section, $tile_target['labels'] );
        if ( $key_match || $label_match ) {
            $myorg_primary_sections[ $section_key ] = $section;
            $myorg_used_section_keys[ $section_key ] = true;
            break;
        }
    }
}

foreach ( $sections as $section_key => $section ) {
    if ( empty( $section['enabled'] ) || isset( $myorg_used_section_keys[ $section_key ] ) ) {
        continue;
    }
    $myorg_extra_sections[ $section_key ] = $section;
    $myorg_used_section_keys[ $section_key ] = true;
}

$myorg_can_access_org_setup = class_exists( 'QN_Permissions' )
    && QN_Permissions::user_can( get_current_user_id(), 'access_hospital_console' )
    && ! QN_Permissions::user_can( get_current_user_id(), 'access_super_admin' );
$myorg_org_setup_href = home_url( '/qualinav' );

$myorg_render_tile = static function( $sec ) use ( $icons_dir ) {
    $icon_path = ! empty( $sec['icon'] ) ? $icons_dir . '/' . $sec['icon'] : '';
    ?>
    <a href="<?php echo esc_url( $sec['href'] ); ?>" class="myorg-tile">
        <div class="myorg-tile-body">
            <div class="myorg-tile-icon">
                <?php
                if ( $icon_path && file_exists( $icon_path ) ) {
                    echo file_get_contents( $icon_path ); // phpcs:ignore -- trusted local SVG
                } else {
                    echo '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#03283E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect></svg>';
                }
                ?>
            </div>
            <div class="myorg-tile-title"><?php echo esc_html( $sec['label'] ); ?></div>
            <div class="myorg-tile-desc"><?php echo esc_html( $sec['description'] ); ?></div>
        </div>
    </a>
    <?php
};
?>

<div class="myorg-dashboard">

    <!-- Top Bar -->
    <?php
    // Mirror the side-menu icon: when the My Org menu icon is changed in
    // the menu admin it flows through here too. Falls back to the globe
    // (language) when grapevine is inactive or no icon has been set.
    $myorg_topbar_icon = 'language';
    if ( function_exists( 'dtam_get_menu_icon_for_url' ) ) {
        $resolved_icon = dtam_get_menu_icon_for_url( home_url( '/my-org/' ) );
        if ( $resolved_icon !== '' ) {
            $myorg_topbar_icon = $resolved_icon;
        }
    }
    ?>
    <div class="myorg-dashboard-topbar">
        <h1 class="gv-header-with-desc">
            <span class="gv-header-title-part">
                <span class="material-icons-outlined" style="font-size: 24px;"><?php echo esc_html( $myorg_topbar_icon ); ?></span> <?php echo esc_html( $myorg_header_label ); ?>
            </span>
        </h1>
        <button type="button" class="myorg-documents-button" onclick="myOrgOpenDocumentsModal();">
            <span class="material-icons-outlined" aria-hidden="true">folder_open</span>
            <span class="myorg-documents-button-label"><?php echo esc_html( $myorg_documents_label ); ?></span>
        </button>
    </div>

    <!-- Stats Bar -->
    <div class="myorg-stats-bar">
        <div class="myorg-stat-card">
            <div class="myorg-stat-value"><?php echo $quality_metric_count > 0 ? esc_html( $quality_metric_count ) : '&mdash;'; ?></div>
            <div class="myorg-stat-label">Quality</div>
            <div class="myorg-stat-desc">Dashboard metrics</div>
        </div>
        <div class="myorg-stat-card">
            <div class="myorg-stat-value"><?php echo esc_html( $shared_chart_count ); ?></div>
            <div class="myorg-stat-label">Charts</div>
            <div class="myorg-stat-desc">Public organization charts</div>
        </div>
        <div class="myorg-stat-card">
            <div class="myorg-stat-value"><?php echo wp_kses_post( $org_assessment_avg ); ?></div>
            <div class="myorg-stat-label">Assessment</div>
            <div class="myorg-stat-desc">Survey results</div>
        </div>
        <div class="myorg-stat-card">
            <div class="myorg-stat-value"><?php echo wp_kses_post( $readiness_avg ); ?></div>
            <div class="myorg-stat-label">Readiness</div>
            <div class="myorg-stat-desc">TJC &amp; survey readiness</div>
        </div>
        <div class="myorg-stat-card">
            <div class="myorg-stat-value"><?php echo $qi_project_count > 0 ? esc_html( $qi_project_count ) : '0'; ?></div>
            <div class="myorg-stat-label">QI Projects</div>
            <div class="myorg-stat-desc">Quality improvement</div>
        </div>
    </div>

    <!-- Tiles Grid -->
    <div class="myorg-tiles-grid">

        <?php foreach ( $myorg_primary_sections as $sec ) : ?>
            <?php $myorg_render_tile( $sec ); ?>
        <?php endforeach; ?>

        <?php if ( $myorg_can_access_org_setup ) : ?>
            <a href="<?php echo esc_url( $myorg_org_setup_href ); ?>" class="myorg-tile myorg-organization-setup-tile" target="_blank" rel="noopener noreferrer">
                <div class="myorg-tile-body">
                    <div class="myorg-tile-icon">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#03283E" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M4 20h16"></path>
                            <path d="M6 20V6.5A1.5 1.5 0 0 1 7.5 5h5A1.5 1.5 0 0 1 14 6.5V20"></path>
                            <path d="M14 9h2.5A1.5 1.5 0 0 1 18 10.5V20"></path>
                            <path d="M8.5 8.5h3"></path>
                            <path d="M8.5 12h3"></path>
                            <path d="M8.5 15.5h3"></path>
                            <path d="M15.5 13h1"></path>
                            <path d="M15.5 16h1"></path>
                        </svg>
                    </div>
                    <div class="myorg-tile-title">Organization Setup</div>
                    <div class="myorg-tile-desc">Open the hospital setup console to review organization setup, workspace details, and assigned configuration.</div>
                </div>
            </a>
        <?php endif; ?>

        <?php foreach ( $myorg_extra_sections as $sec ) : ?>
            <?php $myorg_render_tile( $sec ); ?>
        <?php endforeach; ?>

    </div>

</div>

<div class="myorg-documents-modal" id="myorgDocumentsModal" aria-hidden="true">
    <div class="myorg-documents-modal-scrim" onclick="myOrgCloseDocumentsModal();" aria-hidden="true"></div>
    <section class="myorg-documents-modal-surface" role="dialog" aria-modal="true" aria-labelledby="myorgDocumentsModalTitle">
        <header class="myorg-documents-modal-header">
            <div class="myorg-documents-modal-title-group">
                <span class="myorg-documents-modal-icon material-icons-outlined" aria-hidden="true">folder_open</span>
                <div>
                    <h2 id="myorgDocumentsModalTitle"><?php echo esc_html( $myorg_documents_label ); ?></h2>
                </div>
            </div>
            <button type="button" class="myorg-documents-modal-close" onclick="myOrgCloseDocumentsModal();" aria-label="Close documents modal">
                <span class="material-icons-outlined" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="myorg-documents-modal-body">
            <iframe title="Document upload portal" src="<?php echo esc_url( add_query_arg( 'embedded', '1', home_url( '/qualinav-org-documents-embed/' ) ) ); ?>" loading="lazy"></iframe>
        </div>
    </section>
</div>

<script>
    (function() {
        var lastFocusedElement = null;

        window.myOrgOpenDocumentsModal = function() {
            var modal = document.getElementById('myorgDocumentsModal');
            if (!modal) {
                return;
            }
            lastFocusedElement = document.activeElement;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('myorg-modal-open');
            window.setTimeout(function() {
                var closeButton = modal.querySelector('.myorg-documents-modal-close');
                if (closeButton) {
                    closeButton.focus();
                }
            }, 0);
        };

        window.myOrgCloseDocumentsModal = function() {
            var modal = document.getElementById('myorgDocumentsModal');
            if (!modal) {
                return;
            }
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('myorg-modal-open');
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        };

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                window.myOrgCloseDocumentsModal();
            }
        });
    })();
</script>

<?php get_footer(); ?>
