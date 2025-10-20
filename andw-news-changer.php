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

    // 翻訳ファイルを読み込み
    load_plugin_textdomain('andw-news-changer', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // 各クラスを初期化
    new ANDW_News_Template_Manager();
    new ANDW_News_Query_Handler();
    new ANDW_News_Shortcode();
    new ANDW_News_Admin();
    new ANDW_News_Gutenberg_Block();
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
    // フロントエンドでの判定
    else {
        // 単一投稿ページの場合
        if (is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id) {
                $content = get_post_field('post_content', $post_id);
                if ($content) {
                    $should_load_tabs = has_block('andw-news-changer/news-list', $content) ||
                                      has_shortcode($content, 'andw_news');
                }
            }
        }

        // まだ見つからない場合はグローバル検索（アーカイブページ等）
        if (!$should_load_tabs) {
            global $wp_query;
            if (isset($wp_query->posts) && is_array($wp_query->posts)) {
                foreach ($wp_query->posts as $post) {
                    // ブロックチェック：投稿内容を直接渡す
                    if (has_block('andw-news-changer/news-list', $post->post_content)) {
                        $should_load_tabs = true;
                        break;
                    }

                    // parse_blocks()による追加チェック（より確実）
                    if (!$should_load_tabs && function_exists('parse_blocks')) {
                        $blocks = parse_blocks($post->post_content);
                        foreach ($blocks as $block) {
                            if ($block['blockName'] === 'andw-news-changer/news-list') {
                                $should_load_tabs = true;
                                break 2; // 外側のループも抜ける
                            }
                        }
                    }

                    // ショートコードチェック
                    if (!$should_load_tabs && function_exists('has_shortcode') && has_shortcode($post->post_content, 'andw_news')) {
                        $should_load_tabs = true;
                        break;
                    }
                }
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
    if ($hook !== 'settings_page_andw-news-changer') {
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