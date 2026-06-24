<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Storage {
  private const MAX_LIST_LIMIT = 500;
  public const DB_VERSION = '2';

  public static function init(): void {
    self::maybe_upgrade();
  }

  public static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'cah_assessment_submissions';
  }

  public static function create_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $table = self::table();

    $sql = "CREATE TABLE $table (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      assessment_slug VARCHAR(190) NOT NULL,
      user_id BIGINT(20) UNSIGNED NULL,
      context_post_id BIGINT(20) UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      answers_json LONGTEXT NOT NULL,
      section_scores_json LONGTEXT NOT NULL,
      scores_json LONGTEXT NOT NULL,
      overall_score DECIMAL(4,2) NOT NULL DEFAULT 0.00,
      status VARCHAR(50) NOT NULL DEFAULT '',
      PRIMARY KEY  (id),
      KEY assessment_slug (assessment_slug),
      KEY user_id (user_id),
      KEY created_at (created_at),
      KEY slug_user_post_updated (assessment_slug, user_id, context_post_id, updated_at),
      KEY slug_updated (assessment_slug, updated_at)
    ) $charset_collate;";

    dbDelta($sql);
  }

  public static function maybe_upgrade(): void {
    $installed = (string)get_option('cah_assess_db_version', '0');
    if ($installed === self::DB_VERSION) {
      return;
    }

    self::create_tables();
    update_option('cah_assess_db_version', self::DB_VERSION);
  }

  public static function insert_submission(array $row): int {
    global $wpdb;
    $table = self::table();

    // Provide explicit formats for safety
    $formats = [
      '%s', // assessment_slug
      '%d', // user_id
      '%d', // context_post_id
      '%s', // created_at
      '%s', // updated_at
      '%s', // answers_json
      '%s', // section_scores_json
      '%s', // scores_json
      '%f', // overall_score
      '%s', // status
    ];

    $ok = $wpdb->insert($table, $row, $formats);
    if ($ok === false) {
      return 0;
    }

    return (int) $wpdb->insert_id;
  }

  public static function get_submissions(int $limit = 50, int $offset = 0, ?string $slug = null): array {
    global $wpdb;
    $table = self::table();
    $limit = max(1, min(self::MAX_LIST_LIMIT, $limit));
    $offset = max(0, $offset);
    $slug = $slug ? sanitize_key($slug) : null;

    if ($slug) {
      return $wpdb->get_results(
        $wpdb->prepare(
          "SELECT * FROM $table WHERE assessment_slug = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
          $slug, $limit, $offset
        ),
        ARRAY_A
      );
    }

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
      ),
      ARRAY_A
    );
  }

  public static function get_submission(int $id): ?array {
    global $wpdb;
    $table = self::table();
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
      ARRAY_A
    );
    return $row ?: null;
  }

  public static function to_result_payload(array $row, string $slug): array {
    $scores = json_decode((string)($row['scores_json'] ?? ''), true);
    $sectionScores = json_decode((string)($row['section_scores_json'] ?? ''), true);

    if (!is_array($scores)) $scores = [];
    if (!is_array($sectionScores)) $sectionScores = [];

    return [
      'submission_id' => (int)($row['id'] ?? 0),
      'assessment_slug' => $slug,
      'overall_score' => (float)($row['overall_score'] ?? 0),
      'status' => (string)($row['status'] ?? ''),
      'section_scores' => !empty($scores['section_scores']) && is_array($scores['section_scores'])
        ? $scores['section_scores']
        : $sectionScores,
      'saved_at' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
    ];
  }

  public static function get_average_overall(string $slug): ?float {
    global $wpdb;
    $table = self::table();
    $slug = sanitize_key($slug);
    if (!$slug) return null;

    $value = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT AVG(overall_score) FROM $table WHERE assessment_slug = %s",
        $slug
      )
    );

    if ($value === null) return null;
    if ($value === '') return null;
    return round((float)$value, 2);
  }

  public static function get_latest_submission(string $slug, ?int $userId = null, ?int $contextPostId = null): ?array {
    global $wpdb;
    $table = self::table();
    $slug = sanitize_key($slug);
    if (!$slug) return null;

    $where = ['assessment_slug = %s'];
    $params = [$slug];

    if ($userId !== null && $userId > 0) {
      $where[] = 'user_id = %d';
      $params[] = (int)$userId;
    } else {
      $where[] = 'user_id IS NULL';
    }

    if ($contextPostId !== null && $contextPostId > 0) {
      $where[] = 'context_post_id = %d';
      $params[] = (int)$contextPostId;
    }

    $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY updated_at DESC, id DESC LIMIT 1";
    $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);
    return $row ?: null;
  }
}
