<?php
/**
 * Hybrid CPT: qi_project is a thin WP-side shell that gives us auth, REST plumbing,
 * and a stable WP-side ID for the AI brain. All structured data lives in wp_qi_* tables,
 * linked via wp_qi_projects.post_id.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_CPT {

	const POST_TYPE = 'qi_project';

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'QI Projects', 'qualinav-qi-projects' ),
				'public'              => false,
				// UI/REST plumbing stays on, but the standalone CPT menu is hidden:
				// the single "QI Projects" admin menu is the one in class-admin.php.
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'rest_base'           => 'qi-projects',
				'menu_icon'           => 'dashicons-clipboard',
				'menu_position'       => 30,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
			)
		);
	}
}
