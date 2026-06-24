<?php
/**
 * DB layer for Do Tank Table Chart.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dttc_get_rows_table_name() : string {
    global $wpdb;
    return $wpdb->prefix . 'dttc_chart_rows';
}

/**
 * Create/upgrade DB tables.
 */
function dttc_install_db() : void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name = dttc_get_rows_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        chart_id BIGINT(20) UNSIGNED NOT NULL,
        row_index INT(11) NOT NULL DEFAULT 0,
        time_label VARCHAR(190) NOT NULL DEFAULT '',
        numerator DECIMAL(20,6) NULL,
        denominator DECIMAL(20,6) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY chart_id (chart_id),
        KEY chart_row (chart_id, row_index)
    ) {$charset_collate};";

    dbDelta( $sql );
}

function dttc_get_chart_rows( int $chart_id ) : array {
    global $wpdb;

    $table = dttc_get_rows_table_name();

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT row_index, time_label, numerator, denominator
             FROM {$table}
             WHERE chart_id = %d
             ORDER BY row_index ASC",
            $chart_id
        ),
        ARRAY_A
    );

    if ( ! is_array( $rows ) ) {
        return array();
    }

    foreach ( $rows as &$r ) {
        $r['row_index'] = (int) $r['row_index'];
        $r['time_label'] = (string) $r['time_label'];
        $r['numerator'] = array_key_exists( 'numerator', $r ) && $r['numerator'] !== null ? (float) $r['numerator'] : null;
        $r['denominator'] = array_key_exists( 'denominator', $r ) && $r['denominator'] !== null ? (float) $r['denominator'] : null;
    }

    return $rows;
}

/**
 * Replace all rows for a chart.
 *
 * @param int   $chart_id Chart post ID.
 * @param array $rows     Rows from client.
 */
function dttc_replace_chart_rows( int $chart_id, array $rows ) : void {
    global $wpdb;

    $table = dttc_get_rows_table_name();

    // Safety cap.
    $rows = array_slice( $rows, 0, 500 );

    $wpdb->delete( $table, array( 'chart_id' => $chart_id ), array( '%d' ) );

    $fallback_index = 0;
    foreach ( $rows as $row ) {
        $fallback_index++;

        $row_index = isset( $row['row_index'] ) ? (int) $row['row_index'] : $fallback_index;
        $time_label = isset( $row['time_label'] ) ? sanitize_text_field( (string) $row['time_label'] ) : '';

        $numerator = null;
        if ( array_key_exists( 'numerator', $row ) && $row['numerator'] !== '' && $row['numerator'] !== null ) {
            $numerator = (float) $row['numerator'];
        }

        $denominator = null;
        if ( array_key_exists( 'denominator', $row ) && $row['denominator'] !== '' && $row['denominator'] !== null ) {
            $denominator = (float) $row['denominator'];
        }

        // Do not pass a format array so NULL values remain NULL.
        $wpdb->insert(
            $table,
            array(
                'chart_id'    => $chart_id,
                'row_index'   => $row_index,
                'time_label'  => $time_label,
                'numerator'   => $numerator,
                'denominator' => $denominator,
            )
        );
    }
}

/**
 * Delete all stored rows for a chart.
 *
 * @param int $chart_id Chart post ID.
 */
function dttc_delete_chart_rows( int $chart_id ) : void {
    global $wpdb;

    $table = dttc_get_rows_table_name();
    $wpdb->delete( $table, array( 'chart_id' => $chart_id ), array( '%d' ) );
}
