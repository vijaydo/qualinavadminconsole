<?php
defined('ABSPATH') || exit;

$dtu_request_path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$dtu_request_segments = '' === $dtu_request_path ? [] : explode('/', $dtu_request_path);
$dtu_request_slug = empty($dtu_request_segments) ? '' : (string) end($dtu_request_segments);
$dtu_embedded = (isset($_GET['embedded']) && '1' === (string) $_GET['embedded'])
    || (bool) get_query_var('dtu_embedded_page')
    || 'qualinav-org-documents-embed' === $dtu_request_slug;
if (!$dtu_embedded) {
    get_header();
} else {
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
        <style>
            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fdfbf7 !important;
            }
            body.admin-bar {
                margin-top: 0 !important;
            }
            #wpadminbar {
                display: none !important;
            }
            .dtu-wrapper {
                margin: 24px auto !important;
                width: min(1040px, calc(100vw - 32px)) !important;
            }
        </style>
    </head>
    <body <?php body_class('dtu-embedded-view'); ?>>
    <?php
}
?>

<div class="dtu-wrapper-root">
    <?php
    global $DTU;

    if (isset($DTU->page) && $DTU->page instanceof DTU_Page) {
        $DTU->page->render_page_contents();
    } else {
        echo '<div style="padding:20px;color:red;font-size:16px;">
            DTU_Page instance missing.
        </div>';
    }
    ?>
</div>

<?php
if (!$dtu_embedded) {
    get_footer();
} else {
    wp_footer();
    ?>
    </body>
    </html>
    <?php
}
