<?php
/**
 * HACs & HAIs database schema.
 *
 * Stores HAC/HAI submissions separately from qapi_metric_data because this is
 * a workbook-style planning model, not a single uploaded metric.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QUALINAV_HACS_HAIS_DB_VERSION' ) ) {
	define( 'QUALINAV_HACS_HAIS_DB_VERSION', '2' );
}

if ( ! defined( 'QUALINAV_IMPROVEMENT_CALCULATOR_DB_VERSION' ) ) {
	define( 'QUALINAV_IMPROVEMENT_CALCULATOR_DB_VERSION', QUALINAV_HACS_HAIS_DB_VERSION );
}

if ( ! function_exists( 'qualinav_data_hub_improvement_submissions_table' ) ) {
	function qualinav_data_hub_improvement_submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_hacs_hais_submissions';
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_values_table' ) ) {
	function qualinav_data_hub_improvement_values_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_hacs_hais_values';
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_legacy_submissions_table' ) ) {
	function qualinav_data_hub_improvement_legacy_submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_improvement_calculator_submissions';
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_legacy_values_table' ) ) {
	function qualinav_data_hub_improvement_legacy_values_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_improvement_calculator_values';
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_table_exists' ) ) {
	function qualinav_data_hub_improvement_table_exists( $table_name ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === (string) $table_name;
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_migrate_legacy_tables' ) ) {
	function qualinav_data_hub_improvement_migrate_legacy_tables() {
		global $wpdb;

		$new_submissions    = qualinav_data_hub_improvement_submissions_table();
		$new_values         = qualinav_data_hub_improvement_values_table();
		$legacy_submissions = qualinav_data_hub_improvement_legacy_submissions_table();
		$legacy_values      = qualinav_data_hub_improvement_legacy_values_table();

		if (
			qualinav_data_hub_improvement_table_exists( $legacy_submissions ) &&
			qualinav_data_hub_improvement_table_exists( $new_submissions )
		) {
			$wpdb->query(
				"INSERT INTO {$new_submissions}
				 SELECT legacy.*
				   FROM {$legacy_submissions} legacy
				   LEFT JOIN {$new_submissions} current_table
				     ON current_table.id = legacy.id
				  WHERE current_table.id IS NULL"
			);
		}

		if (
			qualinav_data_hub_improvement_table_exists( $legacy_values ) &&
			qualinav_data_hub_improvement_table_exists( $new_values )
		) {
			$wpdb->query(
				"INSERT INTO {$new_values}
				 SELECT legacy.*
				   FROM {$legacy_values} legacy
				   LEFT JOIN {$new_values} current_table
				     ON current_table.id = legacy.id
				  WHERE current_table.id IS NULL"
			);
		}

		update_option( 'qualinav_hacs_hais_legacy_migrated_at', current_time( 'mysql' ), false );
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_install' ) ) {
	function qualinav_data_hub_improvement_calculator_install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate  = $wpdb->get_charset_collate();
		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		$values_table      = qualinav_data_hub_improvement_values_table();

		$submissions_sql = "CREATE TABLE {$submissions_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			organization_id BIGINT(20) UNSIGNED NULL,
			org_key VARCHAR(191) NOT NULL DEFAULT '',
			organization_name VARCHAR(255) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL,
			reference_date DATE NULL,
			reporting_year SMALLINT(5) UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			is_current TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY organization_id (organization_id),
			KEY user_id (user_id),
			KEY org_year (org_key, reporting_year),
			KEY org_year_current (org_key, reporting_year, is_current),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$values_sql = "CREATE TABLE {$values_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT(20) UNSIGNED NOT NULL,
			month_num TINYINT(2) UNSIGNED NOT NULL,
			month_label VARCHAR(12) NOT NULL DEFAULT '',
			measure_key VARCHAR(64) NOT NULL DEFAULT '',
			event_count DECIMAL(20,6) NULL,
			denominator_key VARCHAR(64) NOT NULL DEFAULT '',
			denominator_value DECIMAL(20,6) NULL,
			rate_value DECIMAL(20,6) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY submission_month_measure (submission_id, month_num, measure_key),
			KEY submission_id (submission_id),
			KEY month_num (month_num),
			KEY measure_key (measure_key),
			KEY denominator_key (denominator_key)
		) {$charset_collate};";

		dbDelta( $submissions_sql );
		dbDelta( $values_sql );

		qualinav_data_hub_improvement_migrate_legacy_tables();

		update_option( 'qualinav_hacs_hais_db_version', QUALINAV_HACS_HAIS_DB_VERSION, false );
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_maybe_install' ) ) {
	function qualinav_data_hub_improvement_calculator_maybe_install() {
		$installed = (string) get_option( 'qualinav_hacs_hais_db_version', '0' );
		if (
			$installed === QUALINAV_HACS_HAIS_DB_VERSION &&
			qualinav_data_hub_improvement_table_exists( qualinav_data_hub_improvement_submissions_table() ) &&
			qualinav_data_hub_improvement_table_exists( qualinav_data_hub_improvement_values_table() )
		) {
			return;
		}
		qualinav_data_hub_improvement_calculator_install();
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_measure_map' ) ) {
	function qualinav_data_hub_improvement_measure_map() {
		return array(
			'c_diff' => array(
				'label'           => 'C. Diff',
				'denominator_key' => 'inpatient_days',
				'multiplier'      => 1,
			),
			'mrsa' => array(
				'label'           => 'MRSA',
				'denominator_key' => 'inpatient_days',
				'multiplier'      => 1,
			),
			'cauti' => array(
				'label'           => 'CAUTI',
				'denominator_key' => 'catheter_days',
				'multiplier'      => 1,
			),
			'clabsi' => array(
				'label'           => 'CLABSI',
				'denominator_key' => 'central_line_days',
				'multiplier'      => 1,
			),
			'pressure_ulcers_3_plus' => array(
				'label'           => 'Pressure Ulcers 3+',
				'denominator_key' => 'total_discharges',
				'multiplier'      => 1,
			),
			'falls_with_injury' => array(
				'label'           => 'Inpatient Falls with Injury',
				'denominator_key' => 'inpatient_days',
				'multiplier'      => 1,
			),
			'sepsis_mortality' => array(
				'label'           => 'Sepsis Mortality',
				'denominator_key' => 'sepsis_patients',
				'multiplier'      => 1,
			),
			'readmissions' => array(
				'label'           => 'Readmissions',
				'denominator_key' => 'total_discharges',
				'multiplier'      => 1,
			),
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_decimal_or_null' ) ) {
	function qualinav_data_hub_improvement_decimal_or_null( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return null;
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		return (string) (float) $value;
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_user_context' ) ) {
	function qualinav_data_hub_improvement_user_context() {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( function_exists( 'qualinav_data_hub_get_org_context' ) ) {
			$context = qualinav_data_hub_get_org_context( $user_id );
		} else {
			$context = array();
		}

		$context['organization_id'] = isset( $context['organization_id'] ) ? (int) $context['organization_id'] : 0;
		if ( $context['organization_id'] <= 0 && $user_id > 0 ) {
			$org_id_lookup = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT organization_id FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
					$user_id
				)
			);
			if ( $org_id_lookup !== null && (int) $org_id_lookup > 0 ) {
				$context['organization_id'] = (int) $org_id_lookup;
			}
		}
		$context['org_key']         = ! empty( $context['org_key'] ) ? sanitize_title( $context['org_key'] ) : 'user-' . $user_id;
		$context['org_name']        = ! empty( $context['org_name'] ) ? sanitize_text_field( $context['org_name'] ) : '';

		return $context;
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_submission_to_payload' ) ) {
	function qualinav_data_hub_improvement_submission_to_payload( $submission ) {
		global $wpdb;

		if ( ! is_array( $submission ) || empty( $submission['id'] ) ) {
			return null;
		}

		$values_table = qualinav_data_hub_improvement_values_table();
		$values = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT month_num, month_label, measure_key, event_count, denominator_key, denominator_value, rate_value
				   FROM {$values_table}
				  WHERE submission_id = %d
				  ORDER BY month_num ASC, measure_key ASC",
				(int) $submission['id']
			),
			ARRAY_A
		);

		$rows = array();
		foreach ( $values as $value ) {
			$month_num = (int) ( $value['month_num'] ?? 0 );
			if ( ! isset( $rows[ $month_num ] ) ) {
				$rows[ $month_num ] = array(
					'month_num'    => $month_num,
					'month'        => sanitize_text_field( (string) ( $value['month_label'] ?? '' ) ),
					'events'       => array(),
					'denominators' => array(),
					'rates'        => array(),
				);
			}

			$measure_key = sanitize_key( (string) ( $value['measure_key'] ?? '' ) );
			$denominator_key = sanitize_key( (string) ( $value['denominator_key'] ?? '' ) );
			if ( $measure_key !== '' ) {
				$rows[ $month_num ]['events'][ $measure_key ] = $value['event_count'] === null ? '' : rtrim( rtrim( (string) $value['event_count'], '0' ), '.' );
				$rows[ $month_num ]['rates'][ $measure_key ] = $value['rate_value'] === null ? '' : (float) $value['rate_value'];
			}
			if ( $denominator_key !== '' && ! isset( $rows[ $month_num ]['denominators'][ $denominator_key ] ) ) {
				$rows[ $month_num ]['denominators'][ $denominator_key ] = $value['denominator_value'] === null ? '' : rtrim( rtrim( (string) $value['denominator_value'], '0' ), '.' );
			}
		}

		ksort( $rows );

		return array(
			'id'                => (int) $submission['id'],
			'organization_id'   => (int) ( $submission['organization_id'] ?? 0 ),
			'organization_name' => (string) ( $submission['organization_name'] ?? '' ),
			'reference_date'    => (string) ( $submission['reference_date'] ?? '' ),
			'reporting_year'    => (string) ( $submission['reporting_year'] ?? '' ),
			'status'            => (string) ( $submission['status'] ?? 'active' ),
			'is_current'        => (int) ( $submission['is_current'] ?? 0 ),
			'created_at'        => (string) ( $submission['created_at'] ?? '' ),
			'updated_at'        => (string) ( $submission['updated_at'] ?? '' ),
			'archived_at'       => (string) ( $submission['archived_at'] ?? '' ),
			'rows'              => array_values( $rows ),
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_current_submission' ) ) {
	function qualinav_data_hub_improvement_current_submission( $org_key, $reporting_year ) {
		global $wpdb;

		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				   FROM {$submissions_table}
				  WHERE org_key = %s
				    AND reporting_year = %d
				    AND is_current = 1
				    AND status = 'active'
				  ORDER BY updated_at DESC, id DESC
				  LIMIT 1",
				$org_key,
				(int) $reporting_year
			),
			ARRAY_A
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_submission_by_id' ) ) {
	function qualinav_data_hub_improvement_submission_by_id( $submission_id, $org_key ) {
		global $wpdb;

		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				   FROM {$submissions_table}
				  WHERE id = %d
				    AND org_key = %s
				  LIMIT 1",
				(int) $submission_id,
				$org_key
			),
			ARRAY_A
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_list_handler' ) ) {
	function qualinav_data_hub_improvement_calculator_list_handler() {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		qualinav_data_hub_improvement_calculator_maybe_install();

		$context = qualinav_data_hub_improvement_user_context();
		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				   FROM {$submissions_table}
				  WHERE org_key = %s
				  ORDER BY reporting_year DESC, updated_at DESC, id DESC",
				$context['org_key']
			),
			ARRAY_A
		);

		$payload = array();
		foreach ( $submissions as $submission ) {
			$payload[] = qualinav_data_hub_improvement_submission_to_payload( $submission );
		}

		wp_send_json_success(
			array(
				'submissions' => array_values( array_filter( $payload ) ),
			)
		);
	}

	add_action( 'wp_ajax_qualinav_improvement_calculator_list', 'qualinav_data_hub_improvement_calculator_list_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_archive_handler' ) ) {
	function qualinav_data_hub_improvement_calculator_archive_handler() {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		qualinav_data_hub_improvement_calculator_maybe_install();

		$context = qualinav_data_hub_improvement_user_context();
		$submission_id = (int) ( $_POST['submission_id'] ?? 0 );
		$submission = qualinav_data_hub_improvement_submission_by_id( $submission_id, $context['org_key'] );
		if ( ! $submission ) {
			wp_send_json_error( 'Submission not found.' );
		}

		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		$wpdb->update(
			$submissions_table,
			array(
				'status'      => 'archived',
				'is_current'  => 0,
				'archived_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $submission_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => 'Calculator submission archived.' ) );
	}

	add_action( 'wp_ajax_qualinav_improvement_calculator_archive', 'qualinav_data_hub_improvement_calculator_archive_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_restore_handler' ) ) {
	function qualinav_data_hub_improvement_calculator_restore_handler() {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		qualinav_data_hub_improvement_calculator_maybe_install();

		$context = qualinav_data_hub_improvement_user_context();
		$submission_id = (int) ( $_POST['submission_id'] ?? 0 );
		$overwrite = ! empty( $_POST['overwrite'] );
		$submission = qualinav_data_hub_improvement_submission_by_id( $submission_id, $context['org_key'] );
		if ( ! $submission ) {
			wp_send_json_error( 'Submission not found.' );
		}

		$existing = qualinav_data_hub_improvement_current_submission( $context['org_key'], (int) $submission['reporting_year'] );
		if ( $existing && (int) $existing['id'] !== $submission_id && ! $overwrite ) {
			wp_send_json_error(
				array(
					'code'    => 'existing_submission',
					'message' => 'An active Improvement Calculator submission already exists for this reporting year.',
				)
			);
		}

		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		$now = current_time( 'mysql' );
		if ( $existing && (int) $existing['id'] !== $submission_id ) {
			$wpdb->update(
				$submissions_table,
				array(
					'status'      => 'archived',
					'is_current'  => 0,
					'archived_at' => $now,
					'updated_at'  => $now,
				),
				array( 'id' => (int) $existing['id'] ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$submissions_table}
				    SET status = 'active',
				        is_current = 1,
				        archived_at = NULL,
				        updated_at = %s
				  WHERE id = %d",
				$now,
				$submission_id
			)
		);

		wp_send_json_success( array( 'message' => 'Calculator submission restored.' ) );
	}

	add_action( 'wp_ajax_qualinav_improvement_calculator_restore', 'qualinav_data_hub_improvement_calculator_restore_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_load_handler' ) ) {
	function qualinav_data_hub_improvement_calculator_load_handler() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		$year = (int) ( $_POST['reporting_year'] ?? 0 );
		if ( $year < 2000 || $year > 2100 ) {
			wp_send_json_error( 'Invalid reporting year' );
		}

		$context = qualinav_data_hub_improvement_user_context();
		$submission = qualinav_data_hub_improvement_current_submission( $context['org_key'], $year );
		if ( ! $submission ) {
			wp_send_json_success( array( 'submission' => null ) );
		}

		wp_send_json_success( array(
			'submission' => qualinav_data_hub_improvement_submission_to_payload( $submission ),
		) );
	}

	add_action( 'wp_ajax_qualinav_improvement_calculator_load', 'qualinav_data_hub_improvement_calculator_load_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_improvement_calculator_save_handler' ) ) {
	function qualinav_data_hub_improvement_calculator_save_handler() {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		qualinav_data_hub_improvement_calculator_maybe_install();

		$user_id = get_current_user_id();
		$context = qualinav_data_hub_improvement_user_context();
		$year = (int) ( $_POST['reporting_year'] ?? 0 );
		$reference_date = sanitize_text_field( wp_unslash( (string) ( $_POST['reference_date'] ?? '' ) ) );
		$rows = json_decode( wp_unslash( (string) ( $_POST['rows'] ?? '[]' ) ), true );
		$overwrite = ! empty( $_POST['overwrite'] );
		$save_mode = sanitize_key( wp_unslash( (string) ( $_POST['save_mode'] ?? '' ) ) );
		$is_single_month_save = ( $save_mode === 'single_month' );

		if ( $year < 2000 || $year > 2100 || ! is_array( $rows ) ) {
			wp_send_json_error( 'Invalid calculator data' );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reference_date ) ) {
			$reference_date = current_time( 'Y-m-d' );
		}

		$has_data = false;
		foreach ( $rows as $row ) {
			foreach ( array( 'events', 'denominators' ) as $group ) {
				if ( empty( $row[ $group ] ) || ! is_array( $row[ $group ] ) ) {
					continue;
				}
				foreach ( $row[ $group ] as $value ) {
					if ( trim( (string) $value ) !== '' ) {
						$has_data = true;
						break 3;
					}
				}
			}
		}

		if ( ! $has_data ) {
			wp_send_json_error( 'Enter at least one event count or denominator before saving.' );
		}

		$existing = qualinav_data_hub_improvement_current_submission( $context['org_key'], $year );
		if ( $existing && ! $overwrite && ! $is_single_month_save ) {
			wp_send_json_error( array(
				'code'       => 'existing_submission',
				'message'    => 'A saved Improvement Calculator submission already exists for this reporting year.',
				'submission' => qualinav_data_hub_improvement_submission_to_payload( $existing ),
			) );
		}

		$submissions_table = qualinav_data_hub_improvement_submissions_table();
		$values_table = qualinav_data_hub_improvement_values_table();
		$now = current_time( 'mysql' );
		$measure_map = qualinav_data_hub_improvement_measure_map();
		$month_labels = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );

		if ( $is_single_month_save && $existing && ! $overwrite ) {
			$existing_submission_id = (int) $existing['id'];
			foreach ( $rows as $index => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$month_label = sanitize_text_field( (string) ( $row['month'] ?? ( $month_labels[ $index ] ?? '' ) ) );
				$month_num = array_search( $month_label, $month_labels, true );
				$month_num = ( $month_num === false ) ? ( $index + 1 ) : ( $month_num + 1 );
				$events = ( ! empty( $row['events'] ) && is_array( $row['events'] ) ) ? $row['events'] : array();
				foreach ( $measure_map as $measure_key => $measure ) {
					if ( qualinav_data_hub_improvement_decimal_or_null( $events[ $measure_key ] ?? '' ) === null ) {
						continue;
					}
					$existing_value_id = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$values_table} WHERE submission_id = %d AND month_num = %d AND measure_key = %s LIMIT 1",
							$existing_submission_id,
							$month_num,
							$measure_key
						)
					);
					if ( $existing_value_id > 0 ) {
						$measure_label = isset( $measure['label'] ) ? $measure['label'] : ucwords( str_replace( '_', ' ', $measure_key ) );
						wp_send_json_error(
							array(
								'code'           => 'existing_value',
								'message'        => sprintf( '%s data already exists for %s %d.', $measure_label, $month_label, $year ),
								'measure_key'    => $measure_key,
								'measure_label'  => $measure_label,
								'month'          => $month_label,
								'reporting_year' => $year,
							)
						);
					}
				}
			}
		}

		if ( $existing ) {
			$submission_id = (int) $existing['id'];
			$wpdb->update(
				$submissions_table,
				array(
					'organization_id'   => $context['organization_id'] > 0 ? $context['organization_id'] : null,
					'organization_name' => $context['org_name'],
					'user_id'           => $user_id,
					'reference_date'    => $reference_date,
					'updated_at'        => $now,
				),
				array( 'id' => $submission_id ),
				array( '%d', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
			if ( ! $is_single_month_save ) {
				$wpdb->delete( $values_table, array( 'submission_id' => $submission_id ), array( '%d' ) );
			}
		} else {
			$wpdb->insert(
				$submissions_table,
				array(
					'organization_id'   => $context['organization_id'] > 0 ? $context['organization_id'] : null,
					'org_key'           => $context['org_key'],
					'organization_name' => $context['org_name'],
					'user_id'           => $user_id,
					'reference_date'    => $reference_date,
					'reporting_year'    => $year,
					'status'            => 'active',
					'is_current'        => 1,
					'created_at'        => $now,
					'updated_at'        => $now,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
			$submission_id = (int) $wpdb->insert_id;
		}

		if ( $submission_id <= 0 ) {
			wp_send_json_error( 'Could not save calculator submission.' );
		}

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$month_label = sanitize_text_field( (string) ( $row['month'] ?? ( $month_labels[ $index ] ?? '' ) ) );
			$month_num = array_search( $month_label, $month_labels, true );
			$month_num = ( $month_num === false ) ? ( $index + 1 ) : ( $month_num + 1 );
			$events = ( ! empty( $row['events'] ) && is_array( $row['events'] ) ) ? $row['events'] : array();
			$denominators = ( ! empty( $row['denominators'] ) && is_array( $row['denominators'] ) ) ? $row['denominators'] : array();

			foreach ( $measure_map as $measure_key => $measure ) {
				$event_count = qualinav_data_hub_improvement_decimal_or_null( $events[ $measure_key ] ?? '' );
				$denominator_key = $measure['denominator_key'];
				$denominator_value = qualinav_data_hub_improvement_decimal_or_null( $denominators[ $denominator_key ] ?? '' );
				if ( $event_count === null && $denominator_value === null ) {
					continue;
				}

				$rate_value = null;
				if ( $event_count !== null && $denominator_value !== null && (float) $denominator_value > 0 ) {
					$rate_value = ( (float) $event_count / (float) $denominator_value ) * (float) $measure['multiplier'];
				}

				$value_data = array(
					'submission_id'     => $submission_id,
					'month_num'         => $month_num,
					'month_label'       => $month_label,
					'measure_key'       => $measure_key,
					'event_count'       => $event_count,
					'denominator_key'   => $denominator_key,
					'denominator_value' => $denominator_value,
					'rate_value'        => $rate_value,
					'created_at'        => $now,
					'updated_at'        => $now,
				);
				$value_formats = array( '%d', '%d', '%s', '%s', '%f', '%s', '%f', '%f', '%s', '%s' );
				$existing_value_id = 0;

				if ( $is_single_month_save ) {
					$existing_value_id = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$values_table} WHERE submission_id = %d AND month_num = %d AND measure_key = %s LIMIT 1",
							$submission_id,
							$month_num,
							$measure_key
						)
					);
					if ( $existing_value_id > 0 && ! $overwrite ) {
						$measure_label = isset( $measure['label'] ) ? $measure['label'] : ucwords( str_replace( '_', ' ', $measure_key ) );
						wp_send_json_error(
							array(
								'code'           => 'existing_value',
								'message'        => sprintf( '%s data already exists for %s %d.', $measure_label, $month_label, $year ),
								'measure_key'    => $measure_key,
								'measure_label'  => $measure_label,
								'month'          => $month_label,
								'reporting_year' => $year,
							)
						);
					}
				}

				if ( $is_single_month_save && $existing_value_id > 0 ) {
					unset( $value_data['submission_id'], $value_data['month_num'], $value_data['month_label'], $value_data['measure_key'], $value_data['created_at'] );
					$wpdb->update(
						$values_table,
						$value_data,
						array( 'id' => $existing_value_id ),
						array( '%f', '%s', '%f', '%f', '%s' ),
						array( '%d' )
					);
				} else {
					$wpdb->insert(
						$values_table,
						$value_data,
						$value_formats
					);
				}
			}
		}

		$submission = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$submissions_table} WHERE id = %d LIMIT 1", $submission_id ),
			ARRAY_A
		);

		wp_send_json_success( array(
			'submission' => qualinav_data_hub_improvement_submission_to_payload( $submission ),
			'message'    => $is_single_month_save ? ( $overwrite ? 'Monthly calculator data overwritten.' : 'Monthly calculator data saved.' ) : ( $existing ? 'Calculator data overwritten.' : 'Calculator data saved.' ),
		) );
	}

	add_action( 'wp_ajax_qualinav_improvement_calculator_save', 'qualinav_data_hub_improvement_calculator_save_handler' );
}
