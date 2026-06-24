<?php
/**
 * MBQIP database schema.
 *
 * Defines normalized storage for MBQIP measure submissions, their raw values,
 * and the measure-definition metadata used by Data Hub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QUALINAV_MBQIP_DB_VERSION' ) ) {
	define( 'QUALINAV_MBQIP_DB_VERSION', '9' );
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_submissions_table' ) ) {
	function qualinav_data_hub_mbqip_submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_mbqip_submissions';
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_submission_values_table' ) ) {
	function qualinav_data_hub_mbqip_submission_values_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_mbqip_submission_values';
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_measure_definitions_table' ) ) {
	function qualinav_data_hub_mbqip_measure_definitions_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_mbqip_measure_definitions';
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_measure_goals_table' ) ) {
	function qualinav_data_hub_mbqip_measure_goals_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_mbqip_measure_goals';
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_report_ownership_table' ) ) {
	function qualinav_data_hub_mbqip_report_ownership_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_mbqip_report_ownership';
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_report_ownership_audit_table' ) ) {
	function qualinav_data_hub_mbqip_report_ownership_audit_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_mbqip_report_ownership_audit';
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_install' ) ) {
	function qualinav_data_hub_mbqip_install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate  = $wpdb->get_charset_collate();
		$submissions_table = qualinav_data_hub_mbqip_submissions_table();
		$values_table      = qualinav_data_hub_mbqip_submission_values_table();
		$definitions_table = qualinav_data_hub_mbqip_measure_definitions_table();
		$goals_table       = qualinav_data_hub_mbqip_measure_goals_table();
		$ownership_table   = qualinav_data_hub_mbqip_report_ownership_table();
		$audit_table       = qualinav_data_hub_mbqip_report_ownership_audit_table();

		$submissions_sql = "CREATE TABLE {$submissions_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			organization_id BIGINT(20) UNSIGNED NULL,
			org_key VARCHAR(191) NOT NULL DEFAULT '',
			organization_name VARCHAR(255) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL,
			measure_key VARCHAR(100) NOT NULL DEFAULT '',
			measure_name VARCHAR(255) NOT NULL DEFAULT '',
			event_type VARCHAR(100) NOT NULL DEFAULT '',
			reporting_period_type VARCHAR(20) NOT NULL DEFAULT 'annual',
			reporting_year SMALLINT(5) UNSIGNED NOT NULL,
			reporting_period VARCHAR(20) NOT NULL DEFAULT '',
			date_reported DATE NULL,
			source_type VARCHAR(20) NOT NULL DEFAULT 'manual',
			file_name VARCHAR(255) NOT NULL DEFAULT '',
			file_url TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			is_current TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY organization_id (organization_id),
			KEY user_id (user_id),
			KEY measure_key (measure_key),
			KEY event_type (event_type),
			KEY org_measure_period (org_key, measure_key, reporting_year, reporting_period),
			KEY org_measure_current (org_key, measure_key, reporting_year, reporting_period, is_current),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$values_sql = "CREATE TABLE {$values_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT(20) UNSIGNED NOT NULL,
			row_index INT(10) UNSIGNED NOT NULL DEFAULT 0,
			field_key VARCHAR(100) NOT NULL DEFAULT '',
			field_label VARCHAR(255) NOT NULL DEFAULT '',
			field_value LONGTEXT NULL,
			field_value_numeric DECIMAL(20,6) NULL,
			field_type VARCHAR(30) NOT NULL DEFAULT 'text',
			sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY field_key (field_key),
			KEY row_index (submission_id, row_index),
			KEY sort_order (submission_id, sort_order)
		) {$charset_collate};";

		$definitions_sql = "CREATE TABLE {$definitions_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			measure_key VARCHAR(100) NOT NULL DEFAULT '',
			measure_name VARCHAR(255) NOT NULL DEFAULT '',
			event_type VARCHAR(100) NOT NULL DEFAULT '',
			reporting_period_type VARCHAR(20) NOT NULL DEFAULT 'annual',
			numerator_label VARCHAR(255) NOT NULL DEFAULT '',
			denominator_label VARCHAR(255) NOT NULL DEFAULT '',
			rate_label VARCHAR(100) NOT NULL DEFAULT 'Rate',
			benchmark_value DECIMAL(20,6) NULL,
			benchmark_label VARCHAR(255) NOT NULL DEFAULT '',
			direction VARCHAR(20) NOT NULL DEFAULT 'higher',
			template_schema LONGTEXT NULL,
			raw_data_schema LONGTEXT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY measure_key (measure_key),
			KEY event_type (event_type),
			KEY active (active),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$goals_sql = "CREATE TABLE {$goals_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			organization_id BIGINT(20) UNSIGNED NULL,
			org_key VARCHAR(191) NOT NULL DEFAULT '',
			organization_name VARCHAR(255) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL,
			measure_key VARCHAR(100) NOT NULL DEFAULT '',
			measure_name VARCHAR(255) NOT NULL DEFAULT '',
			start_date DATE NULL,
			end_date DATE NULL,
			current_rate DECIMAL(20,6) NULL,
			goal_rate DECIMAL(20,6) NULL,
			difference_needed DECIMAL(20,6) NULL,
			direction VARCHAR(20) NOT NULL DEFAULT 'higher',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY organization_id (organization_id),
			KEY user_id (user_id),
			KEY measure_key (measure_key),
			KEY org_measure_status (org_key, measure_key, status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$ownership_sql = "CREATE TABLE {$ownership_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			organization_id BIGINT(20) UNSIGNED NULL,
			org_key VARCHAR(100) NOT NULL DEFAULT '',
			organization_name VARCHAR(255) NOT NULL DEFAULT '',
			module_key VARCHAR(32) NOT NULL DEFAULT 'mbqip',
			event_type_key VARCHAR(64) NOT NULL DEFAULT '',
			measure_key VARCHAR(100) NOT NULL DEFAULT '',
			measure_name VARCHAR(255) NOT NULL DEFAULT '',
			owner_user_id BIGINT(20) UNSIGNED NULL,
			assigned_by_user_id BIGINT(20) UNSIGNED NULL,
			assigned_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ownership_scope (org_key, module_key, event_type_key, measure_key),
			KEY organization_id (organization_id),
			KEY owner_user_id (owner_user_id),
			KEY assigned_by_user_id (assigned_by_user_id),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$audit_sql = "CREATE TABLE {$audit_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ownership_id BIGINT(20) UNSIGNED NULL,
			organization_id BIGINT(20) UNSIGNED NULL,
			org_key VARCHAR(100) NOT NULL DEFAULT '',
			organization_name VARCHAR(255) NOT NULL DEFAULT '',
			module_key VARCHAR(32) NOT NULL DEFAULT 'mbqip',
			event_type_key VARCHAR(64) NOT NULL DEFAULT '',
			measure_key VARCHAR(100) NOT NULL DEFAULT '',
			measure_name VARCHAR(255) NOT NULL DEFAULT '',
			previous_owner_user_id BIGINT(20) UNSIGNED NULL,
			new_owner_user_id BIGINT(20) UNSIGNED NULL,
			changed_by_user_id BIGINT(20) UNSIGNED NULL,
			changed_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ownership_id (ownership_id),
			KEY organization_id (organization_id),
			KEY changed_by_user_id (changed_by_user_id),
			KEY changed_at (changed_at)
		) {$charset_collate};";

		dbDelta( $submissions_sql );
		dbDelta( $values_sql );
		dbDelta( $definitions_sql );
		dbDelta( $goals_sql );
		dbDelta( $ownership_sql );
		dbDelta( $audit_sql );

		qualinav_data_hub_mbqip_seed_measure_definitions();
		update_option( 'qualinav_mbqip_db_version', QUALINAV_MBQIP_DB_VERSION, false );
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_maybe_install' ) ) {
	function qualinav_data_hub_mbqip_maybe_install() {
		global $wpdb;

		$installed = (string) get_option( 'qualinav_mbqip_db_version', '0' );
		$tables = array(
			qualinav_data_hub_mbqip_submissions_table(),
			qualinav_data_hub_mbqip_submission_values_table(),
			qualinav_data_hub_mbqip_measure_definitions_table(),
			qualinav_data_hub_mbqip_measure_goals_table(),
			qualinav_data_hub_mbqip_report_ownership_table(),
			qualinav_data_hub_mbqip_report_ownership_audit_table(),
		);
		$has_tables = true;
		foreach ( $tables as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				$has_tables = false;
				break;
			}
		}

		if ( $installed === QUALINAV_MBQIP_DB_VERSION && $has_tables ) {
			return;
		}
		qualinav_data_hub_mbqip_install();
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_measure_definitions' ) ) {
	function qualinav_data_hub_mbqip_measure_definitions() {
		$definitions = array(
			array(
				'measure_key'           => 'cah_quality_infrastructure_assessment',
				'measure_name'          => 'CAH Quality Infrastructure Assessment',
				'event_type'            => 'Global Measures',
				'reporting_period_type' => 'annual',
				'numerator_label'       => 'Criteria met',
				'denominator_label'     => 'Criteria count',
				'benchmark_value'       => 100,
				'benchmark_label'       => 'National benchmark',
				'direction'             => 'higher',
				'template_schema'       => array( 'Year', 'CAH Global Measure Component', 'Criteria Met' ),
				'raw_data_schema'       => array( 'Year', 'Criteria Met', 'Criteria Count', 'Rate', 'File' ),
				'sort_order'            => 10,
			),
			array(
				'measure_key'           => 'hcp_imm_3_healthcare_personnel_influenza_vaccination',
				'measure_name'          => 'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination',
				'event_type'            => 'Patient Safety',
				'reporting_period_type' => 'annual',
				'numerator_label'       => 'Vaccinated HCP',
				'denominator_label'     => 'Total Eligible HCP',
				'benchmark_value'       => 100,
				'benchmark_label'       => 'National benchmark',
				'direction'             => 'higher',
				'template_schema'       => array( 'Year', 'Numerator', 'Denominator', 'Rate' ),
				'raw_data_schema'       => array( 'Year', 'Vaccinated HCP', 'Total Eligible HCP', 'Rate', 'File' ),
				'sort_order'            => 20,
			),
			array(
				'measure_key'           => 'antibiotic_stewardship',
				'measure_name'          => 'Antibiotic Stewardship',
				'event_type'            => 'Patient Safety',
				'reporting_period_type' => 'annual',
				'numerator_label'       => 'Core elements met',
				'denominator_label'     => 'Core elements count',
				'benchmark_value'       => 100,
				'benchmark_label'       => 'Target',
				'direction'             => 'higher',
				'template_schema'       => array( 'Year', 'CDC 7 Core Elements', 'Criteria Met' ),
				'raw_data_schema'       => array( 'Year', 'Core Elements Met', 'Core Elements Count', 'Rate', 'File' ),
				'sort_order'            => 30,
			),
			array(
				'measure_key'           => 'safe_use_of_opioids_ecqm_mbqip_submission',
				'measure_name'          => 'Safe Use of Opioids eCQM — MBQIP Submission',
				'event_type'            => 'Patient Safety',
				'reporting_period_type' => 'quarterly',
				'numerator_label'       => 'Num',
				'denominator_label'     => 'Denom',
				'direction'             => 'lower',
				'template_schema'       => array( 'Year', 'Month', 'Num', 'Denom', 'Rate' ),
				'raw_data_schema'       => array( 'Year', 'Month', 'Num', 'Denom', 'Rate', 'File' ),
				'sort_order'            => 40,
			),
			array(
				'measure_key'           => 'edtc_emergency_department_transfer_communication',
				'measure_name'          => 'EDTC — Emergency Department Transfer Communication',
				'event_type'            => 'Emergency Department',
				'reporting_period_type' => 'quarterly',
				'numerator_label'       => 'Num',
				'denominator_label'     => 'Denom',
				'benchmark_value'       => 100,
				'benchmark_label'       => 'Target',
				'direction'             => 'higher',
				'template_schema'       => array( 'Year', 'Quarter', 'EDTC Reporting Item', 'Num', 'Denom', 'Rate' ),
				'raw_data_schema'       => array( 'Year', 'Quarter', 'EDTC Reporting Item', 'Numerator', 'Denominator', 'Rate', 'File' ),
				'sort_order'            => 60,
			),
			array(
				'measure_key'           => 'op_18_median_ed_arrival_to_departure_time_discharged_patients',
				'measure_name'          => 'OP-18 — Median ED Arrival to Departure Time (Discharged Patients)',
				'event_type'            => 'Emergency Department',
				'reporting_period_type' => 'quarterly',
				'numerator_label'       => 'Median Minutes',
				'denominator_label'     => '',
				'rate_label'            => 'Median Minutes',
				'benchmark_value'       => 84,
				'benchmark_label'       => 'National benchmark',
				'direction'             => 'lower',
				'template_schema'       => array( 'Year', 'Quarter', 'Median Minutes' ),
				'raw_data_schema'       => array( 'Year', 'Quarter', 'Median Minutes', 'File' ),
				'sort_order'            => 70,
			),
			array(
				'measure_key'           => 'op_22_patient_left_without_being_seen_lwbs_rate',
				'measure_name'          => 'OP-22 — Patient Left Without Being Seen (LWBS) Rate',
				'event_type'            => 'Emergency Department',
				'reporting_period_type' => 'annual',
				'numerator_label'       => 'Num',
				'denominator_label'     => 'Denom',
				'benchmark_value'       => 0.1,
				'benchmark_label'       => 'National benchmark',
				'direction'             => 'lower',
				'template_schema'       => array( 'Year', 'Num', 'Denom', 'Rate' ),
				'raw_data_schema'       => array( 'Year', 'Num', 'Denom', 'Rate', 'File' ),
				'sort_order'            => 80,
			),
		);

		$hcahps_measures = array(
			'HCAHPS — Composite 1: Communication with Nurses' => 85.6,
			'HCAHPS — Composite 2: Communication with Doctors' => 85.9,
			'HCAHPS — Composite 3: Restfulness of Hospital Environment' => 77.5,
			'HCAHPS — Composite 4: Responsiveness of Hospital Staff' => 77.2,
			'HCAHPS — Composite 5: Communication About Medicines' => 70.1,
			'HCAHPS — Composite 6: Discharge Information / Care Coordination' => 91.1,
			'HCAHPS — Composite 7: Transitions of Care' => 60.9,
			'HCAHPS — Q7: Cleanliness of Hospital Environment' => 77.5,
			'HCAHPS — Q20: Info About Symptoms to Watch For After Discharge' => null,
			'HCAHPS — Q24: Overall Rating of Hospital (0-10)' => 83.2,
			'HCAHPS — Q5: Willingness to Recommend Hospital' => null,
		);

		$sort_order = 100;
		foreach ( $hcahps_measures as $measure_name => $benchmark_value ) {
			$definitions[] = array(
				'measure_key'           => sanitize_title( str_replace( array( '—', '/', ':' ), '-', $measure_name ) ),
				'measure_name'          => $measure_name,
				'event_type'            => 'Patient Experience (HCAHPS)',
				'reporting_period_type' => 'quarterly',
				'numerator_label'       => 'Num',
				'denominator_label'     => 'Denom',
				'benchmark_value'       => $benchmark_value,
				'benchmark_label'       => null === $benchmark_value ? '' : 'National benchmark',
				'direction'             => 'higher',
				'template_schema'       => array( 'Year', 'Quarter', 'Num', 'Denom', 'Rate' ),
				'raw_data_schema'       => array( 'Year', 'Quarter', 'Num', 'Denom', 'Rate', 'File' ),
				'sort_order'            => $sort_order,
			);
			$sort_order += 10;
		}

		return $definitions;
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_seed_measure_definitions' ) ) {
	function qualinav_data_hub_mbqip_seed_measure_definitions() {
		global $wpdb;

		$table = qualinav_data_hub_mbqip_measure_definitions_table();
		$now   = current_time( 'mysql' );

		foreach ( qualinav_data_hub_mbqip_measure_definitions() as $definition ) {
			$measure_key = sanitize_key( (string) ( $definition['measure_key'] ?? '' ) );
			if ( $measure_key === '' ) {
				continue;
			}

			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE measure_key = %s LIMIT 1",
					$measure_key
				)
			);

			$row = array(
				'measure_key'           => $measure_key,
				'measure_name'          => sanitize_text_field( (string) ( $definition['measure_name'] ?? '' ) ),
				'event_type'            => sanitize_text_field( (string) ( $definition['event_type'] ?? '' ) ),
				'reporting_period_type' => sanitize_key( (string) ( $definition['reporting_period_type'] ?? 'annual' ) ),
				'numerator_label'       => sanitize_text_field( (string) ( $definition['numerator_label'] ?? '' ) ),
				'denominator_label'     => sanitize_text_field( (string) ( $definition['denominator_label'] ?? '' ) ),
				'rate_label'            => sanitize_text_field( (string) ( $definition['rate_label'] ?? 'Rate' ) ),
				'benchmark_value'       => isset( $definition['benchmark_value'] ) ? (string) (float) $definition['benchmark_value'] : null,
				'benchmark_label'       => sanitize_text_field( (string) ( $definition['benchmark_label'] ?? '' ) ),
				'direction'             => sanitize_key( (string) ( $definition['direction'] ?? 'higher' ) ) === 'lower' ? 'lower' : 'higher',
				'template_schema'       => wp_json_encode( $definition['template_schema'] ?? array() ),
				'raw_data_schema'       => wp_json_encode( $definition['raw_data_schema'] ?? array() ),
				'active'                => isset( $definition['active'] ) ? (int) $definition['active'] : 1,
				'sort_order'            => isset( $definition['sort_order'] ) ? (int) $definition['sort_order'] : 0,
				'updated_at'            => $now,
			);

			if ( $existing_id > 0 ) {
				$wpdb->update(
					$table,
					$row,
					array( 'id' => $existing_id ),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
					array( '%d' )
				);
				continue;
			}

			$row['created_at'] = $now;
			$wpdb->insert(
				$table,
				$row,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_user_context' ) ) {
	function qualinav_data_hub_mbqip_goal_user_context() {
		$user_id = get_current_user_id();
		if ( function_exists( 'qualinav_data_hub_get_org_context' ) ) {
			$context = qualinav_data_hub_get_org_context( $user_id );
		} else {
			$context = array();
		}

		$context['organization_id'] = isset( $context['organization_id'] ) ? (int) $context['organization_id'] : 0;
		$context['org_key']         = ! empty( $context['org_key'] ) ? sanitize_title( $context['org_key'] ) : 'user-' . $user_id;
		$context['org_name']        = ! empty( $context['org_name'] ) ? sanitize_text_field( (string) $context['org_name'] ) : '';

		return $context;
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_measure_key' ) ) {
	function qualinav_data_hub_mbqip_goal_measure_key( $measure_name, $measure_key = '' ) {
		$measure_key = sanitize_key( (string) $measure_key );
		if ( $measure_key !== '' ) {
			return $measure_key;
		}
		return sanitize_title( str_replace( array( '—', '/', ':' ), '-', (string) $measure_name ) );
	}
}

if ( ! function_exists( 'qualinav_data_hub_measure_coverage_option_key' ) ) {
	function qualinav_data_hub_measure_coverage_option_key( $org_key ) {
		$org_key = sanitize_title( (string) $org_key );
		if ( $org_key === '' ) {
			$org_key = 'user-' . (int) get_current_user_id();
		}
		return 'qualinav_data_hub_measure_coverage_' . $org_key;
	}
}

if ( ! function_exists( 'qualinav_data_hub_normalize_measure_coverage' ) ) {
	function qualinav_data_hub_normalize_measure_coverage( $coverage ) {
		$coverage = is_array( $coverage ) ? $coverage : array();
		$normalize_list = static function( $values ) {
			if ( ! is_array( $values ) ) {
				return array();
			}
			$values = array_map(
				static function( $value ) {
					return sanitize_text_field( (string) $value );
				},
				$values
			);
			$values = array_filter(
				$values,
				static function( $value ) {
					return $value !== '';
				}
			);
			return array_values( array_unique( $values ) );
		};

		return array(
			'saved'     => ! empty( $coverage['saved'] ),
			'mbqip'     => $normalize_list( $coverage['mbqip'] ?? array() ),
			'hacs_hais' => $normalize_list( $coverage['hacs_hais'] ?? array() ),
			'updated_at' => sanitize_text_field( (string) ( $coverage['updated_at'] ?? '' ) ),
			'updated_by' => isset( $coverage['updated_by'] ) ? (int) $coverage['updated_by'] : 0,
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_get_measure_coverage' ) ) {
	function qualinav_data_hub_get_measure_coverage( $context = null ) {
		if ( ! is_array( $context ) ) {
			$context = qualinav_data_hub_mbqip_goal_user_context();
		}
		$option_key = qualinav_data_hub_measure_coverage_option_key( $context['org_key'] ?? '' );
		$stored     = get_option( $option_key, null );
		if ( ! is_array( $stored ) ) {
			return array(
				'saved'      => false,
				'mbqip'      => array(),
				'hacs_hais'  => array(),
				'updated_at' => '',
				'updated_by' => 0,
			);
		}
		$stored['saved'] = true;
		return qualinav_data_hub_normalize_measure_coverage( $stored );
	}
}

if ( ! function_exists( 'qualinav_data_hub_measure_coverage_request_list' ) ) {
	function qualinav_data_hub_measure_coverage_request_list( $key ) {
		$raw = wp_unslash( $_POST[ $key ] ?? array() );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array( $raw );
		}
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) ) ) );
	}
}

if ( ! function_exists( 'qualinav_data_hub_measure_coverage_load_handler' ) ) {
	function qualinav_data_hub_measure_coverage_load_handler() {
		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to load measure coverage.' );
		}

		$context = qualinav_data_hub_mbqip_goal_user_context();
		wp_send_json_success( array(
			'coverage' => qualinav_data_hub_get_measure_coverage( $context ),
		) );
	}

	add_action( 'wp_ajax_qualinav_measure_coverage_load', 'qualinav_data_hub_measure_coverage_load_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_measure_coverage_save_handler' ) ) {
	function qualinav_data_hub_measure_coverage_save_handler() {
		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to save measure coverage.' );
		}

		$context = qualinav_data_hub_mbqip_goal_user_context();
		$coverage = qualinav_data_hub_normalize_measure_coverage( array(
			'saved'      => true,
			'mbqip'      => qualinav_data_hub_measure_coverage_request_list( 'mbqip' ),
			'hacs_hais'  => qualinav_data_hub_measure_coverage_request_list( 'hacs_hais' ),
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
		) );

		update_option(
			qualinav_data_hub_measure_coverage_option_key( $context['org_key'] ?? '' ),
			$coverage,
			false
		);

		wp_send_json_success( array(
			'coverage' => $coverage,
			'message'  => 'Measure coverage saved.',
		) );
	}

	add_action( 'wp_ajax_qualinav_measure_coverage_save', 'qualinav_data_hub_measure_coverage_save_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_date_or_null' ) ) {
	function qualinav_data_hub_mbqip_goal_date_or_null( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return null;
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : null;
		}
		if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches ) ) {
			if ( ! checkdate( (int) $matches[1], (int) $matches[2], (int) $matches[3] ) ) {
				return null;
			}
			return sprintf( '%04d-%02d-%02d', (int) $matches[3], (int) $matches[1], (int) $matches[2] );
		}
		return null;
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_decimal_or_null' ) ) {
	function qualinav_data_hub_mbqip_goal_decimal_or_null( $value ) {
		$value = trim( str_replace( '%', '', (string) $value ) );
		if ( $value === '' || ! is_numeric( $value ) ) {
			return null;
		}
		return (string) (float) $value;
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_payload' ) ) {
	function qualinav_data_hub_mbqip_goal_payload( $goal ) {
		if ( ! is_array( $goal ) || empty( $goal['id'] ) ) {
			return null;
		}
		$user_id   = (int) ( $goal['user_id'] ?? 0 );
		$user_name = '';
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$user_name = trim( (string) $user->display_name );
				if ( $user_name === '' ) {
					$user_name = trim( (string) $user->user_login );
				}
			}
		}
		return array(
			'id'                => (int) $goal['id'],
			'organization_id'   => (int) ( $goal['organization_id'] ?? 0 ),
			'organization_name' => (string) ( $goal['organization_name'] ?? '' ),
			'user_id'           => $user_id,
			'user_name'         => $user_name,
			'measure_key'       => (string) ( $goal['measure_key'] ?? '' ),
			'measure_name'      => (string) ( $goal['measure_name'] ?? '' ),
			'start_date'        => (string) ( $goal['start_date'] ?? '' ),
			'end_date'          => (string) ( $goal['end_date'] ?? '' ),
			'current_rate'      => $goal['current_rate'] === null ? '' : (float) $goal['current_rate'],
			'goal_rate'         => $goal['goal_rate'] === null ? '' : (float) $goal['goal_rate'],
			'difference_needed' => $goal['difference_needed'] === null ? '' : (float) $goal['difference_needed'],
			'direction'         => (string) ( $goal['direction'] ?? 'higher' ),
			'status'            => (string) ( $goal['status'] ?? 'active' ),
			'updated_at'        => (string) ( $goal['updated_at'] ?? '' ),
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_ownership_scope_key' ) ) {
	function qualinav_data_hub_mbqip_ownership_scope_key( $value, $fallback = '' ) {
		$key = sanitize_key( (string) $value );
		return $key !== '' ? $key : sanitize_key( (string) $fallback );
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_same_org_users' ) ) {
	function qualinav_data_hub_mbqip_same_org_users( $organization_id ) {
		global $wpdb;

		$current_user_id = get_current_user_id();
		$organization_id = (int) $organization_id;
		$context         = qualinav_data_hub_mbqip_goal_user_context();
		$org_name        = trim( (string) ( $context['org_name'] ?? '' ) );
		$org_key         = sanitize_title( (string) ( $context['org_key'] ?? '' ) );
		$user_ids        = array();

		if ( $current_user_id > 0 ) {
			$user_ids[] = $current_user_id;
		}

		if ( $organization_id > 0 ) {
			$user_ids = array_merge(
				$user_ids,
				array_map(
					'intval',
					(array) $wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID
							   FROM {$wpdb->users}
							  WHERE organization_id = %d",
							$organization_id
						)
					)
				)
			);
		}

		if ( $org_name !== '' ) {
			$user_ids = array_merge(
				$user_ids,
				get_users(
					array(
						'fields'     => 'ids',
						'meta_key'   => 'organization',
						'meta_value' => $org_name,
					)
				)
			);
		}

		if ( $org_key !== '' ) {
			foreach ( array( 'org_key', 'organization_slug', 'organization_key' ) as $meta_key ) {
				$user_ids = array_merge(
					$user_ids,
					get_users(
						array(
							'fields'     => 'ids',
							'meta_key'   => $meta_key,
							'meta_value' => $org_key,
						)
					)
				);
			}
		}

		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		$users    = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			$label = trim( (string) $user->display_name );
			if ( $label === '' ) {
				$label = (string) $user->user_login;
			}
			$users[] = array(
				'id'    => (int) $user->ID,
				'label' => $label,
				'email' => (string) $user->user_email,
			);
		}

		usort(
			$users,
			function( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		return $users;
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_user_in_org' ) ) {
	function qualinav_data_hub_mbqip_user_in_org( $user_id, $organization_id ) {
		global $wpdb;

		$user_id         = (int) $user_id;
		$organization_id = (int) $organization_id;
		if ( $user_id <= 0 ) {
			return true;
		}
		if ( $user_id === get_current_user_id() ) {
			return true;
		}

		if ( $organization_id > 0 ) {
			$matches_user_column = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(1) FROM {$wpdb->users} WHERE ID = %d AND organization_id = %d",
					$user_id,
					$organization_id
				)
			) > 0;
			if ( $matches_user_column ) {
				return true;
			}
		}

		$current_context = qualinav_data_hub_mbqip_goal_user_context();
		$user_context    = function_exists( 'qualinav_data_hub_get_org_context' )
			? qualinav_data_hub_get_org_context( $user_id )
			: array();
		$current_key     = sanitize_title( (string) ( $current_context['org_key'] ?? '' ) );
		$user_key        = sanitize_title( (string) ( $user_context['org_key'] ?? '' ) );
		if ( $current_key !== '' && $user_key !== '' && $current_key === $user_key ) {
			return true;
		}

		$current_org_name = trim( (string) ( $current_context['org_name'] ?? '' ) );
		$user_org_name    = trim( (string) get_user_meta( $user_id, 'organization', true ) );
		return $current_org_name !== '' && strcasecmp( $current_org_name, $user_org_name ) === 0;
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_ownership_payload' ) ) {
	function qualinav_data_hub_mbqip_ownership_payload( $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			return null;
		}
		return array(
			'id'                  => (int) $row['id'],
			'organization_id'     => (int) ( $row['organization_id'] ?? 0 ),
			'organization_name'   => (string) ( $row['organization_name'] ?? '' ),
			'module_key'          => (string) ( $row['module_key'] ?? 'mbqip' ),
			'event_type_key'      => (string) ( $row['event_type_key'] ?? '' ),
			'measure_key'         => (string) ( $row['measure_key'] ?? '' ),
			'measure_name'        => (string) ( $row['measure_name'] ?? '' ),
			'owner_user_id'       => (int) ( $row['owner_user_id'] ?? 0 ),
			'assigned_by_user_id' => (int) ( $row['assigned_by_user_id'] ?? 0 ),
			'assigned_at'         => (string) ( $row['assigned_at'] ?? '' ),
			'updated_at'          => (string) ( $row['updated_at'] ?? '' ),
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_ownership_scope_from_request' ) ) {
	function qualinav_data_hub_mbqip_ownership_scope_from_request() {
		$measure_name = sanitize_text_field( wp_unslash( $_POST['measure_name'] ?? '' ) );
		$measure_key  = qualinav_data_hub_mbqip_ownership_scope_key(
			wp_unslash( $_POST['measure_key'] ?? '' ),
			qualinav_data_hub_mbqip_goal_measure_key( $measure_name )
		);
		$event_key = qualinav_data_hub_mbqip_ownership_scope_key( wp_unslash( $_POST['event_type_key'] ?? '' ), 'mbqip' );
		$module_key = qualinav_data_hub_mbqip_ownership_scope_key( wp_unslash( $_POST['module_key'] ?? '' ), 'mbqip' );

		return array(
			'module_key'     => $module_key ?: 'mbqip',
			'event_type_key' => $event_key ?: 'mbqip',
			'measure_key'    => $measure_key,
			'measure_name'   => $measure_name,
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_ownership_load_handler' ) ) {
	function qualinav_data_hub_mbqip_ownership_load_handler() {
		global $wpdb;

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to load report ownership.' );
		}

		qualinav_data_hub_mbqip_maybe_install();

		$scope = qualinav_data_hub_mbqip_ownership_scope_from_request();
		if ( $scope['measure_key'] === '' ) {
			wp_send_json_error( 'Missing measure.' );
		}

		$context = qualinav_data_hub_mbqip_goal_user_context();
		$table   = qualinav_data_hub_mbqip_report_ownership_table();
		$row     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				   FROM {$table}
				  WHERE org_key = %s
				    AND module_key = %s
				    AND event_type_key = %s
				    AND measure_key = %s
				  LIMIT 1",
				$context['org_key'],
				$scope['module_key'],
				$scope['event_type_key'],
				$scope['measure_key']
			),
			ARRAY_A
		);

		wp_send_json_success( array(
			'ownership' => qualinav_data_hub_mbqip_ownership_payload( $row ),
			'users'     => qualinav_data_hub_mbqip_same_org_users( $context['organization_id'] ),
		) );
	}

	add_action( 'wp_ajax_qualinav_mbqip_report_ownership_load', 'qualinav_data_hub_mbqip_ownership_load_handler' );
	add_action( 'wp_ajax_qualinav_data_ownership_load', 'qualinav_data_hub_mbqip_ownership_load_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_ownership_save_handler' ) ) {
	function qualinav_data_hub_mbqip_ownership_save_handler() {
		global $wpdb;

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to save report ownership.' );
		}

		qualinav_data_hub_mbqip_maybe_install();

		$scope = qualinav_data_hub_mbqip_ownership_scope_from_request();
		if ( $scope['measure_key'] === '' || $scope['measure_name'] === '' ) {
			wp_send_json_error( 'Missing measure.' );
		}

		$context       = qualinav_data_hub_mbqip_goal_user_context();
		$owner_user_id = (int) wp_unslash( $_POST['owner_user_id'] ?? 0 );
		if ( ! qualinav_data_hub_mbqip_user_in_org( $owner_user_id, $context['organization_id'] ) ) {
			wp_send_json_error( 'Selected owner is not part of this organization.' );
		}

		$table       = qualinav_data_hub_mbqip_report_ownership_table();
		$audit_table = qualinav_data_hub_mbqip_report_ownership_audit_table();
		$now         = current_time( 'mysql' );
		$existing    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				   FROM {$table}
				  WHERE org_key = %s
				    AND module_key = %s
				    AND event_type_key = %s
				    AND measure_key = %s
				  LIMIT 1",
				$context['org_key'],
				$scope['module_key'],
				$scope['event_type_key'],
				$scope['measure_key']
			),
			ARRAY_A
		);
		$previous_owner_id = $existing ? (int) ( $existing['owner_user_id'] ?? 0 ) : 0;

		$row = array(
			'organization_id'     => $context['organization_id'] > 0 ? $context['organization_id'] : null,
			'org_key'             => $context['org_key'],
			'organization_name'   => $context['org_name'],
			'module_key'          => $scope['module_key'],
			'event_type_key'      => $scope['event_type_key'],
			'measure_key'         => $scope['measure_key'],
			'measure_name'        => $scope['measure_name'],
			'owner_user_id'       => $owner_user_id > 0 ? $owner_user_id : null,
			'assigned_by_user_id' => get_current_user_id(),
			'updated_at'          => $now,
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

		if ( $existing ) {
			$ownership_id = (int) $existing['id'];
			$wpdb->update( $table, $row, array( 'id' => $ownership_id ), $formats, array( '%d' ) );
		} else {
			$row['assigned_at'] = $now;
			$wpdb->insert( $table, $row, array_merge( $formats, array( '%s' ) ) );
			$ownership_id = (int) $wpdb->insert_id;
		}

		if ( $previous_owner_id !== $owner_user_id ) {
			$wpdb->insert(
				$audit_table,
				array(
					'ownership_id'           => $ownership_id,
					'organization_id'        => $context['organization_id'] > 0 ? $context['organization_id'] : null,
					'org_key'                => $context['org_key'],
					'organization_name'      => $context['org_name'],
					'module_key'             => $scope['module_key'],
					'event_type_key'         => $scope['event_type_key'],
					'measure_key'            => $scope['measure_key'],
					'measure_name'           => $scope['measure_name'],
					'previous_owner_user_id' => $previous_owner_id > 0 ? $previous_owner_id : null,
					'new_owner_user_id'      => $owner_user_id > 0 ? $owner_user_id : null,
					'changed_by_user_id'     => get_current_user_id(),
					'changed_at'             => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
			);
		}

		$saved = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $ownership_id ),
			ARRAY_A
		);

		wp_send_json_success( array(
			'ownership' => qualinav_data_hub_mbqip_ownership_payload( $saved ),
			'users'     => qualinav_data_hub_mbqip_same_org_users( $context['organization_id'] ),
			'message'   => 'Report owner saved.',
		) );
	}

	add_action( 'wp_ajax_qualinav_mbqip_report_ownership_save', 'qualinav_data_hub_mbqip_ownership_save_handler' );
	add_action( 'wp_ajax_qualinav_data_ownership_save', 'qualinav_data_hub_mbqip_ownership_save_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_load_handler' ) ) {
	function qualinav_data_hub_mbqip_goal_load_handler() {
		global $wpdb;

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to load measure goals.' );
		}

		qualinav_data_hub_mbqip_maybe_install();

		$measure_name = sanitize_text_field( wp_unslash( $_POST['measure_name'] ?? '' ) );
		$measure_key  = qualinav_data_hub_mbqip_goal_measure_key( $measure_name, wp_unslash( $_POST['measure_key'] ?? '' ) );
		if ( $measure_key === '' ) {
			wp_send_json_error( 'Missing measure.' );
		}

		$context = qualinav_data_hub_mbqip_goal_user_context();
		$table   = qualinav_data_hub_mbqip_measure_goals_table();
		$goals   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				   FROM {$table}
				  WHERE org_key = %s
				    AND measure_key = %s
				    AND status = 'active'
				  ORDER BY COALESCE(end_date, '9999-12-31') DESC, updated_at DESC, id DESC",
				$context['org_key'],
				$measure_key
			),
			ARRAY_A
		);

		$today = current_time( 'Y-m-d' );
		$current_goal = null;
		$goal_payloads = array();
		foreach ( $goals as $goal ) {
			$payload = qualinav_data_hub_mbqip_goal_payload( $goal );
			if ( ! $payload ) {
				continue;
			}
			$goal_payloads[] = $payload;
			$end_date = (string) ( $goal['end_date'] ?? '' );
			if ( $current_goal === null && ( $end_date === '' || $end_date >= $today ) ) {
				$current_goal = $payload;
			}
		}

		wp_send_json_success( array(
			'goal'  => $current_goal,
			'goals' => $goal_payloads,
		) );
	}

	add_action( 'wp_ajax_qualinav_mbqip_goal_load', 'qualinav_data_hub_mbqip_goal_load_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_save_handler' ) ) {
	function qualinav_data_hub_mbqip_goal_save_handler() {
		global $wpdb;

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to save measure goals.' );
		}

		qualinav_data_hub_mbqip_maybe_install();

		$measure_name = sanitize_text_field( wp_unslash( $_POST['measure_name'] ?? '' ) );
		$measure_key  = qualinav_data_hub_mbqip_goal_measure_key( $measure_name, wp_unslash( $_POST['measure_key'] ?? '' ) );
		if ( $measure_key === '' || $measure_name === '' ) {
			wp_send_json_error( 'Missing measure.' );
		}

		$direction = sanitize_key( wp_unslash( $_POST['direction'] ?? 'higher' ) );
		if ( ! in_array( $direction, array( 'higher', 'lower' ), true ) ) {
			$direction = 'higher';
		}
		$current_rate = qualinav_data_hub_mbqip_goal_decimal_or_null( wp_unslash( $_POST['current_rate'] ?? '' ) );
		$goal_rate    = qualinav_data_hub_mbqip_goal_decimal_or_null( wp_unslash( $_POST['goal_rate'] ?? '' ) );
		$difference   = qualinav_data_hub_mbqip_goal_decimal_or_null( wp_unslash( $_POST['difference_needed'] ?? '' ) );
		$start_date   = qualinav_data_hub_mbqip_goal_date_or_null( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date     = qualinav_data_hub_mbqip_goal_date_or_null( wp_unslash( $_POST['end_date'] ?? '' ) );
		if ( null === $start_date || null === $end_date || null === $current_rate || null === $goal_rate || null === $difference ) {
			wp_send_json_error( 'Complete all goal fields before saving.' );
		}
		if ( strtotime( $end_date ) < strtotime( $start_date ) ) {
			wp_send_json_error( 'End date must be on or after start date.' );
		}

		$context = qualinav_data_hub_mbqip_goal_user_context();
		$table   = qualinav_data_hub_mbqip_measure_goals_table();
		$now     = current_time( 'mysql' );
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				   FROM {$table}
				  WHERE org_key = %s
				    AND measure_key = %s
				    AND status = 'active'
				    AND (end_date IS NULL OR end_date >= %s)
				  ORDER BY updated_at DESC, id DESC
				  LIMIT 1",
				$context['org_key'],
				$measure_key,
				current_time( 'Y-m-d' )
			)
		);

		$row = array(
			'organization_id'   => $context['organization_id'] > 0 ? $context['organization_id'] : null,
			'org_key'           => $context['org_key'],
			'organization_name' => $context['org_name'],
			'user_id'           => get_current_user_id(),
			'measure_key'       => $measure_key,
			'measure_name'      => $measure_name,
			'start_date'        => $start_date,
			'end_date'          => $end_date,
			'current_rate'      => $current_rate,
			'goal_rate'         => $goal_rate,
			'difference_needed' => $difference,
			'direction'         => $direction,
			'status'            => 'active',
			'updated_at'        => $now,
		);
		$formats = array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' );

		if ( $existing_id > 0 ) {
			$wpdb->update( $table, $row, array( 'id' => $existing_id ), $formats, array( '%d' ) );
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $table, $row, array_merge( $formats, array( '%s' ) ) );
			$existing_id = (int) $wpdb->insert_id;
		}

		$goal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $existing_id ),
			ARRAY_A
		);
		$goals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				   FROM {$table}
				  WHERE org_key = %s
				    AND measure_key = %s
				    AND status = 'active'
				  ORDER BY COALESCE(end_date, '9999-12-31') DESC, updated_at DESC, id DESC",
				$context['org_key'],
				$measure_key
			),
			ARRAY_A
		);
		$goal_payloads = array_values( array_filter( array_map( 'qualinav_data_hub_mbqip_goal_payload', $goals ) ) );

		wp_send_json_success( array(
			'goal'    => qualinav_data_hub_mbqip_goal_payload( $goal ),
			'goals'   => $goal_payloads,
			'message' => 'Measure goal saved.',
		) );
	}

	add_action( 'wp_ajax_qualinav_mbqip_goal_save', 'qualinav_data_hub_mbqip_goal_save_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_mbqip_goal_archive_handler' ) ) {
	function qualinav_data_hub_mbqip_goal_archive_handler() {
		global $wpdb;

		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to remove measure goals.' );
		}

		qualinav_data_hub_mbqip_maybe_install();

		$goal_id = absint( wp_unslash( $_POST['goal_id'] ?? 0 ) );
		if ( $goal_id <= 0 ) {
			wp_send_json_error( 'Missing goal.' );
		}

		$context = qualinav_data_hub_mbqip_goal_user_context();
		$table   = qualinav_data_hub_mbqip_measure_goals_table();
		$goal    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				   FROM {$table}
				  WHERE id = %d
				    AND org_key = %s
				    AND status = 'active'
				  LIMIT 1",
				$goal_id,
				$context['org_key']
			),
			ARRAY_A
		);

		if ( ! $goal ) {
			wp_send_json_error( 'Could not find this goal.' );
		}

		$updated = $wpdb->update(
			$table,
			array(
				'status'     => 'archived',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $goal_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Could not remove this goal.' );
		}

		$goals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				   FROM {$table}
				  WHERE org_key = %s
				    AND measure_key = %s
				    AND status = 'active'
				  ORDER BY COALESCE(end_date, '9999-12-31') DESC, updated_at DESC, id DESC",
				$context['org_key'],
				(string) $goal['measure_key']
			),
			ARRAY_A
		);

		$today         = current_time( 'Y-m-d' );
		$current_goal  = null;
		$goal_payloads = array();
		foreach ( $goals as $active_goal ) {
			$payload = qualinav_data_hub_mbqip_goal_payload( $active_goal );
			if ( ! $payload ) {
				continue;
			}
			$goal_payloads[] = $payload;
			$end_date = (string) ( $active_goal['end_date'] ?? '' );
			if ( null === $current_goal && ( '' === $end_date || $end_date >= $today ) ) {
				$current_goal = $payload;
			}
		}

		wp_send_json_success( array(
			'goal'        => $current_goal,
			'goals'       => $goal_payloads,
			'archived_id' => $goal_id,
			'measure_key' => (string) $goal['measure_key'],
			'message'     => 'Past goal removed.',
		) );
	}

	add_action( 'wp_ajax_qualinav_mbqip_goal_archive', 'qualinav_data_hub_mbqip_goal_archive_handler' );
}
