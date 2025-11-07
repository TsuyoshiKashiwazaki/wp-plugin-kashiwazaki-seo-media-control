<?php
/**
 * Plugin Name: Kashiwazaki SEO Media Control
 * Plugin URI: https://tsuyoshikashiwazaki.jp/
 * Description: WordPressメディアライブラリの詳細管理と所有者変更機能を提供するプラグインです。
 * Version: 1.0.0
 * Author: 柏崎剛
 * Author URI: https://tsuyoshikashiwazaki.jp/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-media-control
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KSMC_VERSION', '1.0.0');
define('KSMC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSMC_PLUGIN_PATH', plugin_dir_path(__FILE__));

class Kashiwazaki_SEO_Media_Control {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        load_plugin_textdomain('kashiwazaki-seo-media-control', false, dirname(plugin_basename(__FILE__)) . '/languages');

        if (is_admin()) {
            $this->load_admin_classes();
        }
    }

    private function load_admin_classes() {
        require_once KSMC_PLUGIN_PATH . 'includes/class-admin.php';
        require_once KSMC_PLUGIN_PATH . 'includes/class-media-manager.php';
        require_once KSMC_PLUGIN_PATH . 'includes/class-bulk-actions.php';

        new KSMC_Admin();
        KSMC_Media_Manager::get_instance();
        new KSMC_Bulk_Actions();
    }

    public function activate() {
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('このプラグインはWordPress 5.0以上が必要です。', 'kashiwazaki-seo-media-control'));
        }
    }

    public function deactivate() {

    }
}

Kashiwazaki_SEO_Media_Control::get_instance();
