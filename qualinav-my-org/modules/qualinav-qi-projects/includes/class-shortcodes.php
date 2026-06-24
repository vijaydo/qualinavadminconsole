<?php
/**
 * Front-end shortcodes:
 *   [qi_projects_dashboard]  — list of current user's projects + create-new form
 *   [qi_project_canvas]      — JSON-driven 4-tab canvas renderer (resolves project from ?qi=)
 *
 * Both require login + an active org context.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Shortcodes {

	public static function register() {
		add_shortcode( 'qi_projects_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_shortcode( 'qi_project_canvas',     array( __CLASS__, 'render_canvas' ) );
		add_action( 'wp_enqueue_scripts',       array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'body_class',               array( __CLASS__, 'body_class' ) );

		// Plugin-provided page templates (selectable in the page editor's Template dropdown)
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_templates' ) );
		add_filter( 'template_include',     array( __CLASS__, 'load_page_template' ) );
	}

	public static function body_class( $classes ) {
		// Plugin-owned route (e.g. /qi-projects/) — no $post, but still a QI page.
		if ( class_exists( 'Qualinav_QI_Router' ) && Qualinav_QI_Router::is_plugin_route() ) {
			$classes[] = 'qi-page';
			return $classes;
		}

		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return $classes;
		}

		$has_shortcode = has_shortcode( $post->post_content, 'qi_project_canvas' )
			|| has_shortcode( $post->post_content, 'qi_projects_dashboard' );

		$tpl = get_post_meta( $post->ID, '_wp_page_template', true );
		$is_qi_template = in_array( $tpl, array(
			'qualinav-qi-projects/templates/page-qi-projects.php',
			'qualinav-qi-projects/templates/page-qi-project-canvas.php',
		), true );

		if ( $has_shortcode || $is_qi_template ) {
			$classes[] = 'qi-page';
		}
		return $classes;
	}

	public static function register_page_templates( $templates ) {
		$templates['qualinav-qi-projects/templates/page-qi-projects.php']       = 'QI Projects — Dashboard';
		$templates['qualinav-qi-projects/templates/page-qi-project-canvas.php'] = 'QI Project — Canvas';
		return $templates;
	}

	public static function load_page_template( $template ) {
		$id = (int) get_queried_object_id();
		if ( $id <= 0 ) {
			return $template;
		}
		$page_template = (string) get_post_meta( $id, '_wp_page_template', true );
		if ( strpos( $page_template, 'qualinav-qi-projects/templates/' ) !== 0 ) {
			return $template;
		}
		$file = QUALINAV_QI_PLUGIN_DIR . str_replace( 'qualinav-qi-projects/', '', $page_template );
		if ( file_exists( $file ) ) {
			return $file;
		}
		return $template;
	}

	public static function enqueue_assets() {
		$is_plugin_route = class_exists( 'Qualinav_QI_Router' ) && Qualinav_QI_Router::is_plugin_route();

		if ( ! $is_plugin_route ) {
			global $post;
			if ( ! is_a( $post, 'WP_Post' ) ) {
				return;
			}
			$has_shortcode = has_shortcode( $post->post_content, 'qi_project_canvas' )
				|| has_shortcode( $post->post_content, 'qi_projects_dashboard' );

			$tpl            = get_post_meta( $post->ID, '_wp_page_template', true );
			$is_qi_template = in_array( $tpl, array(
				'qualinav-qi-projects/templates/page-qi-projects.php',
				'qualinav-qi-projects/templates/page-qi-project-canvas.php',
			), true );

			if ( ! $has_shortcode && ! $is_qi_template ) {
				return;
			}
		}
		wp_enqueue_style(
			'qualinav-qi',
			QUALINAV_QI_PLUGIN_URL . 'assets/css/qi-canvas.css',
			array(),
			QUALINAV_QI_VERSION
		);
		wp_enqueue_script(
			'qualinav-qi',
			QUALINAV_QI_PLUGIN_URL . 'assets/js/qi-canvas.js',
			array(),
			QUALINAV_QI_VERSION,
			true
		);
		wp_localize_script( 'qualinav-qi', 'QualinavQI', array(
			'restUrl' => esc_url_raw( rest_url( 'qualinav-qi/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'userId'  => get_current_user_id(),
		) );
	}

	public static function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to view your QI projects.</p>';
		}
		$user_id = get_current_user_id();
		$org_id  = Qualinav_QI_Org_Context::current_user_org_id();

		// Bootstrap a default org for first-time users so they can start using the plugin.
		// Real org provisioning will move to the My Org plugin.
		if ( $org_id <= 0 ) {
			$org_id = Qualinav_QI_Org_Context::ensure_default_org_for_user( $user_id );
		}

		$proj_repo = new Qualinav_QI_Project_Repository();
		$projects  = $proj_repo->list_for_org( $org_id, array( 'visible_to_user' => $user_id ) );
		$tpl_repo  = new Qualinav_QI_Template_Repository();
		$default   = $tpl_repo->find_template_by_slug( Qualinav_QI_Seeder::DEFAULT_SLUG, $org_id );

		$canvas_page_url = self::find_page_url_with_shortcode( 'qi_project_canvas' );

		// Bulk load aim statements for description previews on the project cards.
		$aim_by_project = array();
		if ( ! empty( $projects ) ) {
			global $wpdb;
			$ids        = array_map( 'intval', array_column( $projects, 'id' ) );
			$ph         = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$fields_tbl = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'project_fields';
			$rows       = $wpdb->get_results( $wpdb->prepare(
				"SELECT project_id, value_text FROM {$fields_tbl}
				 WHERE field_path = %s AND project_id IN ($ph) AND org_id = %d",
				array_merge( array( 'improvement_canvas.aim_statement' ), $ids, array( $org_id ) )
			), ARRAY_A );
			foreach ( $rows as $r ) {
				$aim_by_project[ (int) $r['project_id'] ] = (string) $r['value_text'];
			}
		}

		$tpl_version_id = ( $default && $default['current_version_id'] ) ? (int) $default['current_version_id'] : 0;

		ob_start();
		?>
		<?php $back_url = apply_filters( 'qualinav_qi_back_to_org_url', home_url( '/my-org/' ) ); ?>
		<div class="qi-dashboard"
			data-canvas-url="<?php echo esc_attr( (string) $canvas_page_url ); ?>"
			data-template-version-id="<?php echo esc_attr( $tpl_version_id ); ?>">

			<div class="qi-dashboard-topbar">
				<a class="qi-back-circle" href="<?php echo esc_url( $back_url ); ?>" aria-label="Back to My Org" title="Back to My Org">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="19" y1="12" x2="5" y2="12"/>
						<polyline points="12 19 5 12 12 5"/>
					</svg>
				</a>
				<h1 class="qi-topbar-title">
					<span class="qi-topbar-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M9 3h6"/>
							<path d="M10 3v7l-5 8a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-8V3"/>
						</svg>
					</span>
					QI Projects
				</h1>
			</div>

			<div class="qi-dashboard-search">
				<div class="qi-search-input-wrap">
					<svg class="qi-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="11" cy="11" r="8"/>
						<line x1="21" y1="21" x2="16.65" y2="16.65"/>
					</svg>
					<input type="search" id="qi-search" placeholder="Search projects..." autocomplete="off" />
				</div>
			</div>

			<div class="qi-projects-grid">
				<button type="button" class="qi-tile qi-tile-add"
					<?php echo $tpl_version_id ? '' : 'disabled title="No template available"'; ?>>
					<div class="qi-tile-body">
						<div class="qi-tile-icon qi-tile-icon-add" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
								<line x1="12" y1="5"  x2="12" y2="19"/>
								<line x1="5"  y1="12" x2="19" y2="12"/>
							</svg>
						</div>
						<div class="qi-tile-title">Add project</div>
					</div>
				</button>

				<?php foreach ( $projects as $p ) :
					$url       = $canvas_page_url ? add_query_arg( 'qi', (int) $p['id'], $canvas_page_url ) : '#';
					$status    = (string) $p['status'];
					$aim       = isset( $aim_by_project[ (int) $p['id'] ] ) ? $aim_by_project[ (int) $p['id'] ] : '';
					$search_kw = strtolower( $p['title'] . ' ' . $aim );
					$desc      = $aim !== '' ? $aim : 'No aim statement yet. Open to start.';
				?>
					<a class="qi-tile" href="<?php echo esc_url( $url ); ?>" data-qi-title="<?php echo esc_attr( $search_kw ); ?>">
						<span class="qi-tile-status qi-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( strtoupper( str_replace( '_', ' ', $status ) ) ); ?></span>
						<div class="qi-tile-body">
							<div class="qi-tile-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
									<rect x="4" y="3" width="16" height="18" rx="2"/>
									<path d="M8 3v3h8V3"/>
									<line x1="8"  y1="11" x2="16" y2="11"/>
									<line x1="8"  y1="15" x2="13" y2="15"/>
								</svg>
							</div>
							<div class="qi-tile-title"><?php echo esc_html( $p['title'] ); ?></div>
							<div class="qi-tile-desc<?php echo $aim === '' ? ' qi-tile-desc-empty' : ''; ?>"><?php echo esc_html( $desc ); ?></div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="qi-no-results" hidden>No projects match your search.</div>

			<div class="qi-modal" id="qi-create-modal" hidden>
				<div class="qi-modal-backdrop" data-qi-close></div>
				<div class="qi-modal-card" role="dialog" aria-labelledby="qi-create-title" aria-modal="true">
					<button type="button" class="qi-modal-close" data-qi-close aria-label="Close">&times;</button>
					<h2 id="qi-create-title">Create new QI project</h2>
					<label class="qi-modal-label">
						Project title
						<input type="text" id="qi-create-title-input" maxlength="200" autocomplete="off" placeholder="e.g. Reduce ED door-to-doc time" />
					</label>
					<div class="qi-modal-error" hidden></div>
					<div class="qi-modal-actions">
						<button type="button" class="qi-btn-secondary" data-qi-close>Cancel</button>
						<button type="button" class="qi-btn-primary" id="qi-create-submit">Create project</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_canvas() {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to view this project.</p>';
		}
		$project_id = class_exists( 'Qualinav_QI_Router' )
			? Qualinav_QI_Router::current_project_id()
			: ( isset( $_GET['qi'] ) ? (int) $_GET['qi'] : 0 );
		if ( $project_id <= 0 ) {
			return '<p>No project specified.</p>';
		}
		$user_id = get_current_user_id();
		$org_id  = Qualinav_QI_Org_Context::current_user_org_id();
		if ( $org_id <= 0 ) {
			return '<p>You are not a member of any organization.</p>';
		}

		$project = ( new Qualinav_QI_Project_Repository() )->find_for_org( $project_id, $org_id, $user_id );
		if ( ! $project ) {
			return '<p>Project not found.</p>';
		}

		$template  = ( new Qualinav_QI_Template_Repository() );
		$structure = $template->get_structure( (int) $project['template_version_id'] );
		if ( ! $structure || empty( $structure['tabs'] ) ) {
			return '<p>Template is not available.</p>';
		}

		// Pre-load all data for this project so renderer doesn't N+1
		$cards    = ( new Qualinav_QI_Card_Repository() )->list_for_project( $project_id, $org_id );
		$fields   = ( new Qualinav_QI_Field_Repository() )->list_for_project( $project_id, $org_id );
		$scores   = ( new Qualinav_QI_Score_Repository() )->list_for_project( $project_id, $org_id, $user_id );
		$measures = ( new Qualinav_QI_Measure_Repository() )->list_for_project( $project_id, $org_id );

		$by_field     = self::index_cards_by_field( $cards );
		$field_values = self::index_fields_by_path( $fields );

		ob_start();
		Qualinav_QI_Renderer::render_canvas( $project, $structure, $by_field, $field_values, $scores, $measures );
		return ob_get_clean();
	}

	private static function index_cards_by_field( $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			$slot = $r['slot_key'] === null ? '__none__' : $r['slot_key'];
			$out[ $r['field_path'] ][ $slot ][] = $r;
		}
		return $out;
	}

	private static function index_fields_by_path( $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			$out[ $r['field_path'] ] = $r;
		}
		return $out;
	}

	public static function find_page_url_with_shortcode( $shortcode ) {
		// Match 1: pages whose post_content contains the shortcode.
		$pages = get_posts( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => -1,
			's'           => '[' . $shortcode,
		) );
		foreach ( $pages as $p ) {
			if ( has_shortcode( $p->post_content, $shortcode ) ) {
				return get_permalink( $p->ID );
			}
		}

		// Match 2: pages assigned to the corresponding plugin template (legacy
		// setup, kept for sites that wired the page through the template
		// dropdown — the shortcode lives in the template file, not the
		// content, so Match 1 misses these).
		$template_for_shortcode = array(
			'qi_projects_dashboard' => 'qualinav-qi-projects/templates/page-qi-projects.php',
			'qi_project_canvas'     => 'qualinav-qi-projects/templates/page-qi-project-canvas.php',
		);
		if ( isset( $template_for_shortcode[ $shortcode ] ) ) {
			$template_pages = get_posts( array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => 1,
				'meta_key'    => '_wp_page_template',
				'meta_value'  => $template_for_shortcode[ $shortcode ],
			) );
			if ( ! empty( $template_pages ) ) {
				return get_permalink( $template_pages[0]->ID );
			}
		}

		// Match 3: plugin-owned route. The router serves both the dashboard
		// and the canvas from /qi-projects/ (the canvas is /qi-projects/?qi=<id>
		// or /qi-projects/<id>/), so the same URL is the correct fallback for
		// either shortcode — no WP page needs to exist.
		if ( in_array( $shortcode, array( 'qi_projects_dashboard', 'qi_project_canvas' ), true )
			&& class_exists( 'Qualinav_QI_Router' ) ) {
			return Qualinav_QI_Router::dashboard_url();
		}

		return null;
	}
}
