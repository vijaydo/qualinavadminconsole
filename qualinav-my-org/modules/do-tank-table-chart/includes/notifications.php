<?php
/**
 * Notification system for Do Tank Table Chart.
 *
 * Handles in-app notifications (via qualinav_notifications filter)
 * and email notifications when users are tagged on charts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the notifications table name.
 */
function dttc_get_notifications_table_name() : string {
    global $wpdb;
    return $wpdb->prefix . 'dttc_notifications';
}

/**
 * Create/upgrade the notifications table.
 */
function dttc_install_notifications_table() : void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name      = dttc_get_notifications_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        actor_id BIGINT(20) UNSIGNED NOT NULL,
        chart_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        type VARCHAR(50) NOT NULL DEFAULT 'chart_tagged',
        message TEXT NOT NULL,
        link VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY user_unread (user_id, read_at)
    ) {$charset_collate};";

    dbDelta( $sql );
}

/**
 * Ensure the notifications table exists, auto-create if missing.
 */
function dttc_ensure_notifications_table() : void {
    global $wpdb;

    $table = dttc_get_notifications_table_name();

    // Use a transient to avoid checking on every page load.
    if ( get_transient( 'dttc_notifications_table_ok' ) ) {
        return;
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        dttc_install_notifications_table();
    }

    set_transient( 'dttc_notifications_table_ok', 1, DAY_IN_SECONDS );
}

/**
 * Insert a notification row.
 */
function dttc_create_notification( int $user_id, int $actor_id, int $chart_id, string $type, string $message, string $link ) : void {
    global $wpdb;

    dttc_ensure_notifications_table();

    $wpdb->insert(
        dttc_get_notifications_table_name(),
        array(
            'user_id'  => $user_id,
            'actor_id' => $actor_id,
            'chart_id' => $chart_id,
            'type'     => $type,
            'message'  => $message,
            'link'     => $link,
        ),
        array( '%d', '%d', '%d', '%s', '%s', '%s' )
    );
}

/**
 * Get recent notifications for a user.
 */
function dttc_get_user_notifications( int $user_id, int $limit = 20 ) : array {
    global $wpdb;

    dttc_ensure_notifications_table();

    $table = dttc_get_notifications_table_name();

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE user_id = %d
         ORDER BY created_at DESC
         LIMIT %d",
        $user_id,
        $limit
    ), ARRAY_A );

    return is_array( $rows ) ? $rows : array();
}

/**
 * Mark all unread notifications as read for a user.
 */
function dttc_mark_all_notifications_read( int $user_id ) : int {
    global $wpdb;

    dttc_ensure_notifications_table();

    $table = dttc_get_notifications_table_name();

    return (int) $wpdb->query( $wpdb->prepare(
        "UPDATE {$table} SET read_at = %s WHERE user_id = %d AND read_at IS NULL",
        current_time( 'mysql', true ),
        $user_id
    ) );
}

/**
 * Mark a single notification as read.
 */
function dttc_mark_notification_read( int $notification_id, int $user_id ) : bool {
    global $wpdb;

    dttc_ensure_notifications_table();

    $table = dttc_get_notifications_table_name();

    $result = $wpdb->update(
        $table,
        array( 'read_at' => current_time( 'mysql', true ) ),
        array( 'id' => $notification_id, 'user_id' => $user_id, 'read_at' => null ),
        array( '%s' ),
        array( '%d', '%d', '%s' )
    );

    return $result !== false;
}

/**
 * Send an HTML email notification when a user is tagged on a chart.
 */
function dttc_send_tag_email( int $target_user_id, int $actor_user_id, string $chart_title ) : void {
    $target = get_userdata( $target_user_id );
    $actor  = get_userdata( $actor_user_id );

    if ( ! $target || ! $actor ) {
        return;
    }

    $to           = $target->user_email;
    $first_name   = $target->first_name ?: $target->display_name;
    $actor_name   = $actor->display_name;
    $workflow_url = home_url( '/my-workflow/' );
    $subject      = sprintf( '[QualiNav] %s tagged you on a chart', $actor_name );

    $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 20px;">'
        . '<tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06);">'

        // Header
        . '<tr><td style="background:#a8dbe6;padding:24px 32px;">'
        . '<h1 style="margin:0;font-size:20px;color:#03283E;font-weight:600;">QualiNav</h1>'
        . '</td></tr>'

        // Body
        . '<tr><td style="padding:32px;">'
        . '<p style="margin:0 0 16px;font-size:16px;color:#03283E;">Hi ' . esc_html( $first_name ) . ',</p>'
        . '<p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">'
        . '<strong>' . esc_html( $actor_name ) . '</strong> tagged you on the chart <strong>&ldquo;' . esc_html( $chart_title ) . '&rdquo;</strong>. '
        . 'You can view it in your workflow.</p>'
        . '<p style="margin:0 0 24px;">'
        . '<a href="' . esc_url( $workflow_url ) . '" style="display:inline-block;background:#03283E;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">View My Workflow</a>'
        . '</p>'
        . '</td></tr>'

        // Footer
        . '<tr><td style="padding:20px 32px;border-top:1px solid rgba(0,0,0,0.06);text-align:center;">'
        . '<p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from QualiNav.</p>'
        . '</td></tr>'

        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';

    // Temporarily switch to HTML content type.
    $set_html = function () {
        return 'text/html';
    };
    add_filter( 'wp_mail_content_type', $set_html );

    wp_mail( $to, $subject, $body );

    remove_filter( 'wp_mail_content_type', $set_html );
}

// ─── Hooks ───

/**
 * Provide notifications to the header bell via the qualinav_notifications filter.
 */
function dttc_provide_notifications( array $notifications, int $user_id ) : array {
    if ( ! $user_id ) {
        return $notifications;
    }

    $rows = dttc_get_user_notifications( $user_id, 20 );

    foreach ( $rows as $row ) {
        $created = strtotime( $row['created_at'] );
        $time    = '';
        if ( $created ) {
            $diff = time() - $created;
            if ( $diff < 60 ) {
                $time = 'Just now';
            } elseif ( $diff < 3600 ) {
                $m    = (int) floor( $diff / 60 );
                $time = $m . ' min' . ( $m > 1 ? 's' : '' ) . ' ago';
            } elseif ( $diff < 86400 ) {
                $h    = (int) floor( $diff / 3600 );
                $time = $h . ' hour' . ( $h > 1 ? 's' : '' ) . ' ago';
            } else {
                $time = date_i18n( 'M j, Y', $created );
            }
        }

        $notifications[] = array(
            'id'     => (int) $row['id'],
            'title'  => $row['message'],
            'icon'   => 'fa-user-tag',
            'time'   => $time,
            'link'   => $row['link'] ?: home_url( '/my-workflow/' ),
            'unread' => empty( $row['read_at'] ),
        );
    }

    return $notifications;
}
add_filter( 'qualinav_notifications', 'dttc_provide_notifications', 10, 2 );

/**
 * AJAX handler: mark all notifications as read.
 */
function dttc_ajax_mark_notifications_read() : void {
    check_ajax_referer( 'dttc_notifications_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in.', 401 );
    }

    dttc_mark_all_notifications_read( get_current_user_id() );
    wp_send_json_success( array( 'ok' => true ) );
}
add_action( 'wp_ajax_dttc_mark_notifications_read', 'dttc_ajax_mark_notifications_read' );

/**
 * AJAX handler: mark a single notification as read.
 */
function dttc_ajax_mark_single_notification_read() : void {
    check_ajax_referer( 'dttc_notifications_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in.', 401 );
    }

    $notification_id = isset( $_POST['notification_id'] ) ? (int) $_POST['notification_id'] : 0;

    if ( ! $notification_id ) {
        wp_send_json_error( 'Missing notification_id.', 400 );
    }

    $marked = dttc_mark_notification_read( $notification_id, get_current_user_id() );
    wp_send_json_success( array( 'marked' => $marked ) );
}
add_action( 'wp_ajax_dttc_mark_single_notification_read', 'dttc_ajax_mark_single_notification_read' );

/**
 * Output inline JS for the "Mark all as read" button and individual notification clicks.
 */
function dttc_notification_inline_js() : void {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $nonce    = wp_create_nonce( 'dttc_notifications_nonce' );
    $ajax_url = admin_url( 'admin-ajax.php' );
    ?>
    <script>
    (function(){
        var ajaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
        var nonce = '<?php echo esc_js( $nonce ); ?>';

        // Mark all as read button
        var btn = document.getElementById('mark-all-read');
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=dttc_mark_notifications_read&nonce=' + nonce
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        // Hide badge
                        var badge = document.getElementById('notification-badge');
                        if (badge) badge.style.display = 'none';

                        // Remove unread highlights
                        document.querySelectorAll('.qualinav-notification-item.unread').forEach(function(el){
                            el.classList.remove('unread');
                        });
                    }
                });
            });
        }

        // Individual notification click - mark as read
        document.querySelectorAll('.qualinav-notification-item.unread').forEach(function(item) {
            item.addEventListener('click', function(e) {
                var notificationId = this.getAttribute('data-notification-id');
                if (!notificationId || notificationId === '0') return; // Let link proceed normally

                var itemEl = this;

                // Send AJAX request to mark as read (don't prevent navigation)
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=dttc_mark_single_notification_read&nonce=' + nonce + '&notification_id=' + notificationId
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        // Remove unread class
                        itemEl.classList.remove('unread');

                        // Update badge count
                        var badge = document.getElementById('notification-badge');
                        if (badge) {
                            var count = parseInt(badge.textContent, 10) || 0;
                            count = Math.max(0, count - 1);
                            if (count === 0) {
                                badge.style.display = 'none';
                            } else {
                                badge.textContent = count;
                            }
                        }
                    }
                });
            });
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'dttc_notification_inline_js' );
