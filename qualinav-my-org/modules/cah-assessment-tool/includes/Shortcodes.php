<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Shortcodes {
  public static function init(): void {
    add_shortcode('cah_assessment', [__CLASS__, 'render']);
  }

  public static function render($atts): string {
    $atts = shortcode_atts([
      'slug' => '',
    ], $atts);

    $slug = sanitize_key($atts['slug']);
    if (!$slug) return '<div class="cah-assessment"><div class="cah-error">Missing assessment slug.</div></div>';

    $def = Registry::get($slug);
    if (!$def) return '<div class="cah-assessment"><div class="cah-error">Assessment not found.</div></div>';

    $wrapperClass = apply_filters('cah_assessment_wrapper_class', 'cah-assessment');
    $primaryButtonClass = apply_filters('cah_assessment_primary_button_class', 'cah-btn cah-btn-primary');

    $contextPostId = get_the_ID() ?: 0;
    $loggedIn = is_user_logged_in();
    $initialResultPayload = null;

    $latest = Storage::get_latest_submission(
      $slug,
      $loggedIn ? (int)get_current_user_id() : null,
      $contextPostId ?: null
    );

    if (is_array($latest) && !empty($latest)) {
      $initialResultPayload = Storage::to_result_payload($latest, $slug);
    }

    Assets::ensure_enqueued();

    ob_start();
    include CAH_ASSESS_PATH . 'templates/assessment.php';
    return (string)ob_get_clean();
  }
}
