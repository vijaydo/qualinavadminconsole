<?php
/**
 * Custom post type for saved charts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dttc_register_chart_cpt() : void {
    $labels = array(
        'name'          => __( 'Charts', 'do-tank-table-chart' ),
        'singular_name' => __( 'Chart', 'do-tank-table-chart' ),
    );

    register_post_type(
        'dttc_chart',
        array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'supports'            => array( 'title', 'author' ),
            // Custom capabilities so we can grant chart access to all logged-in roles
            // without opening up core "post" capabilities.
            'capability_type'     => array( 'dttc_chart', 'dttc_charts' ),
            'map_meta_cap'        => true,
            'exclude_from_search' => true,
            'query_var'           => false,
            'rewrite'             => false,
        )
    );
}

add_action( 'init', 'dttc_register_chart_cpt' );
