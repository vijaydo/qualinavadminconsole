<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Assets {
  private static bool $localized = false;

  public static function init(): void {
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
  }

  public static function enqueue(): void {
    self::register();

    if (!is_singular()) return;

    global $post;
    if (!$post || !has_shortcode($post->post_content, 'cah_assessment')) return;

    self::ensure_enqueued();
  }

  public static function register(): void {
    wp_register_style(
      'cah-assessment-frontend',
      CAH_ASSESS_URL . 'assets/css/frontend.css',
      [],
      CAH_ASSESS_VERSION
    );

    wp_register_script(
      'cah-assessment-frontend',
      CAH_ASSESS_URL . 'assets/js/frontend.js',
      [],
      CAH_ASSESS_VERSION,
      true
    );
  }

  public static function ensure_enqueued(): void {
    self::register();

    if (!wp_style_is('qualinav-font-awesome', 'enqueued')) {
        wp_enqueue_style('qualinav-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
    }

    wp_enqueue_style(
      'cah-assessment-frontend',
      CAH_ASSESS_URL . 'assets/css/frontend.css'
    );

    $design = get_option('cah_assess_design_settings', []);
    if (is_array($design) && !empty($design)) {
      $button = sanitize_hex_color((string)($design['button_color'] ?? ''));
      $buttonText = sanitize_hex_color((string)($design['button_text_color'] ?? ''));
      $pieFill = sanitize_hex_color((string)($design['pie_fill'] ?? ''));
      $pieTrack = sanitize_hex_color((string)($design['pie_track'] ?? ''));

      $vars = [];
      if ($button) $vars[] = '--cah-button:' . $button;
      if ($buttonText) $vars[] = '--cah-button-text:' . $buttonText;
      if ($pieFill) $vars[] = '--cah-pie-fill:' . $pieFill;
      if ($pieTrack) $vars[] = '--cah-pie-track:' . $pieTrack;

      if (!empty($vars)) {
        wp_add_inline_style('cah-assessment-frontend', '.cah-assessment--pro{' . implode(';', $vars) . ';}');
      }
    }

    wp_enqueue_script(
      'cah-assessment-frontend',
      CAH_ASSESS_URL . 'assets/js/frontend.js'
    );

    if (!self::$localized) {
      wp_localize_script('cah-assessment-frontend', 'CAH_ASSESS', [
        'restUrl' => esc_url_raw(rest_url('cah/v1/submissions')),
        'latestUrl' => esc_url_raw(rest_url('cah/v1/submissions/latest')),
        'nonce'   => wp_create_nonce('wp_rest'),
        'requireLogin' => CAH_ASSESS_REQUIRE_LOGIN ? 1 : 0,
        'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
        'ajaxNonce' => wp_create_nonce('save_assessment_mydata'),
      ]);
      self::$localized = true;
    }

    // If shortcode is rendered from a template after wp_head, print style immediately.
    if (did_action('wp_head')) {
      wp_print_styles('cah-assessment-frontend');
    }
  }
}
