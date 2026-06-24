<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Admin {
  private const MENU_SLUG = 'cah-assessments';
  private const OVERRIDES_OPTION = 'cah_assess_definitions_overrides';
  private const DESIGN_OPTION = 'cah_assess_design_settings';

  public static function init(): void {
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_init', [__CLASS__, 'handle_post']);
  }

  public static function menu(): void {
    add_menu_page(
      'QualiNav Assessment & Readiness',
      'QualiNav Assessments',
      'manage_options',
      self::MENU_SLUG,
      [__CLASS__, 'page'],
      'dashicons-clipboard',
      60
    );
  }

  public static function handle_post(): void {
    if (!current_user_can('manage_options')) return;
    if (!is_admin()) return;
    if (empty($_POST['cah_assess_action'])) return;

    $action = sanitize_key((string)wp_unslash($_POST['cah_assess_action']));
    switch ($action) {
      case 'save_definition':
        self::save_definition_from_post();
        break;
      case 'save_design':
        self::save_design_from_post();
        break;
      default:
        break;
    }
  }

  private static function save_definition_from_post(): void {
    check_admin_referer('cah_assess_save_definition');

    $slug = sanitize_key((string)wp_unslash($_POST['assessment_slug'] ?? ''));
    $allowed = ['org-assessment', 'tjc-readiness-2026'];
    if (!$slug || !in_array($slug, $allowed, true)) return;

    $current = Registry::get($slug);
    if (!$current) return;

    $input = $_POST['definition'] ?? [];
    if (is_array($input)) {
      $input = wp_unslash($input);
    }
    if (!is_array($input)) return;

    $sanitized = self::sanitize_definition($input, $current, $slug);
    $overrides = get_option(self::OVERRIDES_OPTION, []);
    if (!is_array($overrides)) $overrides = [];
    $overrides[$slug] = $sanitized;
    update_option(self::OVERRIDES_OPTION, $overrides, false);

    wp_safe_redirect(add_query_arg([
      'page' => self::MENU_SLUG,
      'tab' => $slug === 'org-assessment' ? 'org' : 'readiness',
      'updated' => '1',
    ], admin_url('admin.php')));
    exit;
  }

  private static function save_design_from_post(): void {
    check_admin_referer('cah_assess_save_design');

    $input = $_POST['design'] ?? [];
    if (is_array($input)) {
      $input = wp_unslash($input);
    }
    if (!is_array($input)) return;

    $settings = [
      'button_color' => self::sanitize_hex($input['button_color'] ?? '#03283E', '#03283E'),
      'button_text_color' => self::sanitize_hex($input['button_text_color'] ?? '#FFFFFF', '#FFFFFF'),
      'pie_fill' => self::sanitize_hex($input['pie_fill'] ?? '#03283E', '#03283E'),
      'pie_track' => self::sanitize_hex($input['pie_track'] ?? '#A8DBE6', '#A8DBE6'),
    ];
    update_option(self::DESIGN_OPTION, $settings, false);

    wp_safe_redirect(add_query_arg([
      'page' => self::MENU_SLUG,
      'tab' => 'design',
      'updated' => '1',
    ], admin_url('admin.php')));
    exit;
  }

  private static function sanitize_definition(array $input, array $base, string $slug): array {
    $out = $base;
    $out['slug'] = $slug;
    $out['title'] = sanitize_text_field((string)($input['title'] ?? ($base['title'] ?? '')));
    $out['subtitle'] = sanitize_text_field((string)($input['subtitle'] ?? ($base['subtitle'] ?? '')));
    $out['instructions'] = wp_kses_post((string)($input['instructions'] ?? ($base['instructions'] ?? '')));

    $sections = [];
    $rawSections = $input['sections'] ?? [];
    if (is_array($rawSections)) {
      foreach ($rawSections as $si => $section) {
        if (!is_array($section)) continue;

        $sectionId = sanitize_key((string)($section['id'] ?? ''));
        $sectionTitle = sanitize_text_field((string)($section['title'] ?? ''));
        $sectionFocus = sanitize_text_field((string)($section['focus'] ?? ''));

        if (!$sectionId) $sectionId = 'section_' . ((int)$si + 1);
        if (!$sectionTitle) $sectionTitle = 'Section ' . ((int)$si + 1);

        $questions = [];
        $rawQuestions = $section['questions'] ?? [];
        if (is_array($rawQuestions)) {
          foreach ($rawQuestions as $qi => $q) {
            if (!is_array($q)) continue;
            $qid = sanitize_key((string)($q['id'] ?? ''));
            $label = sanitize_textarea_field((string)($q['label'] ?? ''));
            $hint = sanitize_text_field((string)($q['hint'] ?? ''));
            if (!$label) continue;
            if (!$qid) $qid = 'q_' . ((int)$si + 1) . '_' . ((int)$qi + 1);
            $row = ['id' => $qid, 'label' => $label];
            if ($hint !== '') $row['hint'] = $hint;
            $questions[] = $row;
          }
        }

        $sections[] = [
          'id' => $sectionId,
          'title' => $sectionTitle,
          'focus' => $sectionFocus,
          'questions' => $questions,
        ];
      }
    }

    $out['sections'] = $sections;
    return $out;
  }

  private static function sanitize_hex($value, string $fallback): string {
    $hex = sanitize_hex_color((string)$value);
    return $hex ?: $fallback;
  }

  public static function page(): void {
    if (!current_user_can('manage_options')) return;

    $tab = sanitize_key((string)($_GET['tab'] ?? 'hub'));
    $validTabs = ['hub', 'org', 'readiness', 'design', 'data', 'health'];
    if (!in_array($tab, $validTabs, true)) $tab = 'hub';

    echo '<div class="wrap"><h1>QualiNav Assessment &amp; Readiness</h1>';
    if (!empty($_GET['updated'])) {
      echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    self::render_tabs($tab);

    if ($tab === 'hub') self::render_hub_tab();
    if ($tab === 'org') self::render_assessment_editor('org-assessment', 'ORG Assessment');
    if ($tab === 'readiness') self::render_assessment_editor('tjc-readiness-2026', 'Readiness Assessment');
    if ($tab === 'design') self::render_design_tab();
    if ($tab === 'data') self::render_data_tab();
    if ($tab === 'health') self::render_health_tab();

    self::render_editor_assets();
    echo '</div>';
  }

  private static function render_tabs(string $current): void {
    $tabs = [
      'hub' => 'Hub & Shortcodes',
      'org' => 'ORG Assessment',
      'readiness' => 'Readiness',
      'design' => 'Data & Design',
      'data' => 'All Data',
      'health' => 'Health',
    ];

    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $slug => $label) {
      $url = add_query_arg(['page' => self::MENU_SLUG, 'tab' => $slug], admin_url('admin.php'));
      $class = $slug === $current ? 'nav-tab nav-tab-active' : 'nav-tab';
      echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</h2>';
  }

  private static function render_hub_tab(): void {
    echo '<div style="max-width:980px;background:#fff;border:1px solid #dcdcde;padding:16px;margin-top:16px;">';
    echo '<h2>Assessment Hub</h2>';
    echo '<p>This plugin powers QualiNav assessments, scoring, and submission storage. Use the tabs to manage content and design.</p>';
    echo '<h3>Shortcodes</h3>';
    echo '<ul>';
    echo '<li><code>[cah_assessment slug="org-assessment"]</code></li>';
    echo '<li><code>[cah_assessment slug="tjc-readiness-2026"]</code></li>';
    echo '</ul>';
    echo '<p>Place these in pages, posts, or templates using <code>do_shortcode()</code>.</p>';
    echo '</div>';
  }

  private static function render_assessment_editor(string $slug, string $label): void {
    $def = Registry::get($slug);
    if (!$def) {
      echo '<p>Assessment not found.</p>';
      return;
    }

    echo '<form method="post" style="margin-top:16px;">';
    wp_nonce_field('cah_assess_save_definition');
    echo '<input type="hidden" name="cah_assess_action" value="save_definition">';
    echo '<input type="hidden" name="assessment_slug" value="' . esc_attr($slug) . '">';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;">';
    echo '<h2>' . esc_html($label) . ' Editor</h2>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row">Title</th><td><input type="text" class="regular-text" name="definition[title]" value="' . esc_attr((string)($def['title'] ?? '')) . '"></td></tr>';
    echo '<tr><th scope="row">Subtitle</th><td><input type="text" class="regular-text" name="definition[subtitle]" value="' . esc_attr((string)($def['subtitle'] ?? '')) . '"></td></tr>';
    echo '<tr><th scope="row">Instructions</th><td><textarea name="definition[instructions]" rows="6" class="large-text">' . esc_textarea((string)($def['instructions'] ?? '')) . '</textarea></td></tr>';
    echo '</tbody></table>';

    echo '<hr><h3>Sections &amp; Questions</h3>';
    echo '<div data-cah-admin-sections>';
    foreach (($def['sections'] ?? []) as $si => $section) {
      self::render_section_block((int)$si, $section);
    }
    echo '</div>';
    echo '<p><button type="button" class="button" data-cah-add-section>Add Section</button></p>';
    submit_button('Save Assessment');
    echo '</div>';
    echo '</form>';
  }

  private static function render_section_block(int $si, array $section): void {
    $sid = (string)($section['id'] ?? '');
    $title = (string)($section['title'] ?? '');
    $focus = (string)($section['focus'] ?? '');

    echo '<div class="cah-admin-section" data-section style="border:1px solid #dcdcde;padding:12px;margin-bottom:12px;background:#fcfcfc;">';
    echo '<p><strong>Section</strong> <button type="button" class="button-link-delete" data-cah-remove-section>Remove</button></p>';
    echo '<p><label>ID<br><input data-np="definition[sections][{s}][id]" name="definition[sections][' . $si . '][id]" type="text" class="regular-text" value="' . esc_attr($sid) . '"></label></p>';
    echo '<p><label>Title<br><input data-np="definition[sections][{s}][title]" name="definition[sections][' . $si . '][title]" type="text" class="large-text" value="' . esc_attr($title) . '"></label></p>';
    echo '<p><label>Focus<br><input data-np="definition[sections][{s}][focus]" name="definition[sections][' . $si . '][focus]" type="text" class="large-text" value="' . esc_attr($focus) . '"></label></p>';

    echo '<div data-questions>';
    foreach (($section['questions'] ?? []) as $qi => $q) {
      self::render_question_block($si, (int)$qi, $q);
    }
    echo '</div>';
    echo '<p><button type="button" class="button" data-cah-add-question>Add Question</button></p>';
    echo '</div>';
  }

  private static function render_question_block(int $si, int $qi, array $q): void {
    $id = (string)($q['id'] ?? '');
    $label = (string)($q['label'] ?? '');
    $hint = (string)($q['hint'] ?? '');

    echo '<div class="cah-admin-question" data-question style="border:1px dashed #ccd0d4;padding:10px;margin:8px 0;background:#fff;">';
    echo '<p><strong>Question</strong> <button type="button" class="button-link-delete" data-cah-remove-question>Remove</button></p>';
    echo '<p><label>ID<br><input data-np="definition[sections][{s}][questions][{q}][id]" name="definition[sections][' . $si . '][questions][' . $qi . '][id]" type="text" class="regular-text" value="' . esc_attr($id) . '"></label></p>';
    echo '<p><label>Label<br><textarea data-np="definition[sections][{s}][questions][{q}][label]" name="definition[sections][' . $si . '][questions][' . $qi . '][label]" rows="2" class="large-text">' . esc_textarea($label) . '</textarea></label></p>';
    echo '<p><label>Hint (optional)<br><input data-np="definition[sections][{s}][questions][{q}][hint]" name="definition[sections][' . $si . '][questions][' . $qi . '][hint]" type="text" class="large-text" value="' . esc_attr($hint) . '"></label></p>';
    echo '</div>';
  }

  private static function render_design_tab(): void {
    $settings = get_option(self::DESIGN_OPTION, []);
    if (!is_array($settings)) $settings = [];
    $button = $settings['button_color'] ?? '#03283E';
    $buttonText = $settings['button_text_color'] ?? '#FFFFFF';
    $pieFill = $settings['pie_fill'] ?? '#03283E';
    $pieTrack = $settings['pie_track'] ?? '#A8DBE6';

    echo '<form method="post" style="margin-top:16px;">';
    wp_nonce_field('cah_assess_save_design');
    echo '<input type="hidden" name="cah_assess_action" value="save_design">';
    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:720px;">';
    echo '<h2>Data &amp; Design Settings</h2>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row">Button Color</th><td><input type="text" name="design[button_color]" value="' . esc_attr($button) . '" class="regular-text"></td></tr>';
    echo '<tr><th scope="row">Button Text Color</th><td><input type="text" name="design[button_text_color]" value="' . esc_attr($buttonText) . '" class="regular-text"></td></tr>';
    echo '<tr><th scope="row">Donut Fill Color</th><td><input type="text" name="design[pie_fill]" value="' . esc_attr($pieFill) . '" class="regular-text"></td></tr>';
    echo '<tr><th scope="row">Donut Track Color</th><td><input type="text" name="design[pie_track]" value="' . esc_attr($pieTrack) . '" class="regular-text"></td></tr>';
    echo '</tbody></table>';
    submit_button('Save Design');
    echo '</div>';
    echo '</form>';
  }

  private static function render_data_tab(): void {
    $viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

    echo '<div style="margin-top:16px;">';
    if ($viewId) {
      $row = Storage::get_submission($viewId);
      if (!$row) {
        echo '<p>Submission not found.</p></div>';
        return;
      }
      $back = add_query_arg(['page' => self::MENU_SLUG, 'tab' => 'data'], admin_url('admin.php'));
      echo '<p><a href="' . esc_url($back) . '">Back</a></p>';
      echo '<h2>Submission #' . (int)$row['id'] . '</h2>';
      echo '<p><strong>Assessment:</strong> ' . esc_html($row['assessment_slug']) . '</p>';
      echo '<p><strong>Date:</strong> ' . esc_html($row['created_at']) . '</p>';
      echo '<p><strong>Overall:</strong> ' . esc_html($row['overall_score']) . ' <strong>Status:</strong> ' . esc_html($row['status']) . '</p>';
      echo '<h3>Section Scores</h3>';
      echo '<pre style="background:#fff;padding:12px;border:1px solid #ddd;max-width:1100px;overflow:auto;">' . esc_html($row['section_scores_json']) . '</pre>';
      echo '<h3>Answers</h3>';
      echo '<pre style="background:#fff;padding:12px;border:1px solid #ddd;max-width:1100px;overflow:auto;">' . esc_html($row['answers_json']) . '</pre>';
      echo '</div>';
      return;
    }

    $rows = Storage::get_submissions(100, 0);
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Assessment</th><th>Date</th><th>Overall</th><th>Status</th><th>User</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      $link = add_query_arg([
        'page' => self::MENU_SLUG,
        'tab' => 'data',
        'view' => (int)$r['id'],
      ], admin_url('admin.php'));
      echo '<tr>';
      echo '<td>' . (int)$r['id'] . '</td>';
      echo '<td>' . esc_html((string)$r['assessment_slug']) . '</td>';
      echo '<td>' . esc_html((string)$r['created_at']) . '</td>';
      echo '<td>' . esc_html((string)$r['overall_score']) . '</td>';
      echo '<td>' . esc_html((string)$r['status']) . '</td>';
      echo '<td>' . esc_html($r['user_id'] ?: '-') . '</td>';
      echo '<td><a class="button" href="' . esc_url($link) . '">View</a></td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
  }

  private static function render_health_tab(): void {
    global $wpdb;

    $table = Storage::table();
    $tableExists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    $dbVersionInstalled = (string)get_option('cah_assess_db_version', '0');
    $dbVersionExpected = Storage::DB_VERSION;
    $schemaStatus = ($dbVersionInstalled === $dbVersionExpected) ? 'OK' : 'Needs upgrade';

    $routes = [];
    if (function_exists('rest_get_server')) {
      $server = rest_get_server();
      if ($server) {
        $routes = $server->get_routes();
      }
    }
    $routeSubmit = isset($routes['/cah/v1/submissions']) ? 'OK' : 'Missing';
    $routeLatest = isset($routes['/cah/v1/submissions/latest']) ? 'OK' : 'Missing';

    $totalRows = 0;
    $orgRows = 0;
    $readinessRows = 0;
    $queryMs = 0.0;
    if ($tableExists) {
      $start = microtime(true);
      $totalRows = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
      $orgRows = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE assessment_slug = %s", 'org-assessment'));
      $readinessRows = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE assessment_slug = %s", 'tjc-readiness-2026'));
      $queryMs = round((microtime(true) - $start) * 1000, 2);
    }

    $latestOrg = Storage::get_latest_submission('org-assessment', is_user_logged_in() ? get_current_user_id() : null, 0);
    $latestReadiness = Storage::get_latest_submission('tjc-readiness-2026', is_user_logged_in() ? get_current_user_id() : null, 0);

    echo '<div style="margin-top:16px;max-width:980px;">';
    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;">';
    echo '<h2>Plugin Health</h2>';
    echo '<table class="widefat striped" style="max-width:760px;"><tbody>';
    self::health_row('Plugin version', esc_html(CAH_ASSESS_VERSION));
    self::health_row('DB version', esc_html($dbVersionInstalled . ' / expected ' . $dbVersionExpected), $dbVersionInstalled === $dbVersionExpected);
    self::health_row('Submissions table', esc_html($table), $tableExists);
    self::health_row('Schema status', esc_html($schemaStatus), $schemaStatus === 'OK');
    self::health_row('REST route /submissions', esc_html($routeSubmit), $routeSubmit === 'OK');
    self::health_row('REST route /submissions/latest', esc_html($routeLatest), $routeLatest === 'OK');
    self::health_row('Total submissions', (string)$totalRows, true);
    self::health_row('ORG submissions', (string)$orgRows, true);
    self::health_row('Readiness submissions', (string)$readinessRows, true);
    self::health_row('DB health query time', esc_html($queryMs . ' ms'), $tableExists);
    self::health_row('Latest ORG submission', esc_html($latestOrg['updated_at'] ?? $latestOrg['created_at'] ?? 'None'), true);
    self::health_row('Latest Readiness submission', esc_html($latestReadiness['updated_at'] ?? $latestReadiness['created_at'] ?? 'None'), true);
    echo '</tbody></table>';
    echo '<p style="margin-top:10px;color:#555;">If DB version is not matching expected, deactivate/activate the plugin once to force schema refresh.</p>';
    echo '</div>';
    echo '</div>';
  }

  private static function health_row(string $label, string $value, bool $ok = true): void {
    $status = $ok ? 'OK' : 'Check';
    $statusColor = $ok ? '#1a7f37' : '#b54708';
    echo '<tr>';
    echo '<th scope="row" style="width:240px;">' . esc_html($label) . '</th>';
    echo '<td>' . $value . '</td>';
    echo '<td style="width:80px;color:' . esc_attr($statusColor) . ';font-weight:600;">' . esc_html($status) . '</td>';
    echo '</tr>';
  }

  private static function render_editor_assets(): void {
    echo '<script>
      (function(){
        function reindex(){
          document.querySelectorAll("[data-cah-admin-sections] [data-section]").forEach(function(section, s){
            section.querySelectorAll("[data-np]").forEach(function(el){
              var name = el.getAttribute("data-np");
              if (!name) return;
              var qWrap = el.closest("[data-question]");
              if (!qWrap) {
                el.name = name.replaceAll("{s}", String(s));
                return;
              }
              var q = Array.from(section.querySelectorAll("[data-question]")).indexOf(qWrap);
              el.name = name.replaceAll("{s}", String(s)).replaceAll("{q}", String(q));
            });
          });
        }

        function newQuestion(){
          var div = document.createElement("div");
          div.className = "cah-admin-question";
          div.setAttribute("data-question", "");
          div.style.cssText = "border:1px dashed #ccd0d4;padding:10px;margin:8px 0;background:#fff;";
          div.innerHTML = \'<p><strong>Question</strong> <button type="button" class="button-link-delete" data-cah-remove-question>Remove</button></p>\' +
            \'<p><label>ID<br><input data-np="definition[sections][{s}][questions][{q}][id]" type="text" class="regular-text" value=""></label></p>\' +
            \'<p><label>Label<br><textarea data-np="definition[sections][{s}][questions][{q}][label]" rows="2" class="large-text"></textarea></label></p>\' +
            \'<p><label>Hint (optional)<br><input data-np="definition[sections][{s}][questions][{q}][hint]" type="text" class="large-text" value=""></label></p>\';
          return div;
        }

        function newSection(){
          var div = document.createElement("div");
          div.className = "cah-admin-section";
          div.setAttribute("data-section", "");
          div.style.cssText = "border:1px solid #dcdcde;padding:12px;margin-bottom:12px;background:#fcfcfc;";
          div.innerHTML = \'<p><strong>Section</strong> <button type="button" class="button-link-delete" data-cah-remove-section>Remove</button></p>\' +
            \'<p><label>ID<br><input data-np="definition[sections][{s}][id]" type="text" class="regular-text" value=""></label></p>\' +
            \'<p><label>Title<br><input data-np="definition[sections][{s}][title]" type="text" class="large-text" value=""></label></p>\' +
            \'<p><label>Focus<br><input data-np="definition[sections][{s}][focus]" type="text" class="large-text" value=""></label></p>\' +
            \'<div data-questions></div>\' +
            \'<p><button type="button" class="button" data-cah-add-question>Add Question</button></p>\';
          return div;
        }

        document.addEventListener("click", function(e){
          var addSection = e.target.closest("[data-cah-add-section]");
          if (addSection) {
            var wrap = document.querySelector("[data-cah-admin-sections]");
            if (!wrap) return;
            wrap.appendChild(newSection());
            reindex();
            return;
          }

          var removeSection = e.target.closest("[data-cah-remove-section]");
          if (removeSection) {
            var section = removeSection.closest("[data-section]");
            if (section) section.remove();
            reindex();
            return;
          }

          var addQuestion = e.target.closest("[data-cah-add-question]");
          if (addQuestion) {
            var section2 = addQuestion.closest("[data-section]");
            var qWrap = section2 ? section2.querySelector("[data-questions]") : null;
            if (!qWrap) return;
            qWrap.appendChild(newQuestion());
            reindex();
            return;
          }

          var removeQuestion = e.target.closest("[data-cah-remove-question]");
          if (removeQuestion) {
            var q = removeQuestion.closest("[data-question]");
            if (q) q.remove();
            reindex();
          }
        });

        document.querySelectorAll("form").forEach(function(form){
          form.addEventListener("submit", reindex);
        });

        reindex();
      })();
    </script>';
  }
}
