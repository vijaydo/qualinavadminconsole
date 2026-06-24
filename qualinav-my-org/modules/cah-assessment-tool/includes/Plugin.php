<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Plugin {
  public static function init(): void {
    require_once CAH_ASSESS_PATH . 'includes/Storage.php';
    require_once CAH_ASSESS_PATH . 'includes/Registry.php';
    require_once CAH_ASSESS_PATH . 'includes/Scoring.php';
    require_once CAH_ASSESS_PATH . 'includes/Assets.php';
    require_once CAH_ASSESS_PATH . 'includes/Shortcodes.php';
    require_once CAH_ASSESS_PATH . 'includes/Rest.php';
    require_once CAH_ASSESS_PATH . 'includes/Admin.php';

    Storage::init();
    Registry::init();
    Assets::init();
    Shortcodes::init();
    Rest::init();
    Admin::init();
  }

  public static function activate(): void {
    require_once CAH_ASSESS_PATH . 'includes/Storage.php';
    Storage::create_tables();
    update_option('cah_assess_db_version', Storage::DB_VERSION);
  }
}
