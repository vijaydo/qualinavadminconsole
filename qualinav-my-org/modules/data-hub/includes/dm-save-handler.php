<?php
/**
 * Data Management — manual-entry save handler.
 *
 * Listens on `wp_ajax_dm_save_data`. Was historically in qualinav-pages.php;
 * moved here on 2026-05-08 so the Data Hub plugin owns its full back-end.
 *
 * Writes the manual rows out as a per-measure CSV under
 * `uploads/qualinav-dm/{org_key}/` and tracks it in the
 * `dm_org_folder_files_{org_key}` option. Includes SHA-256 content-hash
 * dedup so re-saving identical data doesn't accumulate duplicates.
 *
 * @package Qualinav_Data_Hub
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'qualinav_data_hub_dm_harden_upload_dir' ) ) {
	/**
	 * Drops an .htaccess + index.php into the Data Management upload tree so
	 * stored files can't be executed as scripts and the folder can't be
	 * browsed. Cheap to re-run — only writes a file when it's missing.
	 */
	function qualinav_data_hub_dm_harden_upload_dir( $dir ) {
		$dir = rtrim( (string) $dir, '/\\' );
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return;
		}
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules  = "# Data Hub upload hardening — do not remove.\n";
			$rules .= "Options -Indexes\n";
			$rules .= "<FilesMatch \"(?i)\\.(php|php[0-9]|phtml|phps|pht|phar|cgi|pl|py|asp|aspx|jsp|sh|exe|bat)$\">\n";
			$rules .= "\t<IfModule mod_authz_core.c>\n\t\tRequire all denied\n\t</IfModule>\n";
			$rules .= "\t<IfModule !mod_authz_core.c>\n\t\tOrder allow,deny\n\t\tDeny from all\n\t</IfModule>\n";
			$rules .= "</FilesMatch>\n";
			@file_put_contents( $htaccess, $rules );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}

if ( ! function_exists( 'qualinav_data_hub_get_org_context' ) ) {
	function qualinav_data_hub_get_org_context( $user_id ) {
		global $wpdb;

		$user_id = (int) $user_id;
			$context = array(
				'organization_id' => 0,
				'org_name'   => '',
				'org_slug'   => '',
				'org_key'    => '',
				'state_code' => '',
			);

		if ( $user_id > 0 ) {
			$user_org = $wpdb->get_row( $wpdb->prepare(
				"SELECT u.organization_id, u.state_id, o.name AS org_name, o.slug AS org_slug, o.state_id AS org_state_id
				   FROM {$wpdb->users} u
				   LEFT JOIN {$wpdb->prefix}organizations o ON o.id = u.organization_id
				  WHERE u.ID = %d
				  LIMIT 1",
				$user_id
			), ARRAY_A );

				if ( is_array( $user_org ) ) {
					$context['organization_id'] = (int) ( $user_org['organization_id'] ?? 0 );
					$context['org_name'] = trim( (string) ( $user_org['org_name'] ?? '' ) );
					$context['org_slug'] = trim( (string) ( $user_org['org_slug'] ?? '' ) );
				$state_id = (int) ( $user_org['org_state_id'] ?: ( $user_org['state_id'] ?? 0 ) );
				if ( $state_id > 0 ) {
					$state_code = $wpdb->get_var( $wpdb->prepare(
						"SELECT code FROM {$wpdb->prefix}states WHERE id = %d LIMIT 1",
						$state_id
					) );
					$context['state_code'] = strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $state_code ) ) );
				}
			}

			if ( $context['org_name'] === '' ) {
				$context['org_name'] = trim( (string) get_user_meta( $user_id, 'organization', true ) );
			}
			if ( $context['state_code'] === '' ) {
				foreach ( array( 'state', 'states', 'user_state' ) as $state_meta_key ) {
					$state_meta = trim( (string) get_user_meta( $user_id, $state_meta_key, true ) );
					if ( $state_meta !== '' ) {
						$context['state_code'] = strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( sanitize_text_field( $state_meta ) ) ) );
						break;
					}
				}
			}
		}

		if ( $context['org_slug'] === '' && $context['org_name'] !== '' ) {
			$context['org_slug'] = sanitize_title( $context['org_name'] );
		}
		if ( $context['org_slug'] === '' ) {
			$context['org_slug'] = 'user-' . $user_id;
		}
		$context['org_key'] = sanitize_title( $context['org_slug'] );
		if ( $context['org_key'] === '' ) {
			$context['org_key'] = 'user-' . $user_id;
		}

		return $context;
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_drive_queue_option' ) ) {
	function qualinav_data_hub_dm_drive_queue_option() {
		return 'qualinav_data_hub_drive_sync_queue';
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_drive_sync_url' ) ) {
	function qualinav_data_hub_dm_drive_sync_url( $job_id, $token ) {
		return add_query_arg(
			array(
				'action' => 'dm_process_drive_sync',
				'job_id' => rawurlencode( (string) $job_id ),
				'token'  => rawurlencode( (string) $token ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_enqueue_drive_sync' ) ) {
	function qualinav_data_hub_dm_enqueue_drive_sync( array $job ) {
		$queue = get_option( qualinav_data_hub_dm_drive_queue_option(), array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		$job_id = md5( wp_json_encode( $job ) . '|' . microtime( true ) . '|' . wp_rand() );
		$token = wp_generate_password( 32, false, false );
		$job['job_id'] = $job_id;
		$job['token'] = $token;
		$job['attempts'] = 0;
		$job['created_at'] = time();
		$queue[ $job_id ] = $job;
		update_option( qualinav_data_hub_dm_drive_queue_option(), $queue, false );

		if ( ! wp_next_scheduled( 'qualinav_data_hub_process_drive_sync_job', array( $job_id ) ) ) {
			wp_schedule_single_event( time() + 60, 'qualinav_data_hub_process_drive_sync_job', array( $job_id ) );
		}

		wp_remote_post( admin_url( 'admin-ajax.php' ), array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'body'      => array(
				'action' => 'dm_process_drive_sync',
				'job_id' => $job_id,
				'token'  => $token,
			),
		) );

		return $job_id;
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_find_queued_drive_sync' ) ) {
	function qualinav_data_hub_dm_find_queued_drive_sync( $option_key, $folder_id, $filename ) {
		$queue = get_option( qualinav_data_hub_dm_drive_queue_option(), array() );
		if ( ! is_array( $queue ) ) {
			return null;
		}
		$option_key = (string) $option_key;
		$folder_id  = (string) $folder_id;
		$filename   = (string) $filename;
		foreach ( $queue as $job_id => $job ) {
			if ( ! is_array( $job ) ) {
				continue;
			}
			if (
				(string) ( $job['option_key'] ?? '' ) === $option_key
				&& (string) ( $job['folder_id'] ?? '' ) === $folder_id
				&& (string) ( $job['filename'] ?? '' ) === $filename
			) {
				$job['job_id'] = (string) $job_id;
				return $job;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_update_drive_record' ) ) {
	function qualinav_data_hub_dm_update_drive_record( array $job, array $updates, $remove_job = false ) {
		$option_key = (string) ( $job['option_key'] ?? '' );
		$folder_id = (string) ( $job['folder_id'] ?? '' );
		$filename   = (string) ( $job['filename'] ?? '' );
		if ( $option_key === '' || $folder_id === '' || $filename === '' ) {
			return false;
		}
		$folder_files = get_option( $option_key, array() );
		if ( ! is_array( $folder_files ) || empty( $folder_files[ $folder_id ] ) || ! is_array( $folder_files[ $folder_id ] ) ) {
			return false;
		}
		$updated = false;
		foreach ( $folder_files[ $folder_id ] as $idx => $record ) {
			if ( ! is_array( $record ) || (string) ( $record['name'] ?? '' ) !== $filename ) {
				continue;
			}
			foreach ( $updates as $key => $value ) {
				if ( $value === null ) {
					unset( $record[ $key ] );
				} else {
					$record[ $key ] = $value;
				}
			}
			$folder_files[ $folder_id ][ $idx ] = $record;
			$updated = true;
			break;
		}
		if ( $updated ) {
			update_option( $option_key, $folder_files, false );
		}
		if ( $remove_job && ! empty( $job['job_id'] ) ) {
			$queue = get_option( qualinav_data_hub_dm_drive_queue_option(), array() );
			if ( is_array( $queue ) && isset( $queue[ $job['job_id'] ] ) ) {
				unset( $queue[ $job['job_id'] ] );
				update_option( qualinav_data_hub_dm_drive_queue_option(), $queue, false );
			}
		}
		return $updated;
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_process_drive_sync_job' ) ) {
	function qualinav_data_hub_dm_process_drive_sync_job( $job_id, $token = '' ) {
		$job_id = (string) $job_id;
		if ( $job_id === '' || ! class_exists( 'Qualinav_Data_Hub_Drive' ) ) {
			return false;
		}
		$queue = get_option( qualinav_data_hub_dm_drive_queue_option(), array() );
		if ( ! is_array( $queue ) || empty( $queue[ $job_id ] ) || ! is_array( $queue[ $job_id ] ) ) {
			return false;
		}
		$job = $queue[ $job_id ];
		if ( $token !== '' && ! hash_equals( (string) ( $job['token'] ?? '' ), (string) $token ) ) {
			return false;
		}
		$job['job_id'] = $job_id;
		$job['attempts'] = (int) ( $job['attempts'] ?? 0 ) + 1;
		$queue[ $job_id ] = $job;
		update_option( qualinav_data_hub_dm_drive_queue_option(), $queue, false );

		$local_path = (string) ( $job['local_path'] ?? '' );
		if ( $local_path === '' || ! is_readable( $local_path ) ) {
			qualinav_data_hub_dm_update_drive_record( $job, array(
				'drive_sync_status' => 'failed',
				'drive_error'       => 'Temporary local file is no longer available for Drive sync.',
			), true );
			return false;
		}

		$drive_meta = Qualinav_Data_Hub_Drive::mirror_local_file(
			(int) ( $job['user_id'] ?? 0 ),
			(string) ( $job['folder_id'] ?? '' ),
			$local_path,
			(string) ( $job['filename'] ?? '' ),
			(string) ( $job['mime'] ?? 'text/csv' ),
			(string) ( $job['measure'] ?? '' )
		);

		if ( ! empty( $drive_meta['drive_file_id'] ) ) {
			@unlink( $local_path );
			qualinav_data_hub_dm_update_drive_record( $job, array_merge( $drive_meta, array(
				'drive_sync_status' => 'synced',
				'drive_error'       => null,
				'local_staging_path' => null,
				'url'               => '',
			) ), true );
			return true;
		}

		$error = ! empty( $drive_meta['drive_error'] ) ? (string) $drive_meta['drive_error'] : 'Google Drive sync failed.';
		qualinav_data_hub_dm_update_drive_record( $job, array(
			'drive_sync_status' => 'failed',
			'drive_error'       => $error,
		), false );
		if ( (int) ( $job['attempts'] ?? 0 ) < 3 && ! wp_next_scheduled( 'qualinav_data_hub_process_drive_sync_job', array( $job_id ) ) ) {
			wp_schedule_single_event( time() + 300, 'qualinav_data_hub_process_drive_sync_job', array( $job_id ) );
		}
		return false;
	}
	add_action( 'qualinav_data_hub_process_drive_sync_job', 'qualinav_data_hub_dm_process_drive_sync_job', 10, 1 );
}

if ( ! function_exists( 'qualinav_data_hub_dm_process_drive_sync_ajax' ) ) {
	function qualinav_data_hub_dm_process_drive_sync_ajax() {
		$job_id = isset( $_REQUEST['job_id'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['job_id'] ) ) : '';
		$token  = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['token'] ) ) : '';
		$ok = qualinav_data_hub_dm_process_drive_sync_job( $job_id, $token );
		wp_send_json( array( 'success' => (bool) $ok ) );
	}
	add_action( 'wp_ajax_dm_process_drive_sync', 'qualinav_data_hub_dm_process_drive_sync_ajax' );
	add_action( 'wp_ajax_nopriv_dm_process_drive_sync', 'qualinav_data_hub_dm_process_drive_sync_ajax' );
}

if ( ! function_exists( 'qualinav_data_hub_dm_find_current_user_file_record' ) ) {
	function qualinav_data_hub_dm_find_current_user_file_record( $file_name ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return null;
		}
		$org_context = qualinav_data_hub_get_org_context( $user_id );
		$option_key = 'dm_org_folder_files_' . (string) ( $org_context['org_key'] ?? '' );
		$folder_files = get_option( $option_key, array() );
		if ( ! is_array( $folder_files ) ) {
			return null;
		}
		$file_name = sanitize_file_name( (string) $file_name );
		foreach ( $folder_files as $folder_id => $records ) {
			if ( ! is_array( $records ) ) { continue; }
			foreach ( $records as $idx => $record ) {
				if ( is_array( $record ) && (string) ( $record['name'] ?? '' ) === $file_name ) {
					return array( $option_key, (string) $folder_id, (int) $idx, $record );
				}
			}
		}
		return null;
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_download_saved_file_handler' ) ) {
	function qualinav_data_hub_dm_download_saved_file_handler() {
		if ( ! is_user_logged_in() ) {
			status_header( 403 );
			exit;
		}
		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		$file_name = isset( $_GET['file_name'] ) ? sanitize_file_name( wp_unslash( (string) $_GET['file_name'] ) ) : '';
		$found = qualinav_data_hub_dm_find_current_user_file_record( $file_name );
		if ( ! $found ) {
			status_header( 404 );
			exit;
		}
		$record = $found[3];
		$display_name = sanitize_file_name( (string) ( $record['name'] ?? $file_name ) );
		$mime = sanitize_text_field( (string) ( $record['type'] ?? 'text/csv' ) );

		if ( ! empty( $record['drive_file_id'] ) && class_exists( 'Qualinav_Data_Hub_Drive' ) ) {
			try {
				$drive = Qualinav_Data_Hub_Drive::service();
				$response = $drive->files->get( (string) $record['drive_file_id'], array(
					'alt'               => 'media',
					'supportsAllDrives' => true,
				) );
				if ( is_object( $response ) && method_exists( $response, 'getBody' ) ) {
					$body = $response->getBody();
					$content = is_object( $body ) && method_exists( $body, 'getContents' ) ? $body->getContents() : (string) $body;
				} else {
					$content = (string) $response;
				}
				header( 'Content-Type: ' . ( $mime !== '' ? $mime : 'application/octet-stream' ) );
				header( 'Content-Disposition: attachment; filename="' . $display_name . '"' );
				header( 'Content-Length: ' . strlen( $content ) );
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			} catch ( Throwable $e ) {
				error_log( '[Data Hub] Drive download failed for "' . $display_name . '": ' . $e->getMessage() );
				status_header( 502 );
				exit;
			}
		}

		$url = (string) ( $record['url'] ?? '' );
		if ( $url !== '' ) {
			wp_safe_redirect( $url );
			exit;
		}
		status_header( 404 );
		exit;
	}
	add_action( 'wp_ajax_dm_download_saved_file', 'qualinav_data_hub_dm_download_saved_file_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_dm_drive_status_handler' ) ) {
	function qualinav_data_hub_dm_drive_status_handler() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}
		check_ajax_referer( 'dm_save_nonce', 'nonce' );
		$file_name = isset( $_POST['file_name'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['file_name'] ) ) : '';
		$found = qualinav_data_hub_dm_find_current_user_file_record( $file_name );
		if ( ! $found ) {
			wp_send_json_error( 'File not found' );
		}
		list( $option_key, $folder_id, $record_index, $record ) = $found;
		unset( $record_index );
		if (
			is_array( $record )
			&& (string) ( $record['drive_sync_status'] ?? '' ) === 'pending'
			&& class_exists( 'Qualinav_Data_Hub_Drive' )
			&& Qualinav_Data_Hub_Drive::is_enabled()
		) {
			$local_staging_path = (string) ( $record['local_staging_path'] ?? '' );
			$job = array(
				'user_id'    => get_current_user_id(),
				'folder_id'  => (string) $folder_id,
				'option_key' => (string) $option_key,
				'filename'   => (string) ( $record['name'] ?? $file_name ),
				'local_path' => $local_staging_path,
				'mime'       => sanitize_text_field( (string) ( $record['type'] ?? 'text/csv' ) ),
				'measure'    => sanitize_text_field( (string) ( $record['measure'] ?? '' ) ),
			);
			$queued_job = qualinav_data_hub_dm_find_queued_drive_sync( $option_key, $folder_id, (string) ( $record['name'] ?? $file_name ) );
			if ( $local_staging_path === '' || ! is_readable( $local_staging_path ) ) {
				qualinav_data_hub_dm_update_drive_record( $job, array(
					'drive_sync_status' => 'failed',
					'drive_error'       => 'Temporary local file is no longer available for Drive sync.',
					'drive_sync_delayed' => null,
				), true );
			} elseif ( ! $queued_job ) {
				$retry_count = (int) ( $record['drive_sync_retries'] ?? 0 );
				$last_retry  = (int) ( $record['drive_sync_last_retry_at'] ?? 0 );
				if ( $retry_count >= 3 ) {
					qualinav_data_hub_dm_update_drive_record( $job, array(
						'drive_sync_status' => 'failed',
						'drive_error'       => 'Drive sync did not complete after multiple retry attempts.',
						'drive_sync_delayed' => null,
					), true );
				} elseif ( time() - $last_retry >= 30 ) {
					$new_job_id = qualinav_data_hub_dm_enqueue_drive_sync( $job );
					qualinav_data_hub_dm_update_drive_record( $job, array(
						'drive_sync_status'        => 'pending',
						'drive_error'              => null,
						'drive_sync_delayed'       => true,
						'drive_sync_job_id'        => $new_job_id,
						'drive_sync_retries'       => $retry_count + 1,
						'drive_sync_last_retry_at' => time(),
					), false );
				}
			} elseif ( (int) ( $queued_job['created_at'] ?? 0 ) > 0 && time() - (int) $queued_job['created_at'] > 120 ) {
				qualinav_data_hub_dm_update_drive_record( $job, array(
					'drive_sync_delayed' => true,
				), false );
			}
			$found = qualinav_data_hub_dm_find_current_user_file_record( $file_name );
			if ( $found ) {
				$record = $found[3];
			}
		}
		unset( $record['local_staging_path'] );
		wp_send_json_success( array( 'file' => $record ) );
	}
	add_action( 'wp_ajax_dm_drive_sync_status', 'qualinav_data_hub_dm_drive_status_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_measure_filename_slug' ) ) {
	function qualinav_data_hub_measure_filename_slug( $measure ) {
		$slug = str_replace(
			array( '/', '\\', '—', '–', '−' ),
			'-',
			(string) $measure
		);
		$slug = preg_replace( '/\s*-\s*/', '-', $slug );
		$slug = sanitize_file_name( $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( (string) $slug, '-_' );

		return $slug !== '' ? $slug : 'measure';
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_decimal_or_null' ) ) {
	function qualinav_data_hub_dm_decimal_or_null( $value ) {
		$value = trim( str_replace( array( '%', ',' ), '', (string) $value ) );
		if ( $value === '' || ! is_numeric( $value ) ) {
			return null;
		}
		return (float) $value;
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_sync_mbqip_submission_rows' ) ) {
	function qualinav_data_hub_dm_sync_mbqip_submission_rows( $args ) {
		global $wpdb;

		if (
			! function_exists( 'qualinav_data_hub_mbqip_maybe_install' )
			|| ! function_exists( 'qualinav_data_hub_mbqip_submissions_table' )
			|| ! function_exists( 'qualinav_data_hub_mbqip_submission_values_table' )
			|| empty( $args['raw_rows'] )
			|| ! is_array( $args['raw_rows'] )
		) {
			return;
		}

		qualinav_data_hub_mbqip_maybe_install();

		$submissions_table = qualinav_data_hub_mbqip_submissions_table();
		$values_table      = qualinav_data_hub_mbqip_submission_values_table();
		$measure_name      = sanitize_text_field( (string) ( $args['measure'] ?? '' ) );
		$measure_key       = function_exists( 'qualinav_data_hub_mbqip_goal_measure_key' )
			? qualinav_data_hub_mbqip_goal_measure_key( $measure_name )
			: sanitize_key( sanitize_title( str_replace( array( '—', '/', ':' ), '-', $measure_name ) ) );
		if ( $measure_name === '' || $measure_key === '' ) {
			return;
		}

		$org_context = is_array( $args['org_context'] ?? null ) ? $args['org_context'] : array();
		$org_key     = sanitize_title( (string) ( $org_context['org_key'] ?? '' ) );
		if ( $org_key === '' ) {
			$org_key = 'user-' . (int) ( $args['user_id'] ?? 0 );
		}
		$now = current_time( 'mysql' );
		$reporting_period_type = in_array( (string) ( $args['template_type'] ?? '' ), array( 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true )
			? 'period'
			: 'annual';

		foreach ( $args['raw_rows'] as $idx => $raw_row ) {
			if ( ! is_array( $raw_row ) ) {
				continue;
			}
			$year = (int) ( $raw_row['year'] ?? 0 );
			if ( $year < 1900 ) {
				continue;
			}
			$row_metric = trim( (string) ( $raw_row['metric'] ?? '' ) );
			if ( $row_metric !== '' && strcasecmp( $row_metric, $measure_name ) !== 0 ) {
				continue;
			}
			$period = sanitize_text_field( (string) ( $raw_row['period'] ?? '' ) );
			$date_reported = '';
			if ( ! empty( $raw_row['date_reported'] ) ) {
				$timestamp = strtotime( (string) $raw_row['date_reported'] );
				$date_reported = $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
			}

			$file_name = sanitize_file_name( (string) ( $args['file_name'] ?? '' ) );
			$file_url  = esc_url_raw( (string) ( $args['file_url'] ?? '' ) );
			$existing_id = $file_name !== '' ? (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id
				   FROM {$submissions_table}
				  WHERE org_key = %s
				    AND measure_key = %s
				    AND reporting_year = %d
				    AND reporting_period = %s
				    AND status = 'active'
				    AND is_current = 1
				    AND file_name = %s
				  ORDER BY updated_at DESC, id DESC
				  LIMIT 1",
				$org_key,
				$measure_key,
				$year,
				$period,
				$file_name
			) ) : 0;

			$submission_row = array(
					'organization_id'       => ! empty( $org_context['organization_id'] ) ? (int) $org_context['organization_id'] : null,
					'org_key'               => $org_key,
					'organization_name'     => sanitize_text_field( (string) ( $org_context['org_name'] ?? '' ) ),
					'user_id'               => (int) ( $args['user_id'] ?? 0 ),
					'measure_key'           => $measure_key,
					'measure_name'          => $measure_name,
					'event_type'            => sanitize_text_field( (string) ( $args['folder_id'] ?? '' ) ),
					'reporting_period_type' => $reporting_period_type,
					'reporting_year'        => $year,
					'reporting_period'      => $period,
					'date_reported'         => $date_reported !== '' ? $date_reported : null,
					'source_type'           => sanitize_key( (string) ( $args['source_type'] ?? 'manual' ) ),
					'file_name'             => $file_name,
					'file_url'              => $file_url,
					'status'                => 'active',
					'is_current'            => 1,
					'updated_at'            => $now,
			);
			$submission_formats = array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

			if ( $existing_id > 0 ) {
				$wpdb->update( $submissions_table, $submission_row, array( 'id' => $existing_id ), $submission_formats, array( '%d' ) );
				$wpdb->delete( $values_table, array( 'submission_id' => $existing_id ), array( '%d' ) );
				$submission_id = $existing_id;
			} else {
				$wpdb->update(
					$submissions_table,
					array(
						'is_current'  => 0,
						'status'      => 'archived',
						'archived_at' => $now,
						'updated_at'  => $now,
					),
					array(
						'org_key'          => $org_key,
						'measure_key'      => $measure_key,
						'reporting_year'   => $year,
						'reporting_period' => $period,
						'status'           => 'active',
					),
					array( '%d', '%s', '%s', '%s' ),
					array( '%s', '%s', '%d', '%s', '%s' )
				);
				$submission_row['created_at'] = $now;
				$wpdb->insert(
					$submissions_table,
					$submission_row,
					array_merge( $submission_formats, array( '%s' ) )
				);
				$submission_id = (int) $wpdb->insert_id;
			}
			if ( $submission_id <= 0 ) {
				continue;
			}

			$sort_order = 0;
			foreach ( $raw_row as $field_key => $field_value ) {
				$field_key = sanitize_key( (string) $field_key );
				if ( $field_key === '' ) {
					continue;
				}
				$field_text = sanitize_text_field( (string) $field_value );
				$field_numeric = qualinav_data_hub_dm_decimal_or_null( $field_text );
				$wpdb->insert(
					$values_table,
					array(
						'submission_id'       => $submission_id,
						'row_index'           => (int) $idx,
						'field_key'           => $field_key,
						'field_label'         => ucwords( str_replace( '_', ' ', $field_key ) ),
						'field_value'         => $field_text,
						'field_value_numeric' => $field_numeric,
						'field_type'          => $field_numeric === null ? 'text' : 'number',
						'sort_order'          => $sort_order,
						'created_at'          => $now,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%s' )
				);
				$sort_order++;
			}
		}
	}
}

if ( ! function_exists( 'qualinav_data_hub_dm_save_data_handler' ) ) {

	function qualinav_data_hub_dm_save_data_handler() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		$measure   = sanitize_text_field( $_POST['measure'] ?? '' );
		$folder_id = sanitize_key( $_POST['folder_id'] ?? '' );
		$rows      = json_decode( wp_unslash( $_POST['rows'] ?? '[]' ), true );
		$template_type = sanitize_key( $_POST['template_type'] ?? '' );
		$original_filename = isset( $_POST['original_filename'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['original_filename'] ) ) : '';
		$is_manual_source  = ( $original_filename === '' );
		$overwrite_file_name = isset( $_POST['overwrite_file_name'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['overwrite_file_name'] ) ) : '';
		$overwrite_file_names = array();
		if ( isset( $_POST['overwrite_file_names'] ) ) {
			$decoded_overwrite_names = json_decode( wp_unslash( (string) $_POST['overwrite_file_names'] ), true );
			if ( is_array( $decoded_overwrite_names ) ) {
				foreach ( $decoded_overwrite_names as $overwrite_name ) {
					$overwrite_name = sanitize_file_name( (string) $overwrite_name );
					if ( $overwrite_name !== '' && ! in_array( $overwrite_name, $overwrite_file_names, true ) ) {
						$overwrite_file_names[] = $overwrite_name;
					}
				}
			}
		}

		if ( ! $measure || ! $folder_id || empty( $rows ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		if ( ! in_array( $template_type, array( 'elements_checklist', 'antibiotic_stewardship', 'quarter_median' ), true ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( $template_type === 'edtc_checklist' && ! empty( $row['components'] ) && is_array( $row['components'] ) ) {
					$summary_num = isset( $row['elements_met_count'] ) ? trim( (string) $row['elements_met_count'] ) : '';
					$summary_den = isset( $row['elements_selected_count'] ) ? trim( (string) $row['elements_selected_count'] ) : '';
					if ( $summary_num !== '' && $summary_den !== '' && is_numeric( $summary_num ) && is_numeric( $summary_den ) && (float) $summary_den < (float) $summary_num ) {
						wp_send_json_error( 'Denominator cannot be lower than numerator.' );
					}
					foreach ( $row['components'] as $component_row ) {
						if ( ! is_array( $component_row ) ) {
							continue;
						}
						$num_text = isset( $component_row['num'] ) ? trim( (string) $component_row['num'] ) : '';
						$den_text = isset( $component_row['den'] ) ? trim( (string) $component_row['den'] ) : '';
						if ( $num_text === '' || $den_text === '' || ! is_numeric( $num_text ) || ! is_numeric( $den_text ) ) {
							continue;
						}
						if ( (float) $den_text < (float) $num_text ) {
							wp_send_json_error( 'Denominator cannot be lower than numerator.' );
						}
					}
					continue;
				}
				$num_text = isset( $row['num'] ) ? trim( (string) $row['num'] ) : '';
				$den_text = isset( $row['den'] ) ? trim( (string) $row['den'] ) : '';
				if ( $num_text === '' || $den_text === '' || ! is_numeric( $num_text ) || ! is_numeric( $den_text ) ) {
					continue;
				}
				if ( (float) $den_text < (float) $num_text ) {
					wp_send_json_error( 'Denominator cannot be lower than numerator.' );
				}
			}
		}

		$user_id  = get_current_user_id();
		$org_context = qualinav_data_hub_get_org_context( $user_id );
		$org_key = $org_context['org_key'];
		$state_code = $org_context['state_code'];

		// Build CSV. Every cell is quoted and run through a guard that
		// neutralizes spreadsheet formula injection — a text value beginning
		// with = + - @ tab or CR is prefixed with an apostrophe — so the
		// generated file is safe to open in Excel / Sheets. The dashboard
		// reads it back with fgetcsv, which handles the quoting correctly.
		$csv_cell = static function ( $value ) {
			$value = (string) $value;
			if ( $value === '' ) {
				return '""';
			}
			// Genuine numbers (incl. negative / decimal) pass through as-is.
			if ( ! is_numeric( $value ) ) {
				$first = $value[0];
				if ( $first === '=' || $first === '+' || $first === '-'
					|| $first === '@' || $first === "\t" || $first === "\r" ) {
					$value = "'" . $value;
				}
			}
			return '"' . str_replace( '"', '""', $value ) . '"';
		};

		if ( in_array( $template_type, array( 'elements_checklist', 'antibiotic_stewardship', 'edtc_checklist' ), true ) ) {
			$date_reported = wp_date( 'm/d/Y' );
			$raw_rows = array();
			$component_headers = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['components'] ) || ! is_array( $row['components'] ) ) {
					continue;
				}
				foreach ( $row['components'] as $component => $value ) {
					$component = sanitize_text_field( (string) $component );
					if ( $component !== '' && ! in_array( $component, $component_headers, true ) ) {
						$component_headers[] = $component;
					}
				}
			}
			$is_antibiotic_stewardship = ( $template_type === 'antibiotic_stewardship' );
			$is_edtc_checklist = ( $template_type === 'edtc_checklist' );
			$component_columns = array();
			foreach ( $component_headers as $component ) {
				if ( $is_antibiotic_stewardship ) {
					$component_columns[] = $component . ' Met';
				} elseif ( $is_edtc_checklist ) {
					$component_columns[] = $component . ' Num';
					$component_columns[] = $component . ' Denom';
					$component_columns[] = $component . ' Rate';
				} else {
					$component_columns[] = $component . ' Criteria Met';
				}
			}
			$headers = $is_antibiotic_stewardship
				? array_merge(
					array( 'Metric', 'Year', 'Core Elements Met Count', 'Core Elements Count', 'Rate', 'Improved From Previous Year' ),
					$component_columns
				)
				: ( $is_edtc_checklist
				? array_merge(
					array( 'Metric', 'Year', 'Quarter', 'Numerator', 'Denominator', 'Rate', 'Credit for Measure' ),
					$component_columns
				)
				: array_merge(
					array( 'Metric', 'Year', 'Criteria Met Count', 'Criteria Count', 'Rate', 'Credit for Measure' ),
					$component_columns
				) );
			$csv = implode( ',', array_map( $csv_cell, $headers ) ) . "\n";
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$components = ( ! empty( $row['components'] ) && is_array( $row['components'] ) ) ? $row['components'] : array();
				$reported_rate = sanitize_text_field( (string) ( $row['rate'] ?? '' ) );
				$improved_from_previous_year = sanitize_text_field( (string) ( $row['credit'] ?? '' ) );
				if ( $is_antibiotic_stewardship ) {
					$previous_rate = null;
					$current_year = isset( $row['year'] ) ? (int) $row['year'] : 0;
					if ( $current_year > 0 ) {
						$previous_year = (string) ( $current_year - 1 );
						$option_key_for_prior = 'dm_org_folder_files_' . $org_key;
						$folder_files_for_prior = get_option( $option_key_for_prior, array() );
						if ( is_array( $folder_files_for_prior ) && ! empty( $folder_files_for_prior[ $folder_id ] ) && is_array( $folder_files_for_prior[ $folder_id ] ) ) {
							$upload_dir_for_prior = wp_upload_dir();
							$dm_base_for_prior = $upload_dir_for_prior['basedir'] . '/qualinav-dm/' . $org_key;
							foreach ( $folder_files_for_prior[ $folder_id ] as $existing_record ) {
								if ( ! is_array( $existing_record ) ) { continue; }
								if ( (string) ( $existing_record['measure'] ?? '' ) !== $measure ) { continue; }
								if ( (string) ( $existing_record['assessment_year'] ?? '' ) !== $previous_year ) { continue; }
								$prior_path = $dm_base_for_prior . '/' . (string) ( $existing_record['name'] ?? '' );
								if ( is_readable( $prior_path ) && ( $handle = fopen( $prior_path, 'r' ) ) ) {
									$prior_headers = fgetcsv( $handle );
									$prior_values = fgetcsv( $handle );
									fclose( $handle );
									if ( is_array( $prior_headers ) && is_array( $prior_values ) ) {
										$rate_index = array_search( 'Rate', $prior_headers, true );
										if ( $rate_index !== false && isset( $prior_values[ $rate_index ] ) ) {
											$rate_value = trim( (string) $prior_values[ $rate_index ] );
											$rate_number = (float) str_replace( '%', '', $rate_value );
											if ( $rate_value !== '' ) {
												$previous_rate = $rate_number;
												break;
											}
										}
									}
								}
							}
						}
					}
					$current_rate_number = (float) str_replace( '%', '', $reported_rate );
					if ( $previous_rate === null ) {
						$improved_from_previous_year = 'No prior year';
					} elseif ( $current_rate_number > $previous_rate ) {
						$improved_from_previous_year = 'Yes';
					} elseif ( $current_rate_number === $previous_rate ) {
						$improved_from_previous_year = 'No change';
					} else {
						$improved_from_previous_year = 'No';
					}
				}
				$line = array(
					$measure,
					sanitize_text_field( (string) ( $row['year'] ?? '' ) ),
				);
				if ( $is_edtc_checklist ) {
					$line[] = sanitize_text_field( (string) ( $row['month'] ?? '' ) );
				}
				$line = array_merge( $line, array(
					sanitize_text_field( (string) ( $row['elements_met_count'] ?? '0' ) ),
					sanitize_text_field( (string) ( $row['elements_selected_count'] ?? '0' ) ),
					$reported_rate,
					$improved_from_previous_year,
				) );
				$summary_raw_row = array(
					'metric'        => $measure,
					'year'          => sanitize_text_field( (string) ( $row['year'] ?? '' ) ),
					'date_reported' => $date_reported,
					'period'        => $is_edtc_checklist ? sanitize_text_field( (string) ( $row['month'] ?? '' ) ) : '',
					'num'           => sanitize_text_field( (string) ( $row['elements_met_count'] ?? '0' ) ),
					'den'           => sanitize_text_field( (string) ( $row['elements_selected_count'] ?? '0' ) ),
					'rate'          => $reported_rate,
				);
				if ( $is_edtc_checklist ) {
					$raw_rows[] = $summary_raw_row;
				}
				foreach ( $component_headers as $component ) {
					$component_data = $components[ $component ] ?? array();
					if ( $is_edtc_checklist && is_array( $component_data ) ) {
						$raw_rows[] = array(
							'metric'           => $measure . ' — ' . $component,
							'year'             => sanitize_text_field( (string) ( $row['year'] ?? '' ) ),
							'date_reported'    => $date_reported,
							'period'           => sanitize_text_field( (string) ( $row['month'] ?? '' ) ),
							'num'              => sanitize_text_field( (string) ( $component_data['num'] ?? '' ) ),
							'den'              => sanitize_text_field( (string) ( $component_data['den'] ?? '' ) ),
							'rate'             => sanitize_text_field( (string) ( $component_data['rate'] ?? '' ) ),
							'edtc_series_key'  => 'component:' . sanitize_title( $component ),
							'edtc_series_label'=> sanitize_text_field( $component ),
						);
						$line[] = sanitize_text_field( (string) ( $component_data['num'] ?? '' ) );
						$line[] = sanitize_text_field( (string) ( $component_data['den'] ?? '' ) );
						$line[] = sanitize_text_field( (string) ( $component_data['rate'] ?? '' ) );
					} elseif ( is_array( $component_data ) ) {
						$component_met = sanitize_text_field( (string) ( $component_data['met'] ?? '' ) );
						$summary_raw_row[ strtolower( $component . ( $is_antibiotic_stewardship ? ' Met' : ' Criteria Met' ) ) ] = $component_met;
						$line[] = $component_met;
					} else {
						// Backward-compatible fallback for older checklist payloads.
						$component_met = sanitize_text_field( (string) $component_data );
						$summary_raw_row[ strtolower( $component . ( $is_antibiotic_stewardship ? ' Met' : ' Criteria Met' ) ) ] = $component_met;
						$line[] = $component_met;
					}
				}
				if ( ! $is_edtc_checklist ) {
					$raw_rows[] = $summary_raw_row;
				}
				if ( $is_edtc_checklist && ! empty( $raw_rows ) ) {
					$last_index = count( $raw_rows ) - ( count( $component_headers ) + 1 );
					if ( isset( $raw_rows[ $last_index ] ) && is_array( $raw_rows[ $last_index ] ) ) {
						$raw_rows[ $last_index ]['edtc_series_key']   = 'composite';
						$raw_rows[ $last_index ]['edtc_series_label'] = 'Composite Score';
					}
				}
				$csv .= implode( ',', array_map( $csv_cell, $line ) ) . "\n";
			}
		} else {
			$date_added = wp_date( 'm/d/Y' );
			$raw_rows = array();
			$csv = in_array( $template_type, array( 'annual_rate', 'annual_numden_rate' ), true )
				? ( $template_type === 'annual_numden_rate' ? "Metric,Year,Num,Denom,Rate\n" : "Metric,Year,Vaccinated HCP,Total Eligible HCP,Rate\n" )
				: ( $template_type === 'quarter_median'
					? "Metric,Year,Quarter,Median Minutes\n"
					: ( in_array( $template_type, array( 'period_rate', 'quarter_rate' ), true )
					? ( $template_type === 'quarter_rate' ? "Metric,Year,Quarter,Num,Denom,Rate\n" : "Metric,Year,Month,Num,Denom,Rate\n" )
					: "Metric,Year,Month,Num,Denom,Rate\n" ) );
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				// Manual entry sends discrete month/year fields; older payloads
				// (and any cached clients) may still send a combined "time" string.
				if ( isset( $row['month'] ) || isset( $row['year'] ) ) {
					$month = trim( (string) ( $row['month'] ?? '' ) );
					$year  = trim( (string) ( $row['year'] ?? '' ) );
				} else {
					$parts = explode( ' ', trim( (string) ( $row['time'] ?? '' ) ) );
					$month = $parts[0] ?? '';
					$year  = $parts[1] ?? '';
				}
				$num = (float) ( $row['num'] ?? 0 );
				$den = (float) ( $row['den'] ?? 0 );
				$rate = $den > 0 ? round( ( $num / $den ) * 100, 1 ) . '%' : '';
				if ( in_array( $template_type, array( 'annual_rate', 'annual_numden_rate' ), true ) ) {
					$line = array( $measure, $year, $row['num'] ?? 0, $row['den'] ?? 0, $rate );
					$raw_rows[] = array(
						'metric'        => $measure,
						'year'          => $year,
						'date_reported' => $date_added,
						'num'           => sanitize_text_field( (string) ( $row['num'] ?? 0 ) ),
						'den'           => sanitize_text_field( (string) ( $row['den'] ?? 0 ) ),
						'rate'          => $rate,
					);
				} elseif ( $template_type === 'quarter_median' ) {
					$line = array( $measure, $year, $month, $row['median'] ?? '' );
					$raw_rows[] = array(
						'metric'         => $measure,
						'year'           => $year,
						'date_reported'  => $date_added,
						'period'         => $month,
						'median_minutes' => sanitize_text_field( (string) ( $row['median'] ?? '' ) ),
					);
				} elseif ( in_array( $template_type, array( 'period_rate', 'quarter_rate' ), true ) ) {
					$line = array( $measure, $year, $month, $row['num'] ?? 0, $row['den'] ?? 0, $rate );
					$raw_rows[] = array(
						'metric'        => $measure,
						'year'          => $year,
						'date_reported' => $date_added,
						'period'        => $month,
						'num'           => sanitize_text_field( (string) ( $row['num'] ?? 0 ) ),
						'den'           => sanitize_text_field( (string) ( $row['den'] ?? 0 ) ),
						'rate'          => $rate,
					);
				} else {
					$line = array( $measure, $year, $month, $row['num'] ?? 0, $row['den'] ?? 0, $rate );
					$raw_rows[] = array(
						'metric' => $measure,
						'year'   => $year,
						'period' => $month,
						'num'    => sanitize_text_field( (string) ( $row['num'] ?? 0 ) ),
						'den'    => sanitize_text_field( (string) ( $row['den'] ?? 0 ) ),
						'rate'   => $rate,
					);
				}
				$csv .= implode( ',', array_map( $csv_cell, $line ) ) . "\n";
			}
		}

		$upload_dir = wp_upload_dir();
		$dm_root    = $upload_dir['basedir'] . '/qualinav-dm';
		$dm_base    = $dm_root . '/' . $org_key;
		if ( ! file_exists( $dm_base ) ) {
			if ( ! wp_mkdir_p( $dm_base ) ) {
				wp_send_json_error( 'Could not create upload directory: ' . $dm_base );
			}
		}
		if ( ! is_writable( $dm_base ) ) {
			wp_send_json_error( 'Upload directory is not writable' );
		}
		// Block script execution / directory listing across the upload tree.
		qualinav_data_hub_dm_harden_upload_dir( $dm_root );

		// Content-hash dedup against existing files in the same folder. Files
		// uploaded before content_hash tracking existed don't have one yet —
		// lazy-backfill by reading them from disk during the check so legacy
		// duplicates are caught too.
		$content_hash = md5( $csv );
		$option_key   = 'dm_org_folder_files_' . $org_key;
		$folder_files = get_option( $option_key, array() );
		if ( ! is_array( $folder_files ) ) { $folder_files = array(); }
		if ( ! isset( $folder_files[ $folder_id ] ) || ! is_array( $folder_files[ $folder_id ] ) ) {
			$folder_files[ $folder_id ] = array();
		}

		$is_annual_template = in_array( $template_type, array( 'elements_checklist', 'annual_rate', 'annual_numden_rate', 'antibiotic_stewardship', 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true );
		$annual_assessment_year = ( $is_annual_template && ! empty( $rows[0]['year'] ) )
			? sanitize_text_field( (string) $rows[0]['year'] )
			: '';
		$assessment_years = array();
		if ( $is_annual_template ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['year'] ) ) { continue; }
				$row_year = sanitize_text_field( (string) $row['year'] );
				if ( preg_match( '/^[12][0-9]{3}$/', $row_year ) && ! in_array( $row_year, $assessment_years, true ) ) {
					$assessment_years[] = $row_year;
				}
			}
			sort( $assessment_years, SORT_STRING );
		}
		$assessment_year_range = '';
		$assessment_year_label = '';
		$assessment_year_filename = '';
		if ( count( $assessment_years ) > 1 ) {
			$year_numbers = array_map( 'intval', $assessment_years );
			$is_contiguous = true;
			for ( $year_idx = 1; $year_idx < count( $year_numbers ); $year_idx++ ) {
				if ( $year_numbers[ $year_idx ] !== $year_numbers[ $year_idx - 1 ] + 1 ) {
					$is_contiguous = false;
					break;
				}
			}
			$assessment_year_range = reset( $assessment_years ) . '-' . end( $assessment_years );
			$assessment_year_label = $is_contiguous ? $assessment_year_range : implode( ', ', $assessment_years );
			$assessment_year_filename = $is_contiguous ? $assessment_year_range : implode( '-', $assessment_years );
		} elseif ( count( $assessment_years ) === 1 ) {
			$assessment_year_label = $assessment_years[0];
			$assessment_year_filename = $assessment_years[0];
		}
		$period_assessment_month = ( in_array( $template_type, array( 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true ) && ! empty( $rows[0]['month'] ) )
			? sanitize_text_field( (string) $rows[0]['month'] )
			: '';
		$assessment_periods = array();
		if ( in_array( $template_type, array( 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['year'] ) || empty( $row['month'] ) ) { continue; }
				$row_period = array(
					'year'  => sanitize_text_field( (string) $row['year'] ),
					'month' => sanitize_text_field( (string) $row['month'] ),
				);
				$period_key = $row_period['year'] . '|' . $row_period['month'];
				if ( ! isset( $assessment_periods[ $period_key ] ) ) {
					$assessment_periods[ $period_key ] = $row_period;
				}
			}
			$assessment_periods = array_values( $assessment_periods );
			usort( $assessment_periods, function( $a, $b ) {
				$month_order = array(
					'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
					'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
					'Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4,
				);
				$a_year = (int) ( $a['year'] ?? 0 );
				$b_year = (int) ( $b['year'] ?? 0 );
				if ( $a_year !== $b_year ) {
					return $a_year <=> $b_year;
				}
				$a_month = $month_order[ (string) ( $a['month'] ?? '' ) ] ?? 99;
				$b_month = $month_order[ (string) ( $b['month'] ?? '' ) ] ?? 99;
				return $a_month <=> $b_month;
			} );
		}
		$assessment_period_label = '';
		$assessment_period_filename = '';
		if ( count( $assessment_periods ) > 1 ) {
			$period_labels = array_map( function( $period ) {
				return trim( (string) ( $period['month'] ?? '' ) . ' ' . (string) ( $period['year'] ?? '' ) );
			}, $assessment_periods );
			$assessment_period_label = count( $period_labels ) > 4
				? reset( $period_labels ) . ' - ' . end( $period_labels )
				: implode( ', ', $period_labels );
			$assessment_period_filename = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $assessment_period_label ) );
			$assessment_period_filename = trim( $assessment_period_filename, '-' );
		}
		$replaced_file_name = '';
		$replaced_file_names = array();
		if ( $overwrite_file_name !== '' || ! empty( $overwrite_file_names ) ) {
			foreach ( $folder_files[ $folder_id ] as $idx => $existing ) {
				if ( ! is_array( $existing ) ) { continue; }
				if ( (string) ( $existing['measure'] ?? '' ) !== $measure ) { continue; }
				$is_named_overwrite = ( (string) ( $existing['name'] ?? '' ) === $overwrite_file_name );
				if ( ! $is_named_overwrite && ! empty( $overwrite_file_names ) ) {
					$is_named_overwrite = in_array( (string) ( $existing['name'] ?? '' ), $overwrite_file_names, true );
				}
				$is_same_annual_year = $is_annual_template
					&& $annual_assessment_year !== ''
					&& (string) ( $existing['assessment_year'] ?? '' ) === $annual_assessment_year;
				if ( in_array( $template_type, array( 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true ) ) {
					$is_same_annual_year = $is_same_annual_year
						&& $period_assessment_month !== ''
						&& (string) ( $existing['assessment_month'] ?? '' ) === $period_assessment_month;
				}
				if ( ! $is_named_overwrite && ! $is_same_annual_year ) { continue; }

				$old_name = (string) ( $existing['name'] ?? '' );
				$old_path = $dm_base . '/' . $old_name;
				if ( is_file( $old_path ) ) {
					@unlink( $old_path );
				}
				$old_staging_path = (string) ( $existing['local_staging_path'] ?? '' );
				if ( $old_staging_path !== '' && is_file( $old_staging_path ) ) {
					@unlink( $old_staging_path );
				}
				if ( ! empty( $existing['drive_file_id'] ) && class_exists( 'Qualinav_Data_Hub_Drive' ) ) {
					Qualinav_Data_Hub_Drive::trash_file( (string) $existing['drive_file_id'] );
				}
				unset( $folder_files[ $folder_id ][ $idx ] );
				if ( $replaced_file_name === '' && $old_name !== '' ) {
					$replaced_file_name = $old_name;
				}
				if ( $old_name !== '' ) {
					$replaced_file_names[] = $old_name;
				}
			}
			$folder_files[ $folder_id ] = array_values( $folder_files[ $folder_id ] );
		}

		$hashes_backfilled = false;
		foreach ( $folder_files[ $folder_id ] as $idx => $existing ) {
			if ( ! is_array( $existing ) ) { continue; }
			$existing_hash = (string) ( $existing['content_hash'] ?? '' );
			if ( $existing_hash === '' ) {
				$existing_path = $dm_base . '/' . (string) ( $existing['name'] ?? '' );
				if ( is_readable( $existing_path ) ) {
					$existing_hash = (string) md5_file( $existing_path );
					$folder_files[ $folder_id ][ $idx ]['content_hash'] = $existing_hash;
					$hashes_backfilled = true;
				}
			}
			if ( $existing_hash !== '' && $existing_hash === $content_hash ) {
				if ( $hashes_backfilled ) {
					update_option( $option_key, $folder_files, false );
				}
				wp_send_json_error( sprintf(
					'This data is identical to "%s" already saved. Skipped to avoid duplicates.',
					(string) ( $existing['name'] ?? 'an existing file' )
				) );
			}
		}

		// Annual submissions get a stable org/state/measure/year filename,
		// whether they came from manual entry or a completed uploaded template.
		// Other uploads preserve the source filename, normalized to CSV because
		// this handler writes CSV output.
		$has_allowed_ext   = $original_filename !== '' && preg_match( '/\.(csv|xls|xlsx)$/i', $original_filename );
		$existing_names = array();
		foreach ( $folder_files[ $folder_id ] as $existing ) {
			if ( is_array( $existing ) && ! empty( $existing['name'] ) ) {
				$existing_names[] = (string) $existing['name'];
			}
		}

		if ( $is_annual_template ) {
			$assessment_year = $annual_assessment_year !== '' ? $annual_assessment_year : gmdate( 'Y' );
			$assessment_year_filename = $assessment_year_filename !== '' ? $assessment_year_filename : $assessment_year;
			$org_filename_slug = preg_replace( '/(?:-[a-z]+)?-[a-z]{2}$/i', '', $org_key );
			if ( $org_filename_slug === '' ) {
				$org_filename_slug = $org_key;
			}
			$filename_parts = array_filter( array(
				$org_filename_slug,
				$state_code,
				qualinav_data_hub_measure_filename_slug( $measure ),
				in_array( $template_type, array( 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true ) ? ( $assessment_period_filename !== '' ? $assessment_period_filename : $period_assessment_month ) : '',
				$assessment_year_filename,
			) );
			$filename = sanitize_file_name( implode( '_', $filename_parts ) . '.csv' );
			if ( in_array( $filename, $existing_names, true ) ) {
				$base = preg_replace( '/\.csv$/i', '', $filename );
				$filename = $base . '_' . time() . '.csv';
			}
		} elseif ( $has_allowed_ext ) {
			// Force .csv since we always write CSV here, even if the source was XLS/XLSX.
			$base     = preg_replace( '/\.(csv|xls|xlsx)$/i', '', $original_filename );
			$filename = $base . '.csv';
			// Avoid collisions with an existing file of the same name in this folder.
			if ( in_array( $filename, $existing_names, true ) ) {
				$filename = $base . '_' . time() . '.csv';
			}
		} else {
			$filename = sanitize_file_name( $measure ) . '_' . time() . '.csv';
		}

		$drive_mirroring_enabled = class_exists( 'Qualinav_Data_Hub_Drive' ) && Qualinav_Data_Hub_Drive::is_enabled();
		if ( $drive_mirroring_enabled ) {
			$staging_base = $upload_dir['basedir'] . '/qualinav-dm-staging/' . $org_key;
			if ( ! file_exists( $staging_base ) && ! wp_mkdir_p( $staging_base ) ) {
				wp_send_json_error( 'Could not create temporary upload directory: ' . $staging_base );
			}
			qualinav_data_hub_dm_harden_upload_dir( dirname( $staging_base ) );
			$filepath = trailingslashit( $staging_base ) . $filename;
			$fileurl  = '';
		} else {
			$filepath = $dm_base . '/' . $filename;
			$fileurl  = $upload_dir['baseurl'] . '/qualinav-dm/' . $org_key . '/' . $filename;
		}

		if ( file_put_contents( $filepath, $csv ) === false ) {
			wp_send_json_error( 'Failed to write CSV file' );
		}

		$file_record = array(
			'name'         => $filename,
			'url'          => $fileurl,
			'type'         => 'text/csv',
			'size_kb'      => round( strlen( $csv ) / 1024, 1 ),
			'uploaded_at'  => wp_date( 'M j, Y g:i A' ),
			'content_hash' => $content_hash,
			'source'       => $is_manual_source ? 'manual' : 'upload',
			'template_type' => $template_type,
			'raw_rows'      => isset( $raw_rows ) && is_array( $raw_rows ) ? $raw_rows : array(),
			'assessment_year' => ( in_array( $template_type, array( 'elements_checklist', 'annual_rate', 'annual_numden_rate', 'antibiotic_stewardship', 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true ) && ! empty( $rows[0]['year'] ) )
				? sanitize_text_field( (string) $rows[0]['year'] )
				: '',
			'assessment_years' => $assessment_years,
			'assessment_year_range' => $assessment_year_range,
			'assessment_year_label' => $assessment_year_label,
			'assessment_periods' => $assessment_periods,
			'assessment_period_label' => $assessment_period_label,
			'is_bulk_upload' => count( $assessment_years ) > 1 || count( $assessment_periods ) > 1,
			'assessment_month' => ( in_array( $template_type, array( 'edtc_checklist', 'period_rate', 'quarter_rate', 'quarter_median' ), true ) && ! empty( $rows[0]['month'] ) )
				? sanitize_text_field( (string) $rows[0]['month'] )
				: '',
			// Tag the measure on the record so the Data Management UI can
			// attribute this file to its measure unambiguously, instead of
			// reverse-engineering it from the filename prefix (which loses
			// info whenever sanitize_file_name() collapses chars).
			'measure'      => $measure,
		);

		if ( $drive_mirroring_enabled ) {
			$file_record['drive_sync_status'] = 'pending';
			$file_record['local_staging_path'] = $filepath;
			$file_record['drive_sync_retries'] = 0;
			$file_record['drive_sync_started_at'] = time();
		} elseif ( class_exists( 'Qualinav_Data_Hub_Drive' ) ) {
			$file_record['drive_sync_status'] = 'disabled';
		}

		$folder_files[ $folder_id ][] = $file_record;

		update_option( $option_key, $folder_files, false );

		qualinav_data_hub_dm_sync_mbqip_submission_rows( array(
			'user_id'       => $user_id,
			'org_context'   => $org_context,
			'folder_id'     => $folder_id,
			'measure'       => $measure,
			'template_type' => $template_type,
			'raw_rows'      => isset( $raw_rows ) && is_array( $raw_rows ) ? $raw_rows : array(),
			'source_type'   => $is_manual_source ? 'manual' : 'upload',
			'file_name'     => $filename,
			'file_url'      => $fileurl,
		) );

		if ( $drive_mirroring_enabled ) {
			$drive_sync_job_id = qualinav_data_hub_dm_enqueue_drive_sync( array(
				'user_id'    => $user_id,
				'folder_id'  => $folder_id,
				'option_key' => $option_key,
				'filename'   => $filename,
				'local_path' => $filepath,
				'mime'       => 'text/csv',
				'measure'    => $measure,
			) );
			$file_record['drive_sync_job_id'] = $drive_sync_job_id;
			qualinav_data_hub_dm_update_drive_record( array(
				'option_key' => $option_key,
				'folder_id'  => $folder_id,
				'filename'   => $filename,
			), array(
				'drive_sync_job_id' => $drive_sync_job_id,
			), false );
		}

		// The QAPI dashboard class hooks `updated_option` on
		// `dm_org_folder_files_*` and resyncs caches automatically — its
		// internal sync method is private, so don't call it directly here.

		$response_file_record = $file_record;
		unset( $response_file_record['local_staging_path'] );

		wp_send_json_success( array(
			'message' => $drive_mirroring_enabled ? 'Data saved' : 'Data saved and synced',
			'file'    => $response_file_record,
			'replaced_file_name' => $replaced_file_name,
			'replaced_file_names' => $replaced_file_names,
		) );
	}

	add_action( 'wp_ajax_dm_save_data', 'qualinav_data_hub_dm_save_data_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_dm_delete_file_handler' ) ) {

	/**
	 * Archives one file in the per-org folder option and removes its active
	 * dashboard rows. The CSV stays on disk so it can be viewed from Archive.
	 *
	 * POST: folder_id, file_name (matched against dm_org_folder_files_{org_key}).
	 * Scoped to the current user's org_key — users can't delete other users'
	 * files (assuming distinct org_key per user, which is the per-user fallback).
	 */
	function qualinav_data_hub_dm_delete_file_handler() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}
		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		$folder_id = sanitize_key( $_POST['folder_id'] ?? '' );
		$file_name = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) );
		if ( $folder_id === '' || $file_name === '' ) {
			wp_send_json_error( 'Invalid input' );
		}

		$user_id  = get_current_user_id();
		$org_context = qualinav_data_hub_get_org_context( $user_id );
		$org_key = (string) ( $org_context['org_key'] ?? '' );
		if ( $org_key === '' ) { $org_key = 'user-' . $user_id; }

		$option_key   = 'dm_org_folder_files_' . $org_key;
		$folder_files = get_option( $option_key, array() );
		if ( ! is_array( $folder_files ) ) {
			wp_send_json_error( 'File not found' );
		}

		$removed = null;
		$matched_folder_id = '';
		$archived_at = wp_date( 'M j, Y g:i A' );
		$folder_ids_to_search = array_values( array_unique( array_filter( array_merge(
			array( $folder_id ),
			array_keys( $folder_files )
		) ) ) );
		foreach ( $folder_ids_to_search as $candidate_folder_id ) {
			if ( empty( $folder_files[ $candidate_folder_id ] ) || ! is_array( $folder_files[ $candidate_folder_id ] ) ) {
				continue;
			}
			foreach ( $folder_files[ $candidate_folder_id ] as $idx => $existing ) {
				if ( ! is_array( $existing ) ) { continue; }
				if ( (string) ( $existing['name'] ?? '' ) === $file_name ) {
					$existing['archived'] = true;
					$existing['archived_at'] = $archived_at;
					$removed = $existing;
					$matched_folder_id = (string) $candidate_folder_id;
					$folder_files[ $candidate_folder_id ][ $idx ] = $existing;
					break 2;
				}
			}
		}
		if ( $removed === null ) {
			wp_send_json_error( 'File not found' );
		}
		$removed_staging_path = (string) ( $removed['local_staging_path'] ?? '' );
		if ( $removed_staging_path !== '' && is_file( $removed_staging_path ) ) {
			@unlink( $removed_staging_path );
		}

		update_option( $option_key, $folder_files, false );
		$metric_folder_id = $matched_folder_id !== '' ? $matched_folder_id : $folder_id;

		// Wipe the rows this file produced in wp_qapi_metric_data so the
		// dashboard stops charting archived data. Rows are stored scoped by the
		// real org_id (from wp_users.organization_id) — not just user_id — so
		// the previous user_id-only purge missed them and the metric persisted
		// through the post-save resync. Scope by source_name + folder_key
		// (exactly the rows this file produced in this category) limited to
		// this org / this user / legacy null rows, so another org's file with
		// the same name is never affected.
		global $wpdb;
		$metric_table = $wpdb->prefix . 'qapi_metric_data';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $metric_table ) ) === $metric_table ) {
			if ( current_user_can( 'manage_options' ) ) {
				// Admins manage every org's data. The rows a given file produced
				// are uniquely keyed by source_name + folder_key, so for an admin
				// delete remove them outright — this is what guarantees the
				// metric disappears even when the rows were stored under another
				// user's id or an org_id that differs from the admin's (the exact
				// case where the scoped purge below silently missed them).
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$metric_table} WHERE source_name = %s AND folder_key = %s",
					$file_name,
					$metric_folder_id
				) );
			} else {
				$org_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT organization_id FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
					$user_id
				) );
				$org_id = ( $org_id !== null && (int) $org_id > 0 ) ? (int) $org_id : 0;

				if ( $org_id > 0 ) {
					$wpdb->query( $wpdb->prepare(
						"DELETE FROM {$metric_table}
						 WHERE source_name = %s AND folder_key = %s
						   AND ( org_id = %d OR user_id = %d OR ( org_id IS NULL AND user_id IS NULL ) )",
						$file_name,
						$metric_folder_id,
						$org_id,
						$user_id
					) );
				} else {
					$wpdb->query( $wpdb->prepare(
						"DELETE FROM {$metric_table}
						 WHERE source_name = %s AND folder_key = %s
						   AND ( user_id = %d OR ( org_id IS NULL AND user_id IS NULL ) )",
						$file_name,
						$metric_folder_id,
						$user_id
					) );
				}
			}
		}

		// Authoritatively reconcile the metric cache against the active
		// uploaded files. This removes any phantom rows the scoped purge above
		// couldn't reach because they were stored under a different org/user.
		if ( class_exists( 'Quainav_Qapi_Dasboard' ) && method_exists( 'Quainav_Qapi_Dasboard', 'instance' ) ) {
			$qd = Quainav_Qapi_Dasboard::instance();
			if ( $qd && method_exists( $qd, 'reconcile_metric_data_orphans' ) ) {
				$qd->reconcile_metric_data_orphans();
			}
		}

		// Bust every `qaqd_live_metrics_*` transient — we no longer rely on
		// org_key as the DB scope, so just wipe all the dashboard caches
		// directly and let them regenerate on next read.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_qaqd\_live\_metrics\_%' OR option_name LIKE '\_transient\_timeout\_qaqd\_live\_metrics\_%'"
		);

		wp_send_json_success( array(
			'message' => 'File archived',
			'name'    => $file_name,
			'archived_at' => $archived_at,
		) );
	}

	add_action( 'wp_ajax_dm_delete_file', 'qualinav_data_hub_dm_delete_file_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_dm_restore_file_handler' ) ) {
	function qualinav_data_hub_dm_restore_file_handler() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}
		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		$folder_id = sanitize_key( $_POST['folder_id'] ?? '' );
		$file_name = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) );
		if ( $folder_id === '' || $file_name === '' ) {
			wp_send_json_error( 'Invalid input' );
		}

		$user_id = get_current_user_id();
		$org_context = qualinav_data_hub_get_org_context( $user_id );
		$org_key = (string) ( $org_context['org_key'] ?? '' );
		if ( $org_key === '' ) { $org_key = 'user-' . $user_id; }

		$option_key = 'dm_org_folder_files_' . $org_key;
		$folder_files = get_option( $option_key, array() );
		if ( ! is_array( $folder_files ) ) {
			wp_send_json_error( 'File not found' );
		}

		$restored = null;
		$folder_ids_to_search = array_values( array_unique( array_filter( array_merge(
			array( $folder_id ),
			array_keys( $folder_files )
		) ) ) );
		foreach ( $folder_ids_to_search as $candidate_folder_id ) {
			if ( empty( $folder_files[ $candidate_folder_id ] ) || ! is_array( $folder_files[ $candidate_folder_id ] ) ) {
				continue;
			}
			foreach ( $folder_files[ $candidate_folder_id ] as $idx => $existing ) {
				if ( ! is_array( $existing ) ) { continue; }
				if ( (string) ( $existing['name'] ?? '' ) === $file_name ) {
					unset( $existing['archived'], $existing['archived_at'] );
					$restored = $existing;
					$folder_files[ $candidate_folder_id ][ $idx ] = $existing;
					break 2;
				}
			}
		}
		if ( $restored === null ) {
			wp_send_json_error( 'File not found' );
		}

		update_option( $option_key, $folder_files, false );

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_qaqd\_live\_metrics\_%' OR option_name LIKE '\_transient\_timeout\_qaqd\_live\_metrics\_%'"
		);

		wp_send_json_success( array(
			'message' => 'File restored',
			'name'    => $file_name,
		) );
	}

	add_action( 'wp_ajax_dm_restore_file', 'qualinav_data_hub_dm_restore_file_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_xlsx_col_name' ) ) {
	function qualinav_data_hub_xlsx_col_name( $index ) {
		$index = (int) $index;
		$name  = '';
		while ( $index > 0 ) {
			$index--;
			$name  = chr( 65 + ( $index % 26 ) ) . $name;
			$index = (int) floor( $index / 26 );
		}
		return $name;
	}
}

if ( ! function_exists( 'qualinav_data_hub_xlsx_xml' ) ) {
	function qualinav_data_hub_xlsx_xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}
}

if ( ! function_exists( 'qualinav_data_hub_xlsx_cell' ) ) {
	function qualinav_data_hub_xlsx_cell( $row_index, $col_index, $value, $style = null ) {
		$ref = qualinav_data_hub_xlsx_col_name( $col_index ) . (int) $row_index;
		$style_attr = $style === null ? '' : ' s="' . (int) $style . '"';
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && preg_match( '/^\d+(?:\.\d+)?$/', $value ) ) ) {
			return '<c r="' . esc_attr( $ref ) . '"' . $style_attr . '><v>' . qualinav_data_hub_xlsx_xml( $value ) . '</v></c>';
		}
		return '<c r="' . esc_attr( $ref ) . '"' . $style_attr . ' t="inlineStr"><is><t>' . qualinav_data_hub_xlsx_xml( $value ) . '</t></is></c>';
	}
}

if ( ! function_exists( 'qualinav_data_hub_xlsx_formula_cell' ) ) {
	function qualinav_data_hub_xlsx_formula_cell( $row_index, $col_index, $formula, $style = null ) {
		$ref = qualinav_data_hub_xlsx_col_name( $col_index ) . (int) $row_index;
		$style_attr = $style === null ? '' : ' s="' . (int) $style . '"';
		return '<c r="' . esc_attr( $ref ) . '"' . $style_attr . '><f>' . qualinav_data_hub_xlsx_xml( $formula ) . '</f><v></v></c>';
	}
}

if ( ! function_exists( 'qualinav_data_hub_download_cah_template_handler' ) ) {
	function qualinav_data_hub_download_cah_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$template_year = isset( $_GET['year'] ) ? (int) $_GET['year'] : $current_year;
		if ( $template_year < $start_year || $template_year > $current_year ) {
			$template_year = $current_year;
		}

		$components = array(
			'Leadership Responsibility & Accountability',
			'Quality Embedded within the Organization’s Strategic Plan',
			'Workforce Engagement & Ownership',
			'Culture of Continuous Improvement through Behavior',
			'Culture of Continuous Improvement through Systems',
			'Engagement of Patients, Partners, and Community',
			'Collecting Meaningful and Accurate Data',
			'Using Data to Improve Quality',
		);

		$rows = array(
			array( 'Measure Name', 'CAH Quality Infrastructure Assessment' ),
			array( 'Instructions', 'Use one eight-row block per assessment year. The first block is prefilled with the selected year; change or copy the year as needed. Leave unused blocks blank. Each completed year must include all eight criteria with Criteria Met answered Yes or No.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'CAH Global Measure Component', 'Criteria Met' ),
		);
		$year_validation_refs = array();
		for ( $group_idx = 0; $group_idx < 10; $group_idx++ ) {
			if ( $group_idx > 0 ) {
				$rows[] = array();
			}
			$block_start_row = count( $rows ) + 1;
			$year_validation_refs[] = 'A' . (int) $block_start_row;
			foreach ( $components as $index => $component ) {
				$year_value = 0 === $index
					? ( 0 === $group_idx ? (string) $template_year : '' )
					: array( 'formula' => 'IF(A' . (int) $block_start_row . '="","",A' . (int) $block_start_row . ')' );
				$rows[] = array( $year_value, ( $index + 1 ) . '. ' . $component, '' );
			}
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( in_array( $excel_row, array( 1, 2, 4, 6 ), true ) && $excel_col === 1 ) {
					$style = 1;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 1;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 3 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 2 ) {
					$style = 4;
				}
				if ( is_array( $value ) && isset( $value['formula'] ) ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, (string) $value['formula'], 0 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="78" customHeight="1"';
			}
			if ( $excel_row > 6 && empty( $row ) ) {
				$row_attrs .= ' ht="10" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="64" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="5" width="14" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:C1"/><mergeCell ref="B2:C2"/></mergeCells>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="2">';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="' . esc_attr( implode( ' ', $year_validation_refs ) ) . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="C7:C' . (int) $last_data_row . '"><formula1>&quot;Yes,No&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$tmp = tempnam( get_temp_dir(), 'cah_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="5">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>CAH Quality Infrastructure Assessment Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Global template" sheetId="1" r:id="rId1"/></sheets><calcPr fullCalcOnLoad="1"/></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="cah_quality_infrastructure_assessment_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_cah_template', 'qualinav_data_hub_download_cah_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_hcp_template_handler' ) ) {
	function qualinav_data_hub_download_hcp_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$year         = isset( $_GET['year'] ) ? (int) $_GET['year'] : $current_year;
		if ( $year < $start_year || $year > $current_year ) {
			$year = $current_year;
		}

		$rows = array(
			array( 'Measure Name', 'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination' ),
			array( 'Instructions', 'Enter annual influenza-season results. Fill one row or bulk add multiple years. Numerator is vaccinated healthcare personnel and denominator is total eligible healthcare personnel. Rate is calculated as Numerator divided by Denominator.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Numerator', 'Denominator', 'Rate' ),
		);
		$template_row_count = max( 12, $current_year - $start_year + 1 );
		for ( $row_idx = 0; $row_idx < $template_row_count; $row_idx++ ) {
			$rows[] = array( '', '', '', '' );
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
					$style = 5;
				}
				if ( $excel_row === 1 && $excel_col === 2 ) {
					$style = 5;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 5;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 3 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 4 ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, 'IF(C' . $excel_row . '>0,B' . $excel_row . '/C' . $excel_row . ',"")', 6 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="86" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="58" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="4" width="26" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:D1"/><mergeCell ref="B2:D2"/></mergeCells>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="A7:A' . (int) $last_data_row . '"><formula1>' . $year_formula . '</formula1></dataValidation></dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="7">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'hcp_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>HCP IMM-3 Healthcare Personnel Influenza Vaccination Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="HCP Vaccine" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="hcp_imm_3_healthcare_personnel_influenza_vaccination_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_hcp_template', 'qualinav_data_hub_download_hcp_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_hwr_template_handler' ) ) {
	function qualinav_data_hub_download_hwr_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;

		$rows = array(
			array( 'Measure Name', 'Hybrid Hospital-Wide Readmission (HWR)' ),
			array( 'Instructions', 'Enter one row per reporting month. Leave unused rows blank. Rate is calculated as Numerator divided by Denominator. Lower rate is better.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Month', 'Num', 'Denom', 'Rate' ),
		);
		$template_row_count = 60;
		for ( $row_idx = 0; $row_idx < $template_row_count; $row_idx++ ) {
			$rows[] = array( '', '', '', '', '' );
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
					$style = 5;
				}
				if ( $excel_row === 1 && $excel_col === 2 ) {
					$style = 5;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 5;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 3, 4 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 5 ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, 'IF(D' . $excel_row . '>0,C' . $excel_row . '/D' . $excel_row . ',"")', 6 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="70" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="24" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="4" width="24" customWidth="1"/><col min="5" max="5" width="26" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:E1"/><mergeCell ref="B2:E2"/></mergeCells>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="2">';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="A7:A' . (int) $last_data_row . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="B7:B' . (int) $last_data_row . '"><formula1>&quot;Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="7">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'hwr_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Hybrid Hospital-Wide Readmission Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="HWR" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="hybrid_hospital_wide_readmission_hwr_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_hwr_template', 'qualinav_data_hub_download_hwr_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_op22_template_handler' ) ) {
	function qualinav_data_hub_download_op22_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$rows = array(
			array( 'Measure Name', 'OP-22 — Patient Left Without Being Seen (LWBS) Rate' ),
			array( 'Instructions', 'Enter annual left-without-being-seen results. Fill one row or bulk add multiple years. Rate is calculated as Numerator divided by Denominator. Lower rate is better.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Num', 'Denom', 'Rate' ),
		);
		$template_row_count = max( 12, $current_year - $start_year + 1 );
		for ( $row_idx = 0; $row_idx < $template_row_count; $row_idx++ ) {
			$rows[] = array( '', '', '', '' );
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells     = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style     = null;
				if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
					$style = 5;
				}
				if ( $excel_row === 1 && $excel_col === 2 ) {
					$style = 5;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 5;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 3 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 4 ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, 'IF(C' . $excel_row . '>0,B' . $excel_row . '/C' . $excel_row . ',"")', 6 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="70" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="3" width="18" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="7" width="14" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:D1"/><mergeCell ref="B2:D2"/></mergeCells>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="A7:A' . (int) $last_data_row . '"><formula1>' . $year_formula . '</formula1></dataValidation></dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="7">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'op22_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>OP-22 Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="OP-22" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="op_22_patient_left_without_being_seen_lwbs_rate_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_op22_template', 'qualinav_data_hub_download_op22_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_antibiotic_template_handler' ) ) {
	function qualinav_data_hub_download_antibiotic_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$year         = isset( $_GET['year'] ) ? (int) $_GET['year'] : $current_year;
		if ( $year < $start_year || $year > $current_year ) {
			$year = $current_year;
		}

		$components = array(
			'Leadership',
			'Accountability',
			'Drug Expertise',
			'Action',
			'Tracking',
			'Reporting',
			'Education',
		);

		$rows = array(
			array( 'Measure Name', 'Antibiotic Stewardship' ),
			array( 'Instructions', 'Use one seven-row block per assessment year. The first block is prefilled with the selected year; change or copy the year as needed. Leave unused blocks blank. Each completed year must include all seven Centers for Disease Control and Prevention Core Elements with Criteria Met answered Yes or No.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'CDC 7 Core Elements', 'Criteria Met' ),
		);
		$year_validation_refs = array();
		for ( $group_idx = 0; $group_idx < 10; $group_idx++ ) {
			if ( $group_idx > 0 ) {
				$rows[] = array();
			}
			$block_start_row = count( $rows ) + 1;
			$year_validation_refs[] = 'A' . (int) $block_start_row;
			foreach ( $components as $index => $component ) {
				$year_value = 0 === $index
					? ( 0 === $group_idx ? (string) $year : '' )
					: array( 'formula' => 'IF(A' . (int) $block_start_row . '="","",A' . (int) $block_start_row . ')' );
				$rows[] = array( $year_value, $component, '' );
			}
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
					$style = 5;
				}
				if ( $excel_row === 1 && $excel_col === 2 ) {
					$style = 5;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 5;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 3 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 2 ) {
					$style = 4;
				}
				if ( is_array( $value ) && isset( $value['formula'] ) ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, (string) $value['formula'], 0 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="72" customHeight="1"';
			}
			if ( $excel_row > 6 && empty( $row ) ) {
				$row_attrs .= ' ht="10" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="5" width="14" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="2">';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="' . esc_attr( implode( ' ', $year_validation_refs ) ) . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="C7:C' . (int) $last_data_row . '"><formula1>&quot;Yes,No&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="7">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'antibiotic_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Antibiotic Stewardship Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Antibiotic Stewardship" sheetId="1" r:id="rId1"/></sheets><calcPr fullCalcOnLoad="1"/></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="antibiotic_stewardship_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_antibiotic_template', 'qualinav_data_hub_download_antibiotic_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_safe_use_opioids_template_handler' ) ) {
	function qualinav_data_hub_download_safe_use_opioids_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;

		$rows   = array(
			array( 'Measure Name', 'Safe Use of Opioids eCQM — MBQIP Submission' ),
			array( 'Instructions', 'Enter one row per reporting month. Leave unused rows blank. Rate is calculated as Numerator divided by Denominator. Lower rate is better.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Month', 'Num', 'Denom', 'Rate' ),
		);
		$template_row_count = 60;
		for ( $row_idx = 0; $row_idx < $template_row_count; $row_idx++ ) {
			$rows[] = array( '', '', '', '', '' );
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
					if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
						$style = 5;
					}
					if ( $excel_row === 1 && $excel_col === 2 ) {
						$style = 5;
					}
					if ( $excel_row === 2 && $excel_col === 2 ) {
						$style = 3;
					}
					if ( $excel_row === 6 ) {
						$style = 5;
					}
					if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 3, 4 ), true ) ) {
						$style = 2;
					}
					if ( $excel_row >= 7 && $excel_col === 5 ) {
						$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, 'IF(D' . $excel_row . '>0,C' . $excel_row . '/D' . $excel_row . ',"")', 6 );
						continue;
					}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="54" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
			$sheet_xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="24" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="4" width="24" customWidth="1"/><col min="5" max="5" width="26" customWidth="1"/></cols>';
			$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
			$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:E1"/><mergeCell ref="B2:E2"/></mergeCells>';
			$last_data_row = count( $rows );
			$sheet_xml .= '<dataValidations count="2">';
			$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="A7:A' . (int) $last_data_row . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
			$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="B7:B' . (int) $last_data_row . '"><formula1>&quot;Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="7">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'safe_opioids_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Safe Use of Opioids Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Safe Use of Opioids" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="safe_use_of_opioids_ecqm_mbqip_submission_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_safe_use_opioids_template', 'qualinav_data_hub_download_safe_use_opioids_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_edtc_template_handler' ) ) {
	function qualinav_data_hub_download_edtc_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$components = array(
			'Home Medications',
			'Allergies and/or Reactions',
			'Medications Administered in ED',
			'ED Provider Note',
			'Mental Status/Orientation Assessment',
			'Reason for Transfer and/or Plan of Care',
			'Tests and/or Procedures Performed',
			'Tests and/or Procedures Results',
		);

		$rows = array(
			array( 'Measure Name', 'EDTC — Emergency Department Transfer Communication' ),
			array( 'Instructions', 'Use one nine-row block per reporting quarter. Enter one composite score row plus all eight Emergency Department Transfer Communication element rows. Enter the year and quarter in the first row of each completed block; the remaining rows in that block will copy them automatically. Leave unused blocks blank.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Quarter', 'EDTC Reporting Item', 'Num', 'Denom', 'Rate' ),
		);
		$year_validation_refs    = array();
		$quarter_validation_refs = array();
		for ( $group_idx = 0; $group_idx < 20; $group_idx++ ) {
			if ( $group_idx > 0 ) {
				$rows[] = array();
			}
			$block_start_row = count( $rows ) + 1;
			$year_validation_refs[]    = 'A' . (int) $block_start_row;
			$quarter_validation_refs[] = 'B' . (int) $block_start_row;
			$rows[] = array( '', '', 'Composite Score (All elements documented)', '', '', '' );
			foreach ( $components as $index => $component ) {
				$year_value = array( 'formula' => 'IF(A' . (int) $block_start_row . '="","",A' . (int) $block_start_row . ')' );
				$quarter_value = array( 'formula' => 'IF(B' . (int) $block_start_row . '="","",B' . (int) $block_start_row . ')' );
				$rows[] = array( $year_value, $quarter_value, $component, '', '', '' );
			}
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( in_array( $excel_row, array( 1, 2, 4, 6 ), true ) && $excel_col === 1 ) {
					$style = 1;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 1;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 4, 5 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 3 ) {
					$style = 4;
				}
				if ( is_array( $value ) && isset( $value['formula'] ) ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, (string) $value['formula'], 0 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="78" customHeight="1"';
			}
			if ( $excel_row > 6 && empty( $row ) ) {
				$row_attrs .= ' ht="10" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="60" customWidth="1"/><col min="4" max="6" width="18" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="2">';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="' . esc_attr( implode( ' ', $year_validation_refs ) ) . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="' . esc_attr( implode( ' ', $quarter_validation_refs ) ) . '"><formula1>&quot;Q1,Q2,Q3,Q4&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="5">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'edtc_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>EDTC Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="EDTC" sheetId="1" r:id="rId1"/></sheets><calcPr fullCalcOnLoad="1"/></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="edtc_emergency_department_transfer_communication_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_edtc_template', 'qualinav_data_hub_download_edtc_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_hcahps_template_handler' ) ) {
	function qualinav_data_hub_download_hcahps_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$measure = isset( $_GET['measure'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['measure'] ) ) : 'HCAHPS';
		if ( $measure === '' ) {
			$measure = 'HCAHPS';
		}
		$is_edtc = ( $measure === 'EDTC — Emergency Department Transfer Communication' );
		if ( $is_edtc ) {
			qualinav_data_hub_download_edtc_template_handler();
		}
		$template_label = 'HCAHPS';
		$template_instructions = 'Enter quarterly Hospital Consumer Assessment of Healthcare Providers and Systems results. Fill one row or bulk add multiple quarters. Rate is calculated as Numerator divided by Denominator.';

		$rows = array(
			array( 'Measure Name', $measure ),
			array( 'Instructions', $template_instructions ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Quarter', 'Num', 'Denom', 'Rate' ),
		);
		$template_row_count = 12;
		for ( $row_idx = 0; $row_idx < $template_row_count; $row_idx++ ) {
			$rows[] = array( '', '', '', '', '' );
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
					$style = 5;
				}
				if ( $excel_row === 1 && $excel_col === 2 ) {
					$style = 5;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 5;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 3, 4 ), true ) ) {
					$style = 2;
				}
				if ( $excel_row >= 7 && $excel_col === 5 ) {
					$cells .= qualinav_data_hub_xlsx_formula_cell( $excel_row, $excel_col, 'IF(D' . $excel_row . '>0,C' . $excel_row . '/D' . $excel_row . ',"")', 6 );
					continue;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="62" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="4" width="18" customWidth="1"/><col min="5" max="5" width="16" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:E1"/><mergeCell ref="B2:E2"/></mergeCells>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="2">';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="A7:A' . (int) $last_data_row . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="B7:B' . (int) $last_data_row . '"><formula1>&quot;Q1,Q2,Q3,Q4&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="7">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), strtolower( $template_label ) . '_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . qualinav_data_hub_xlsx_xml( $template_label ) . ' Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . qualinav_data_hub_xlsx_xml( $template_label ) . '" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( qualinav_data_hub_measure_filename_slug( $measure ) . '_template.xlsx' ) . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_hcahps_template', 'qualinav_data_hub_download_hcahps_template_handler' );
}

if ( ! function_exists( 'qualinav_data_hub_download_op18_template_handler' ) ) {
	function qualinav_data_hub_download_op18_template_handler() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Not logged in', 'qualinav-my-org' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( 'dm_save_nonce', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel template generation is unavailable on this site.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$current_year = (int) wp_date( 'Y' );
		$start_year   = 2012;
		$measure = 'OP-18 — Median ED Arrival to Departure Time (Discharged Patients)';
		$rows = array(
			array( 'Measure Name', $measure ),
			array( 'Instructions', 'Enter quarterly median Emergency Department arrival-to-departure time results. Fill one row or bulk add multiple quarters. Lower time is better.' ),
			array(),
			array( 'Hospital Data' ),
			array(),
			array( 'Year', 'Quarter', 'Median Minutes' ),
		);
		$template_row_count = 12;
		for ( $row_idx = 0; $row_idx < $template_row_count; $row_idx++ ) {
			$rows[] = array( '', '', '' );
		}

		$sheet_data = '';
		foreach ( $rows as $row_index => $row ) {
			$excel_row = $row_index + 1;
			$cells = '';
			foreach ( $row as $col_index => $value ) {
				$excel_col = $col_index + 1;
				$style = null;
				if ( $excel_col === 1 && in_array( $excel_row, array( 1, 2, 4, 6 ), true ) ) {
					$style = 5;
				}
				if ( $excel_row === 1 && $excel_col === 2 ) {
					$style = 5;
				}
				if ( $excel_row === 2 && $excel_col === 2 ) {
					$style = 3;
				}
				if ( $excel_row === 6 ) {
					$style = 5;
				}
				if ( $excel_row >= 7 && in_array( $excel_col, array( 1, 2, 3 ), true ) ) {
					$style = 2;
				}
				if ( $value === '' && $style === null ) {
					continue;
				}
				$cells .= qualinav_data_hub_xlsx_cell( $excel_row, $excel_col, $value, $style );
			}
			$row_attrs = ' r="' . (int) $excel_row . '"';
			if ( $excel_row === 2 ) {
				$row_attrs .= ' ht="70" customHeight="1"';
			}
			$sheet_data .= '<row' . $row_attrs . '>' . $cells . '</row>';
		}

		$year_options = array();
		for ( $option_year = $current_year; $option_year >= $start_year; $option_year-- ) {
			$year_options[] = (string) $option_year;
		}
		$year_formula = '&quot;' . qualinav_data_hub_xlsx_xml( implode( ',', $year_options ) ) . '&quot;';

		$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$sheet_xml .= '<cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="60" customWidth="1"/><col min="3" max="3" width="22" customWidth="1"/></cols>';
		$sheet_xml .= '<sheetData>' . $sheet_data . '</sheetData>';
		$sheet_xml .= '<mergeCells count="2"><mergeCell ref="B1:C1"/><mergeCell ref="B2:C2"/></mergeCells>';
		$last_data_row = count( $rows );
		$sheet_xml .= '<dataValidations count="2">';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="A7:A' . (int) $last_data_row . '"><formula1>' . $year_formula . '</formula1></dataValidation>';
		$sheet_xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="B7:B' . (int) $last_data_row . '"><formula1>&quot;Q1,Q2,Q3,Q4&quot;</formula1></dataValidation>';
		$sheet_xml .= '</dataValidations>';
		$sheet_xml .= '</worksheet>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles_xml .= '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>';
		$styles_xml .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>';
		$styles_xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7B7B7"/></left><right style="thin"><color rgb="FFB7B7B7"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom><diagonal/></border></borders>';
		$styles_xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles_xml .= '<cellXfs count="6">';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
		$styles_xml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>';
		$styles_xml .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

		$tmp = tempnam( get_temp_dir(), 'op18_template_' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'Could not create template file.', 'qualinav-my-org' ), '', array( 'response' => 500 ) );
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
		$zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Qualinav</Application></Properties>' );
		$zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>OP-18 Template</dc:title><dc:creator>Qualinav</dc:creator><cp:lastModifiedBy>Qualinav</cp:lastModifiedBy></cp:coreProperties>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="OP-18" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $styles_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="op_18_median_ed_arrival_to_departure_time_template.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	add_action( 'wp_ajax_dm_download_op18_template', 'qualinav_data_hub_download_op18_template_handler' );
}
