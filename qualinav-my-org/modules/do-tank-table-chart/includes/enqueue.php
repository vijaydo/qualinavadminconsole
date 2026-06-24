<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', function () {
    if (!wp_style_is('qualinav-font-awesome', 'enqueued')) {
        wp_enqueue_style('qualinav-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
    }

    wp_register_style(
        'dttc-style',
        DTTC_URL . 'assets/css/dttc.css',
        array(),
        DTTC_VERSION
    );

    wp_register_script(
        'dttc-chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        array(),
        '4.4.1',
        true
    );

    wp_register_script(
        'dttc-jspdf',
        'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
        array(),
        '2.5.1',
        true
    );

    wp_register_script(
        'dttc-script',
        DTTC_URL . 'assets/js/dttc.js',
        array( 'dttc-chartjs', 'dttc-jspdf' ),
        DTTC_VERSION,
        true
    );

    wp_register_script(
        'dttc-public',
        DTTC_URL . 'assets/js/dttc-public.js',
        array( 'dttc-chartjs' ),
        DTTC_VERSION,
        true
    );
} );
