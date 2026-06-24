<?php
/**
 * Template Name: My Org Assessments
 * Description: Unified Assessments page — Readiness Assessment and ORG Assessment
 * in one tabbed view. Served by the Qualinav My Org plugin (standalone; no
 * dependency on qualinav-pages).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

get_header();

$icons_dir = QUALINAV_MY_ORG_PLUGIN_DIR . 'assets/images/icons';
$org_icon  = $icons_dir . '/org-assessment.svg';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Assessments — Unified tabbed page (mirrors Data Hub structure) */
.as-wrap {
    max-width: none;
    margin: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
}

/* Strip theme containers so the page fills the platform content area
   edge-to-edge. The My Org plugin adds the `page-org-assessments` body class. */
body.page-org-assessments .gv-main { padding: 0 !important; }
body.page-org-assessments .gv-content-wrap {
    max-width: none !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}
body.page-org-assessments .entry-content,
body.page-org-assessments .post-content,
body.page-org-assessments article,
body.page-org-assessments article > div,
body.page-org-assessments article > .entry-content,
body.page-org-assessments main > article,
body.page-org-assessments main > .entry-content,
body.page-org-assessments #primary,
body.page-org-assessments #content > article,
body.page-org-assessments .site-main > article {
    max-width: none !important;
    width: auto !important;
    padding: 0 !important;
    margin: 0 !important;
}
body.page-org-assessments .entry-title,
body.page-org-assessments .page-title,
body.page-org-assessments .post-title,
body.page-org-assessments header.entry-header,
body.page-org-assessments header.entry-header h1 {
    display: none !important;
}

.as-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 32px;
    background: #d0f5f9;
    margin-top: 0;
}

.as-back-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(3, 40, 62, 0.1);
    color: #03283E;
    text-decoration: none;
    flex-shrink: 0;
    transition: background 0.15s, transform 0.15s;
}
.as-back-circle:hover { background: rgba(3, 40, 62, 0.18); transform: translateX(-2px); color: #03283E; text-decoration: none; }
.as-back-circle:focus { outline: 2px solid #03283E; outline-offset: 2px; }

.as-title {
    font-size: 18px;
    font-weight: 800;
    color: #03283E;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.as-title svg { width: 20px; height: 20px; }
.as-title-divider { color: rgba(3, 40, 62, 0.2); font-weight: 300; margin: 0 4px; }
.as-title-desc { font-size: 13px; font-weight: 500; color: rgba(3, 40, 62, 0.6); }

.as-tabs {
    display: flex;
    gap: 0;
    background: #03283E;
    padding: 0 24px;
    border-bottom: 2px solid rgba(166, 231, 242, 0.15);
}

.as-tab {
    padding: 14px 24px;
    font-size: 13px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.5);
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    margin-bottom: -2px;
}
.as-tab:hover { color: rgba(255, 255, 255, 0.8); background: rgba(255, 255, 255, 0.03); }
.as-tab.is-active { color: #ffffff; border-bottom-color: #A6E7F2; }
.as-tab i { font-size: 14px; }

.as-panel {
    background: #f8fafc;
    min-height: 500px;
}

@media (max-width: 992px) {
    .as-tabs { padding: 0 12px; }
    .as-tab { padding: 12px 16px; font-size: 12px; }
}

@media (max-width: 768px) {
    .as-header { flex-direction: column; gap: 12px; align-items: flex-start; }
    .as-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .as-tab { white-space: nowrap; }
}

/* --- Assessment section header: drop the underline, roomier padding --- */
body.page-org-assessments .cah-section__toggle {
    border-bottom: 0 !important;
    padding: 16px 20px !important;
}

/* The grapevine theme underlines in-content links; keep assessment links
   (and section text) clean. */
body.page-org-assessments .cah-assessment a,
body.page-org-assessments .cah-assessment a:hover,
body.page-org-assessments .cah-assessment a:focus,
body.page-org-assessments .cah-section__toggle {
    text-decoration: none !important;
}

/* --- Breathing room: separate questions from the section header, and
   give each question row roomier padding --- */
body.page-org-assessments .cah-section__panel {
    padding: 12px 6px 6px !important;
}
body.page-org-assessments .cah-qcard {
    padding: 16px 18px !important;
}
</style>

<div class="as-wrap">

    <!-- HEADER -->
    <header class="as-header">
        <a href="<?php echo esc_url( home_url( '/' . Qualinav_My_Org_Settings::MY_ORG_SLUG . '/' ) ); ?>" class="as-back-circle" aria-label="Back to My Org" title="Back to My Org">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
        <h1 class="as-title">
            <?php
            if ( file_exists( $org_icon ) ) {
                echo file_get_contents( $org_icon ); // phpcs:ignore -- trusted local SVG
            }
            ?>
            Assessments
            <span class="as-title-divider">|</span>
            <span class="as-title-desc">Includes both Readiness and Organizational Assessments</span>
        </h1>
    </header>

    <!-- TAB BAR -->
    <nav class="as-tabs">
        <button class="as-tab is-active" data-tab="readiness">
            <i class="fas fa-clipboard-check"></i> Readiness Assessment
        </button>
        <button class="as-tab" data-tab="org">
            <i class="fas fa-sitemap"></i> ORG Assessment
        </button>
    </nav>

    <!-- TAB CONTENT: Readiness Assessment -->
    <div class="as-panel" id="as-panel-readiness">
        <?php echo do_shortcode( '[cah_assessment slug="tjc-readiness-2026"]' ); ?>
    </div>

    <!-- TAB CONTENT: ORG Assessment -->
    <div class="as-panel" id="as-panel-org" style="display:none;">
        <?php echo do_shortcode( '[cah_assessment slug="org-assessment"]' ); ?>
    </div>

</div><!-- /.as-wrap -->

<!-- Tab Switcher -->
<script>
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        var tabs = document.querySelectorAll('.as-tab');
        var panels = document.querySelectorAll('.as-panel');

        function switchTab(tabId) {
            tabs.forEach(function(t) { t.classList.toggle('is-active', t.getAttribute('data-tab') === tabId); });
            panels.forEach(function(p) { p.style.display = p.id === 'as-panel-' + tabId ? '' : 'none'; });
            localStorage.setItem('as-active-tab', tabId);
            if (history.replaceState) history.replaceState(null, '', '#' + tabId);
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                switchTab(this.getAttribute('data-tab'));
            });
        });

        var hash = window.location.hash.replace('#', '');
        var saved = localStorage.getItem('as-active-tab');
        var initial = hash || saved || 'readiness';
        if (['readiness', 'org'].indexOf(initial) !== -1) {
            switchTab(initial);
        }
    });
})();
</script>

<?php get_footer(); ?>
