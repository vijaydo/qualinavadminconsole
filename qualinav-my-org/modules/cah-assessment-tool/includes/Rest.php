<?php
namespace CAH_Assess;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) exit;

class Rest {
  private const RATE_LIMIT_WINDOW = 60;
  private const RATE_LIMIT_MAX = 20;

  public static function init(): void {
    add_action('rest_api_init', [__CLASS__, 'routes']);
  }

  public static function routes(): void {
    register_rest_route('cah/v1', '/submissions', [
      'methods'  => 'POST',
      'callback' => [__CLASS__, 'create_submission'],
      'args' => [
        'assessment_slug' => [
          'required' => true,
          'sanitize_callback' => 'sanitize_key',
          'validate_callback' => function ($value) {
            return is_string($value) && $value !== '';
          },
        ],
        'context_post_id' => [
          'required' => false,
          'sanitize_callback' => 'absint',
        ],
        'answers' => [
          'required' => true,
          'validate_callback' => function ($value) {
            return is_array($value);
          },
        ],
      ],
      'permission_callback' => function () {
        if (CAH_ASSESS_REQUIRE_LOGIN && !is_user_logged_in()) return false;
        return true;
      }
    ]);

    register_rest_route('cah/v1', '/submissions/latest', [
      'methods'  => 'GET',
      'callback' => [__CLASS__, 'latest_submission'],
      'args' => [
        'assessment_slug' => [
          'required' => true,
          'sanitize_callback' => 'sanitize_key',
          'validate_callback' => function ($value) {
            return is_string($value) && $value !== '';
          },
        ],
        'context_post_id' => [
          'required' => false,
          'sanitize_callback' => 'absint',
        ],
      ],
      'permission_callback' => function () {
        if (CAH_ASSESS_REQUIRE_LOGIN && !is_user_logged_in()) return false;
        return true;
      }
    ]);
  }

  public static function create_submission(WP_REST_Request $req) {
    $slug = sanitize_key((string)$req->get_param('assessment_slug'));
    $answers = $req->get_param('answers');

    if (!$slug) return new WP_Error('bad_request', 'Missing assessment_slug', ['status' => 400]);
    if (!is_array($answers)) return new WP_Error('bad_request', 'Invalid answers payload', ['status' => 400]);
    if (self::is_rate_limited($slug)) {
      return new WP_Error('rate_limited', 'Too many submissions. Please wait and try again.', ['status' => 429]);
    }

    $def = Registry::get($slug);
    if (!$def) return new WP_Error('not_found', 'Assessment not found', ['status' => 404]);

    $clean = [];
    foreach ($answers as $qid => $val) {
      $qid = sanitize_key((string)$qid);
      $val = (int)$val;
      if ($qid && $val >= 1 && $val <= 5) $clean[$qid] = $val;
    }

    // Require all questions answered
    $required = [];
    foreach (($def['sections'] ?? []) as $section) {
      foreach (($section['questions'] ?? []) as $q) {
        if (!empty($q['id'])) $required[] = sanitize_key($q['id']);
      }
    }
    $missing = array_values(array_diff($required, array_keys($clean)));
    if (count($missing)) {
      return new WP_Error('incomplete', 'Please answer all questions before submitting.', ['status' => 400]);
    }

    $scores = Scoring::calculate($def, $clean);

    $now = current_time('mysql');
    $userId = is_user_logged_in() ? get_current_user_id() : null;
    $contextPostId = (int)$req->get_param('context_post_id');

    $row = [
      'assessment_slug' => $slug,
      'user_id' => $userId ? (int)$userId : null,
      'context_post_id' => $contextPostId ?: null,
      'created_at' => $now,
      'updated_at' => $now,
      'answers_json' => wp_json_encode($clean),
      'section_scores_json' => wp_json_encode($scores['section_scores']),
      'scores_json' => wp_json_encode($scores),
      'overall_score' => (float)$scores['overall_score'],
      'status' => sanitize_text_field($scores['status']),
    ];

    $id = Storage::insert_submission($row);
    if ($id <= 0) {
      return new WP_Error('db_error', 'Unable to save submission. Please try again.', ['status' => 500]);
    }

    $resp = [
      'submission_id' => $id,
      'assessment_slug' => $slug,
      'overall_score' => $scores['overall_score'],
      'status' => $scores['status'],
      'section_scores' => $scores['section_scores'],
    ];

    return new WP_REST_Response($resp, 201);
  }

  private static function is_rate_limited(string $slug): bool {
    $slug = sanitize_key($slug);
    if (!$slug) return true;

    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string)wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key = 'cah_rl_' . md5($slug . '|' . $ip);
    $current = get_transient($key);
    $count = is_numeric($current) ? (int)$current : 0;

    if ($count >= self::RATE_LIMIT_MAX) {
      return true;
    }

    set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    return false;
  }

  public static function latest_submission(WP_REST_Request $req) {
    $slug = sanitize_key((string)$req->get_param('assessment_slug'));
    if (!$slug) return new WP_Error('bad_request', 'Missing assessment_slug', ['status' => 400]);

    $def = Registry::get($slug);
    if (!$def) return new WP_Error('not_found', 'Assessment not found', ['status' => 404]);

    $contextPostId = (int)$req->get_param('context_post_id');
    $userId = is_user_logged_in() ? get_current_user_id() : null;

    $latest = Storage::get_latest_submission(
      $slug,
      $userId ? (int)$userId : null,
      $contextPostId ?: null
    );

    if (!$latest) {
      return new WP_REST_Response(['found' => false], 200);
    }

    $resp = Storage::to_result_payload($latest, $slug);
    $resp['found'] = true;

    return new WP_REST_Response($resp, 200);
  }
}
