<?php
/**
 * REST API endpoints for Do Tank Table Chart workspace.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DTTC_PATH . 'includes/db.php';

function dttc_rest_require_user() {
    if ( ! is_user_logged_in() ) {
        return new WP_Error( 'dttc_auth', __( 'You must be logged in.', 'do-tank-table-chart' ), array( 'status' => 401 ) );
    }
    return true;
}

function dttc_rest_can_edit_chart( int $chart_id ) {
    if ( ! current_user_can( 'edit_post', $chart_id ) ) {
        return new WP_Error( 'dttc_forbidden', __( 'You do not have permission to edit this chart.', 'do-tank-table-chart' ), array( 'status' => 403 ) );
    }
    return true;
}

function dttc_rest_can_view_chart( int $chart_id ) {
    // Owner can always view.
    if ( current_user_can( 'edit_post', $chart_id ) ) {
        return true;
    }

    // Check if current user is tagged on this chart.
    $user_id  = get_current_user_id();
    $existing = get_post_meta( $chart_id, 'dttc_chart_tagged_for', false );
    if ( in_array( (string) $user_id, $existing, true ) || in_array( $user_id, $existing, false ) ) {
        return true;
    }

    return new WP_Error( 'dttc_forbidden', __( 'You do not have permission to view this chart.', 'do-tank-table-chart' ), array( 'status' => 403 ) );
}

function dttc_sanitize_settings( $settings ) : array {
    if ( ! is_array( $settings ) ) {
        return array();
    }

    $clean = array();
    $allowed_keys = array(
        'legend',
        'showMedian',
        'showMinMax',
        'seriesColor',
        'medianColor',
        'minColor',
        'maxColor',
    );

    foreach ( $allowed_keys as $key ) {
        if ( array_key_exists( $key, $settings ) ) {
            $val = $settings[ $key ];
            if ( is_bool( $val ) ) {
                $clean[ $key ] = (bool) $val;
            } elseif ( is_numeric( $val ) ) {
                $clean[ $key ] = $val + 0;
            } else {
                $clean[ $key ] = sanitize_text_field( (string) $val );
            }
        }
    }

    return $clean;
}

function dttc_rest_list_charts( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $user_id = get_current_user_id();

    $args = array(
        'post_type'      => 'dttc_chart',
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'orderby'        => array(
            'menu_order' => 'ASC',
            'modified'   => 'DESC',
        ),
    );

    // Default behaviour (Option 2): users only see their own charts.
    // Admins can request all charts via ?all=1.
    $want_all = (int) $request->get_param( 'all' ) === 1;
    if ( ! ( $want_all && current_user_can( 'manage_options' ) ) ) {
        $args['author'] = $user_id;
    }

    $posts = get_posts( $args );

    $out = array();
    $own_chart_ids = array();
    foreach ( $posts as $p ) {
        $own_chart_ids[] = (int) $p->ID;
        $out[] = array(
            'id'          => (int) $p->ID,
            'title'       => (string) $p->post_title,
            'created_gmt' => (string) $p->post_date_gmt,
            'modified_gmt'=> (string) $p->post_modified_gmt,
            'menu_order'  => (int) $p->menu_order,
            'shared'      => get_post_meta( (int) $p->ID, 'dttc_chart_shared', true ) === '1',
            'is_shared'   => false,
            'owner_id'    => (int) $p->post_author,
            'owner_name'  => '',
        );
    }

    // Also include charts where the current user is tagged (shared with them).
    $tagged_args = array(
        'post_type'      => 'dttc_chart',
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'meta_query'     => array(
            array(
                'key'     => 'dttc_chart_tagged_for',
                'value'   => $user_id,
                'compare' => '=',
            ),
        ),
        'orderby'        => 'modified',
        'order'          => 'DESC',
    );

    // Exclude own charts (avoid duplicates).
    if ( ! empty( $own_chart_ids ) ) {
        $tagged_args['post__not_in'] = $own_chart_ids;
    }

    $tagged_posts = get_posts( $tagged_args );

    foreach ( $tagged_posts as $p ) {
        $owner = get_userdata( (int) $p->post_author );
        $out[] = array(
            'id'          => (int) $p->ID,
            'title'       => (string) $p->post_title,
            'created_gmt' => (string) $p->post_date_gmt,
            'modified_gmt'=> (string) $p->post_modified_gmt,
            'menu_order'  => (int) $p->menu_order,
            'shared'      => get_post_meta( (int) $p->ID, 'dttc_chart_shared', true ) === '1',
            'is_shared'   => true,
            'owner_id'    => (int) $p->post_author,
            'owner_name'  => $owner ? $owner->display_name : '',
        );
    }

    return rest_ensure_response( array( 'charts' => $out ) );
}

function dttc_rest_reorder_charts( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $ids = $request->get_param( 'ids' );
    if ( ! is_array( $ids ) ) {
        return new WP_Error( 'invalid_data', 'Missing IDs array', array( 'status' => 400 ) );
    }

    foreach ( $ids as $index => $id ) {
        $id = (int)$id;
        if ( ! current_user_can( 'edit_post', $id ) ) {
            continue;
        }
        wp_update_post( array(
            'ID'         => $id,
            'menu_order' => $index,
        ) );
    }

    return rest_ensure_response( array( 'ok' => true ) );
}

function dttc_rest_create_chart( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $title = $request->get_param( 'title' );
    $title = $title ? sanitize_text_field( (string) $title ) : '';

    if ( $title === '' ) {
        $title = __( 'New Chart', 'do-tank-table-chart' );
    }

    $chart_id = wp_insert_post(
        array(
            'post_type'   => 'dttc_chart',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => get_current_user_id(),
        ),
        true
    );

    if ( is_wp_error( $chart_id ) ) {
        return $chart_id;
    }

    // Seed default meta.
    update_post_meta( $chart_id, 'dttc_chart_notes', '' );
    update_post_meta( $chart_id, 'dttc_chart_settings', wp_json_encode( array() ) );

    return rest_ensure_response(
        array(
            'id'    => (int) $chart_id,
            'title' => $title,
        )
    );
}

function dttc_rest_get_chart( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];

    // Allow view for owner OR tagged users.
    $perm = dttc_rest_can_view_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    $post = get_post( $chart_id );
    if ( ! $post || $post->post_type !== 'dttc_chart' ) {
        return new WP_Error( 'dttc_not_found', __( 'Chart not found.', 'do-tank-table-chart' ), array( 'status' => 404 ) );
    }

    $notes = (string) get_post_meta( $chart_id, 'dttc_chart_notes', true );

    $state_json = (string) get_post_meta( $chart_id, 'dttc_chart_state', true );
    $state = json_decode( $state_json, true );
    if ( ! is_array( $state ) ) {
        $state = array();
    }

    $rows = dttc_get_chart_rows( $chart_id );

    $shared = get_post_meta( $chart_id, 'dttc_chart_shared', true ) === '1';

    // Check if this chart is shared with the current user (not owned by them).
    $user_id   = get_current_user_id();
    $is_shared = ( (int) $post->post_author !== $user_id );
    $owner     = get_userdata( (int) $post->post_author );

    return rest_ensure_response(
        array(
            'id'         => (int) $chart_id,
            'title'      => (string) $post->post_title,
            'notes'      => $notes,
            'rows'       => $rows,
            'state'      => $state,
            'shared'     => $shared,
            'is_shared'  => $is_shared,
            'owner_id'   => (int) $post->post_author,
            'owner_name' => $owner ? $owner->display_name : '',
        )
    );
}

function dttc_rest_save_chart( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];

    $perm = dttc_rest_can_edit_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    $post = get_post( $chart_id );
    if ( ! $post || $post->post_type !== 'dttc_chart' ) {
        return new WP_Error( 'dttc_not_found', __( 'Chart not found.', 'do-tank-table-chart' ), array( 'status' => 404 ) );
    }

    $title = $request->get_param( 'title' );
    if ( $title !== null ) {
        $title = sanitize_text_field( (string) $title );
        if ( $title !== '' ) {
            wp_update_post( array( 'ID' => $chart_id, 'post_title' => $title ) );
        }
    }

    $notes = $request->get_param( 'notes' );
    if ( $notes !== null ) {
        update_post_meta( $chart_id, 'dttc_chart_notes', sanitize_textarea_field( (string) $notes ) );
    }

    // Optional: save full UI state (legend/colors/toggles/titles + rows) for easy restore.
    $state = $request->get_param( 'state' );
    if ( is_array( $state ) ) {
        $clean_state = array();
        // Rows
        $clean_rows = array();
        if ( isset( $state['rows'] ) && is_array( $state['rows'] ) ) {
            $i = 0;
            foreach ( $state['rows'] as $r ) {
                if ( ! is_array( $r ) ) {
                    continue;
                }
                $i++;
                $clean_rows[] = array(
                    'label' => isset( $r['label'] ) ? sanitize_text_field( (string) $r['label'] ) : ( 'Row ' . $i ),
                    'a'     => array_key_exists( 'a', $r ) && $r['a'] !== '' && $r['a'] !== null ? (float) $r['a'] : null,
                    'b'     => array_key_exists( 'b', $r ) && $r['b'] !== '' && $r['b'] !== null ? (float) $r['b'] : null,
                );
            }
        }
        $clean_state['rows'] = $clean_rows;

        // Legend
        if ( isset( $state['legend'] ) && is_array( $state['legend'] ) ) {
            $clean_state['legend'] = array(
                'combined' => isset( $state['legend']['combined'] ) ? sanitize_text_field( (string) $state['legend']['combined'] ) : 'Series',
            );
        }

        // Colors
        if ( isset( $state['colors'] ) && is_array( $state['colors'] ) ) {
            $clean_state['colors'] = array(
                'series'  => isset( $state['colors']['series'] ) ? sanitize_text_field( (string) $state['colors']['series'] ) : '#2563eb',
                'average' => isset( $state['colors']['average'] ) ? sanitize_text_field( (string) $state['colors']['average'] ) : '#f97316',
            );
        }

        // Chart titles
        if ( isset( $state['chart'] ) && is_array( $state['chart'] ) ) {
            $clean_state['chart'] = array(
                'title'  => isset( $state['chart']['title'] ) ? sanitize_text_field( (string) $state['chart']['title'] ) : '',
                'xTitle' => isset( $state['chart']['xTitle'] ) ? sanitize_text_field( (string) $state['chart']['xTitle'] ) : '',
                'yTitle' => isset( $state['chart']['yTitle'] ) ? sanitize_text_field( (string) $state['chart']['yTitle'] ) : '',
            );
        }

        // Toggles
        if ( isset( $state['toggles'] ) && is_array( $state['toggles'] ) ) {
            $clean_state['toggles'] = array(
                'minmax' => ! empty( $state['toggles']['minmax'] ),
            );
        }

        update_post_meta( $chart_id, 'dttc_chart_state', wp_json_encode( $clean_state ) );
    }

    // Rows table (Option A)
    $rows = $request->get_param( 'rows' );
    if ( ! is_array( $rows ) ) {
        $rows = array();
    }

    $clean_rows_for_table = array();
    $idx = 0;
    foreach ( $rows as $r ) {
        $idx++;
        if ( ! is_array( $r ) ) {
            continue;
        }
        $clean_rows_for_table[] = array(
            'row_index'   => isset( $r['row_index'] ) ? (int) $r['row_index'] : $idx,
            'time_label'  => isset( $r['time_label'] ) ? sanitize_text_field( (string) $r['time_label'] ) : '',
            'numerator'   => array_key_exists( 'numerator', $r ) && $r['numerator'] !== '' && $r['numerator'] !== null ? (float) $r['numerator'] : null,
            'denominator' => array_key_exists( 'denominator', $r ) && $r['denominator'] !== '' && $r['denominator'] !== null ? (float) $r['denominator'] : null,
        );
    }

    dttc_replace_chart_rows( $chart_id, $clean_rows_for_table );

    return rest_ensure_response( array( 'ok' => true, 'id' => (int) $chart_id ) );
}

function dttc_rest_delete_chart( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];

    $perm = dttc_rest_can_edit_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    $post = get_post( $chart_id );
    if ( ! $post || $post->post_type !== 'dttc_chart' ) {
        return new WP_Error( 'dttc_not_found', __( 'Chart not found.', 'do-tank-table-chart' ), array( 'status' => 404 ) );
    }

    // By default, move to Trash (keeps rows in case of restore).
    $force = $request->get_param( 'force' );
    if ( $force ) {
        $deleted = wp_delete_post( $chart_id, true );
        if ( ! $deleted ) {
            return new WP_Error( 'dttc_delete_failed', __( 'Could not delete chart.', 'do-tank-table-chart' ), array( 'status' => 500 ) );
        }
        return rest_ensure_response( array( 'ok' => true, 'id' => (int) $chart_id, 'deleted' => true, 'trashed' => false ) );
    }

    $trashed = wp_trash_post( $chart_id );
    if ( ! $trashed ) {
        return new WP_Error( 'dttc_delete_failed', __( 'Could not delete chart.', 'do-tank-table-chart' ), array( 'status' => 500 ) );
    }

    return rest_ensure_response( array( 'ok' => true, 'id' => (int) $chart_id, 'deleted' => false, 'trashed' => true ) );
}

function dttc_rest_toggle_share( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];

    $perm = dttc_rest_can_edit_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    $post = get_post( $chart_id );
    if ( ! $post || $post->post_type !== 'dttc_chart' ) {
        return new WP_Error( 'dttc_not_found', __( 'Chart not found.', 'do-tank-table-chart' ), array( 'status' => 404 ) );
    }

    $current = get_post_meta( $chart_id, 'dttc_chart_shared', true );
    $new_val = $current === '1' ? '' : '1';
    update_post_meta( $chart_id, 'dttc_chart_shared', $new_val );

    if ( $new_val === '1' ) {
        update_post_meta( $chart_id, 'dttc_chart_shared_date', current_time( 'mysql', true ) );
    }

    return rest_ensure_response( array(
        'ok'     => true,
        'id'     => (int) $chart_id,
        'shared' => $new_val === '1',
    ) );
}

// ── Tag / Workflow helpers ──

function dttc_get_tagged_users_for_chart( int $chart_id ) : array {
    $user_ids = get_post_meta( $chart_id, 'dttc_chart_tagged_for', false );
    if ( ! is_array( $user_ids ) ) {
        return array();
    }

    $out = array();
    foreach ( $user_ids as $uid ) {
        $uid  = (int) $uid;
        $user = get_userdata( $uid );
        if ( ! $user ) {
            continue;
        }

        $info_json = (string) get_post_meta( $chart_id, 'dttc_chart_tagged_info_' . $uid, true );
        $info      = json_decode( $info_json, true );
        if ( ! is_array( $info ) ) {
            $info = array();
        }

        $assigned_by      = isset( $info['assigned_by'] ) ? get_userdata( (int) $info['assigned_by'] ) : null;
        $assigned_by_name = $assigned_by ? $assigned_by->display_name : '';

        $out[] = array(
            'user_id'          => $uid,
            'display_name'     => $user->display_name,
            'avatar_url'       => get_avatar_url( $uid, array( 'size' => 64 ) ),
            'assigned_by'      => isset( $info['assigned_by'] ) ? (int) $info['assigned_by'] : 0,
            'assigned_by_name' => $assigned_by_name,
            'assigned_date'    => isset( $info['assigned_date'] ) ? $info['assigned_date'] : '',
        );
    }

    return $out;
}

function dttc_rest_search_users( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
    if ( strlen( $search ) < 2 ) {
        return rest_ensure_response( array( 'users' => array() ) );
    }

    $exclude = array( get_current_user_id() );

    $users = get_users( array(
        'search'         => '*' . $search . '*',
        'search_columns' => array( 'display_name', 'user_login', 'user_email' ),
        'number'         => 10,
        'orderby'        => 'display_name',
        'order'          => 'ASC',
        'exclude'        => $exclude,
    ) );

    $out = array();
    foreach ( $users as $u ) {
        $out[] = array(
            'id'           => (int) $u->ID,
            'display_name' => $u->display_name,
            'avatar_url'   => get_avatar_url( $u->ID, array( 'size' => 64 ) ),
        );
    }

    return rest_ensure_response( array( 'users' => $out ) );
}

function dttc_rest_tag_user( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];
    $perm     = dttc_rest_can_edit_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    $post = get_post( $chart_id );
    if ( ! $post || $post->post_type !== 'dttc_chart' ) {
        return new WP_Error( 'dttc_not_found', __( 'Chart not found.', 'do-tank-table-chart' ), array( 'status' => 404 ) );
    }

    $target_user_id = (int) $request->get_param( 'user_id' );
    if ( ! $target_user_id || ! get_userdata( $target_user_id ) ) {
        return new WP_Error( 'dttc_invalid_user', __( 'Invalid user.', 'do-tank-table-chart' ), array( 'status' => 400 ) );
    }

    // Prevent duplicate tags.
    $existing = get_post_meta( $chart_id, 'dttc_chart_tagged_for', false );
    if ( in_array( (string) $target_user_id, $existing, true ) || in_array( $target_user_id, $existing, false ) ) {
        return rest_ensure_response( array(
            'ok'           => true,
            'tagged_users' => dttc_get_tagged_users_for_chart( $chart_id ),
        ) );
    }

    add_post_meta( $chart_id, 'dttc_chart_tagged_for', $target_user_id );
    update_post_meta( $chart_id, 'dttc_chart_tagged_info_' . $target_user_id, wp_json_encode( array(
        'assigned_by'   => get_current_user_id(),
        'assigned_date' => current_time( 'mysql', true ),
    ) ) );

    // Send in-app notification + email to the tagged user.
    $chart_title = get_the_title( $chart_id ) ?: 'Untitled Chart';
    $actor       = wp_get_current_user();
    $actor_name  = $actor->display_name;
    $message     = sprintf( '%s tagged you on chart: %s', $actor_name, $chart_title );
    $link        = home_url( '/my-workflow/' );

    if ( function_exists( 'dttc_create_notification' ) ) {
        dttc_create_notification( $target_user_id, get_current_user_id(), $chart_id, 'chart_tagged', $message, $link );
    }
    if ( function_exists( 'dttc_send_tag_email' ) ) {
        dttc_send_tag_email( $target_user_id, get_current_user_id(), $chart_title );
    }

    return rest_ensure_response( array(
        'ok'           => true,
        'tagged_users' => dttc_get_tagged_users_for_chart( $chart_id ),
    ) );
}

function dttc_rest_untag_user( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];
    $perm     = dttc_rest_can_edit_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    $target_user_id = (int) $request['user_id'];

    delete_post_meta( $chart_id, 'dttc_chart_tagged_for', $target_user_id );
    delete_post_meta( $chart_id, 'dttc_chart_tagged_info_' . $target_user_id );

    return rest_ensure_response( array(
        'ok'           => true,
        'tagged_users' => dttc_get_tagged_users_for_chart( $chart_id ),
    ) );
}

function dttc_rest_get_tagged_users( WP_REST_Request $request ) {
    $auth = dttc_rest_require_user();
    if ( $auth !== true ) {
        return $auth;
    }

    $chart_id = (int) $request['id'];
    $perm     = dttc_rest_can_edit_chart( $chart_id );
    if ( $perm !== true ) {
        return $perm;
    }

    return rest_ensure_response( array(
        'tagged_users' => dttc_get_tagged_users_for_chart( $chart_id ),
    ) );
}

function dttc_register_rest_routes() : void {
    register_rest_route(
        'dttc/v1',
        '/charts',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'dttc_rest_list_charts',
                'permission_callback' => function () { return is_user_logged_in(); },
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'dttc_rest_create_chart',
                'permission_callback' => function () { return is_user_logged_in(); },
            ),
        )
    );

    register_rest_route(
        'dttc/v1',
        '/charts/reorder',
        array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'dttc_rest_reorder_charts',
                'permission_callback' => function () { return is_user_logged_in(); },
            ),
        )
    );

    register_rest_route(
        'dttc/v1',
        '/charts/(?P<id>\d+)',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'dttc_rest_get_chart',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    if ( ! is_user_logged_in() ) {
                        return false;
                    }
                    // Owner can view.
                    if ( current_user_can( 'edit_post', $id ) ) {
                        return true;
                    }
                    // Tagged users can view.
                    $user_id  = get_current_user_id();
                    $existing = get_post_meta( $id, 'dttc_chart_tagged_for', false );
                    return in_array( (string) $user_id, $existing, true ) || in_array( $user_id, $existing, false );
                },
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'dttc_rest_save_chart',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    return is_user_logged_in() && current_user_can( 'edit_post', $id );
                },
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'dttc_rest_delete_chart',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    return is_user_logged_in() && current_user_can( 'edit_post', $id );
                },
            ),
        )
    );
    register_rest_route(
        'dttc/v1',
        '/charts/(?P<id>\d+)/share',
        array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'dttc_rest_toggle_share',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    return is_user_logged_in() && current_user_can( 'edit_post', $id );
                },
            ),
        )
    );

    // ── Tag / Workflow routes ──

    register_rest_route(
        'dttc/v1',
        '/users',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'dttc_rest_search_users',
                'permission_callback' => function () { return is_user_logged_in(); },
            ),
        )
    );

    register_rest_route(
        'dttc/v1',
        '/charts/(?P<id>\d+)/tags',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'dttc_rest_get_tagged_users',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    return is_user_logged_in() && current_user_can( 'edit_post', $id );
                },
            ),
        )
    );

    register_rest_route(
        'dttc/v1',
        '/charts/(?P<id>\d+)/tag',
        array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'dttc_rest_tag_user',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    return is_user_logged_in() && current_user_can( 'edit_post', $id );
                },
            ),
        )
    );

    register_rest_route(
        'dttc/v1',
        '/charts/(?P<id>\d+)/tag/(?P<user_id>\d+)',
        array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'dttc_rest_untag_user',
                'permission_callback' => function ( WP_REST_Request $r ) {
                    $id = (int) $r['id'];
                    return is_user_logged_in() && current_user_can( 'edit_post', $id );
                },
            ),
        )
    );
}

add_action( 'rest_api_init', 'dttc_register_rest_routes' );
