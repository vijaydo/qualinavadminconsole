<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function () {
    add_menu_page(
        'Do Tank Table Chart',
        'Do Tank Chart',
        'manage_options',
        'dttc-help',
        'dttc_render_help_page',
        'dashicons-chart-line',
        58
    );
} );

function dttc_render_help_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Do Tank Table Chart</h1>
        <p><strong>Created by Maxim Dascalasu</strong></p>

        <h2>Overview</h2>
        <p>This plugin provides an editable table, automatic medians (Median = median of Numerator) and Rule of 7 analysis on the main series line.

        <h2>Shortcode</h2>
        <code>[dotank_table_chart title="My Table" rows="6"]</code>

        <h2>Security</h2>
        <ul>
            <li>No database writes</li>
            <li>No AJAX endpoints</li>
            <li>No user input stored server-side</li>
            <li>All output escaped</li>
        </ul>

        <h2>Data storage</h2>
        <p>All data is saved locally in the browser using <code>localStorage</code>.</p>
    </div>
    <?php
}
