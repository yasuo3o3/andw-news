<?php
/**
 * Plugin Name: andW News Changer
 * Description: カスタム投稿タイプ「andw-news」の記事を様々なテンプレートで表示するプラグイン
 * Version: 0.0.1
 * Author: yasuo3o3
 * Author URI: https://yasuo-o.xyz/
 * License: GPLv2 or later
 * Text Domain: andw-news-changer
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数定義
define('ANDW_NEWS_VERSION', '0.0.1');
define('ANDW_NEWS_PLUGIN_FILE', __FILE__);
define('ANDW_NEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANDW_NEWS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * プラグイン初期化
 */
function andw_news_init() {
    // 必要なファイルを読み込み
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-template-manager.php';
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-query-handler.php';
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-shortcode.php';
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-admin.php';
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-gutenberg-block.php';

    // 各クラスを初期化
    new ANDW_News_Template_Manager();
    new ANDW_News_Query_Handler();
    new ANDW_News_Shortcode();
    new ANDW_News_Admin();
    new ANDW_News_Gutenberg_Block();

    // CSS/JSを登録
    andw_news_register_assets();
}
add_action('init', 'andw_news_init');

/**
 * プラグイン有効化フック
 */
function andw_news_activate() {
    // 権限チェック
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // デフォルトテンプレートを登録
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-template-manager.php';
    $template_manager = new ANDW_News_Template_Manager();
    $template_manager->install_default_templates();

    // フラッシュルールを更新
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'andw_news_activate');

/**
 * プラグイン無効化フック
 */
function andw_news_deactivate() {
    // フラッシュルールを更新
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'andw_news_deactivate');

/**
 * CSS/JSアセットを登録
 */
function andw_news_register_assets() {
    // CSS無効化設定を取得
    $disable_css = get_option('andw_news_disable_css', false);

    if (!$disable_css) {
        // 現在のデフォルトテンプレートを取得
        $default_template = get_option('andw_news_default_template', 'list');

        // テーマのCSSを優先してチェック
        $theme_css_path = get_stylesheet_directory() . '/andw-news-changer/' . $default_template . '.css';
        $theme_css_url = get_stylesheet_directory_uri() . '/andw-news-changer/' . $default_template . '.css';

        if (file_exists($theme_css_path)) {
            // テーマのCSSを使用
            wp_enqueue_style(
                'andw-news-' . $default_template,
                $theme_css_url,
                [],
                filemtime($theme_css_path)
            );
        } else {
            // プラグインのCSSを使用
            $plugin_css_path = ANDW_NEWS_PLUGIN_DIR . 'assets/css/' . $default_template . '.css';
            if (file_exists($plugin_css_path)) {
                wp_enqueue_style(
                    'andw-news-' . $default_template,
                    ANDW_NEWS_PLUGIN_URL . 'assets/css/' . $default_template . '.css',
                    [],
                    filemtime($plugin_css_path)
                );
            }
        }
    }

    // タブ用JavaScript（tabs使用時のみ）
    $should_load_tabs = false;

    // 管理画面では常に読み込み
    if (is_admin()) {
        $should_load_tabs = true;
    }
    // 単一投稿ページでショートコードまたはブロックを使用している場合
    elseif (is_singular()) {
        $post_id = get_the_ID();
        if ($post_id) {
            $content = get_post_field('post_content', $post_id);
            if ($content) {
                $should_load_tabs = has_block('andw-news-changer/news-list', $content) ||
                                  has_shortcode($content, 'andw_news');
            }
        }
    }

    if ($should_load_tabs) {
        wp_enqueue_script(
            'andw-news-tabs',
            ANDW_NEWS_PLUGIN_URL . 'assets/js/tabs.js',
            [],
            ANDW_NEWS_VERSION,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'andw_news_register_assets');

/**
 * 管理画面用アセットを登録
 */
function andw_news_admin_assets($hook) {
    // 管理画面ページでのみ読み込み
    if ($hook !== 'toplevel_page_andw-news-changer') {
        return;
    }

    wp_enqueue_script(
        'andw-news-admin',
        ANDW_NEWS_PLUGIN_URL . 'assets/js/admin.js',
        ['jquery'],
        ANDW_NEWS_VERSION,
        true
    );

    wp_localize_script('andw-news-admin', 'andwNewsAdmin', [
        'nonce' => wp_create_nonce('andw_news_admin'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
}
add_action('admin_enqueue_scripts', 'andw_news_admin_assets');