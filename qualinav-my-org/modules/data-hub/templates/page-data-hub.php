<?php
/**
 * Template Name: Data Hub
 * Description: Unified Data page — Data Management, Dashboard Reports, and Run Charts in one tabbed view
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="dh-wrap">

    <!-- HEADER -->
    <header class="dh-header">
        <a href="<?php echo esc_url( home_url( '/my-org/' ) ); ?>" class="dh-back-circle" aria-label="Back to My Org" title="Back to My Org">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
        <h1 class="dh-title">
            <i class="fas fa-database"></i> Data Hub
        </h1>
    </header>

    <!-- TAB BAR -->
    <nav class="dh-tabs">
        <button class="dh-tab is-active" data-tab="dm">
            <i class="fas fa-folder-open"></i> Data Management
        </button>
        <button class="dh-tab" data-tab="dashboard">
            <i class="fas fa-chart-pie"></i> Dashboard Reports
        </button>
    </nav>

    <!-- TAB CONTENT: Data Management -->
    <div class="dh-panel" id="dh-panel-dm">
        <?php
        $GLOBALS['dh_embed_mode'] = true;
        include dirname( __FILE__ ) . '/page-data-management.php';
        unset( $GLOBALS['dh_embed_mode'] );
        ?>
    </div>

    <!-- TAB CONTENT: Dashboard Reports -->
    <div class="dh-panel" id="dh-panel-dashboard" style="display:none;">
        <?php echo do_shortcode( '[qaqd_dashboard]' ); ?>
    </div>


</div><!-- /.dh-wrap -->

<!-- Tab Switcher -->
<script>
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        var tabs = document.querySelectorAll('.dh-tab');
        var panels = document.querySelectorAll('.dh-panel');

        function switchTab(tabId) {
            tabs.forEach(function(t) { t.classList.toggle('is-active', t.getAttribute('data-tab') === tabId); });
            panels.forEach(function(p) { p.style.display = p.id === 'dh-panel-' + tabId ? '' : 'none'; });
            if (history.replaceState) history.replaceState(null, '', '#' + tabId);
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                switchTab(this.getAttribute('data-tab'));
            });
        });

        // Honor explicit deep links such as /data-hub/#dashboard, but default
        // plain /data-hub/ visits to Data Management every time.
        var hash = window.location.hash.replace('#', '');
        try { localStorage.removeItem('dh-active-tab'); } catch (e) {}
        var initial = hash || 'dm';
        if (['dm', 'dashboard'].indexOf(initial) !== -1) {
            switchTab(initial);
        }
    });
})();
</script>

<?php get_footer(); ?>
