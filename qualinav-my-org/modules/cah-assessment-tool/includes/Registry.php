<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Registry {
  private static array $assessments = [];

  public static function init(): void {
    self::load_all();
  }

  private static function load_all(): void {
    $files = glob(CAH_ASSESS_PATH . 'data/*.php');
    if (!$files) return;

    foreach ($files as $file) {
      $def = require $file;
      if (!is_array($def) || empty($def['slug'])) continue;
      self::$assessments[$def['slug']] = $def;
    }

    $overrides = get_option('cah_assess_definitions_overrides', []);
    if (!is_array($overrides)) return;

    foreach ($overrides as $slug => $override) {
      $slug = sanitize_key((string)$slug);
      if (!$slug || !isset(self::$assessments[$slug])) continue;
      if (!is_array($override)) continue;

      // Merge saved editor overrides on top of file defaults.
      self::$assessments[$slug] = array_replace_recursive(self::$assessments[$slug], $override);
      self::$assessments[$slug]['slug'] = $slug;
    }
  }

  public static function get(string $slug): ?array {
    $slug = sanitize_key($slug);
    return self::$assessments[$slug] ?? null;
  }

  public static function all(): array {
    return self::$assessments;
  }
}
