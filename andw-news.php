<?php
/**
 * Plugin Name: andW News
 * Description: 投稿のリンク先を変更し、新着一覧ブロックを提供するプラグイン
 * Version: 2.0.0
 * Author: yasuo3o3
 * Author URI: https://yasuo-o.xyz/
 * License: GPLv2 or later
 * Text Domain: andw-news
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ANDW_NEWS_VERSION', '2.0.0');
define('ANDW_NEWS_PLUGIN_FILE', __FILE__);
define('ANDW_NEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANDW_NEWS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * プラグイン初期化
 */
function andw_news_init() {
    require_once ANDW_NEWS_PLUGIN_DIR . 'includes/class-block.php';
    new ANDW_News_Block();

    // メタキーをREST APIに公開
    andw_news_register_meta();
}
add_action('init', 'andw_news_init');

/**
 * カスタムメタフィールドを登録
 */
function andw_news_register_meta() {
    register_post_meta('post', 'andw_link_url', [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
    ]);

    register_post_meta('post', 'andw_link_target', [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'andw_pinned', [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}

/**
 * 投稿のパーマリンクを差し替え
 */
function andw_news_filter_post_link($url, $post) {
    if (is_admin()) {
        return $url;
    }

    $custom_url = get_post_meta($post->ID, 'andw_link_url', true);
    if (!empty($custom_url)) {
        return esc_url($custom_url);
    }

    return $url;
}
add_filter('post_link', 'andw_news_filter_post_link', 10, 2);

/**
 * メタボックスを登録
 */
function andw_news_add_meta_boxes() {
    add_meta_box(
        'andw-news-link',
        'andW News — リンク設定',
        'andw_news_render_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'andw_news_add_meta_boxes');

/**
 * メタボックスを描画
 */
function andw_news_render_meta_box($post) {
    wp_nonce_field('andw_news_meta', 'andw_news_nonce');

    $link_url    = get_post_meta($post->ID, 'andw_link_url', true);
    $link_target = get_post_meta($post->ID, 'andw_link_target', true);
    $pinned      = get_post_meta($post->ID, 'andw_pinned', true);
    ?>
    <p>
        <label for="andw_link_url">リンク先URL</label><br>
        <input type="url" id="andw_link_url" name="andw_link_url"
               value="<?php echo esc_attr($link_url); ?>"
               style="width:100%;" placeholder="空欄なら通常のパーマリンク">
    </p>
    <p>
        <label>
            <input type="checkbox" name="andw_link_target" value="_blank"
                   <?php checked($link_target, '_blank'); ?>>
            新しいタブで開く
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="andw_pinned" value="1"
                   <?php checked($pinned, '1'); ?>>
            ピン留め（一覧の先頭に表示）
        </label>
    </p>
    <?php
}

/**
 * メタボックスの値を保存
 */
function andw_news_save_meta($post_id) {
    if (!isset($_POST['andw_news_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['andw_news_nonce'], 'andw_news_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // リンク先URL
    if (isset($_POST['andw_link_url'])) {
        $url = esc_url_raw(wp_unslash($_POST['andw_link_url']));
        if (!empty($url)) {
            update_post_meta($post_id, 'andw_link_url', $url);
        } else {
            delete_post_meta($post_id, 'andw_link_url');
        }
    }

    // ターゲット
    $target = isset($_POST['andw_link_target']) ? '_blank' : '';
    if (!empty($target)) {
        update_post_meta($post_id, 'andw_link_target', $target);
    } else {
        delete_post_meta($post_id, 'andw_link_target');
    }

    // ピン留め
    $pinned = isset($_POST['andw_pinned']) ? '1' : '';
    if (!empty($pinned)) {
        update_post_meta($post_id, 'andw_pinned', $pinned);
    } else {
        delete_post_meta($post_id, 'andw_pinned');
    }
}
add_action('save_post_post', 'andw_news_save_meta');

/**
 * フロント用CSS/JSをエンキュー
 */
function andw_news_enqueue_assets() {
    // list.css は常に読み込み（ブロック使用時のみが理想だが、軽量なので許容）
    wp_enqueue_style(
        'andw-news-list',
        ANDW_NEWS_PLUGIN_URL . 'assets/css/list.css',
        [],
        ANDW_NEWS_VERSION
    );

    // tabs.css と tabs.js はブロック検出時のみ
    $needs_tabs = false;

    if (is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id) {
            $needs_tabs = has_block('andw-news/latest-posts', $post_id);
        }
    }

    if (!$needs_tabs) {
        global $wp_query;
        if (isset($wp_query->posts) && is_array($wp_query->posts)) {
            foreach ($wp_query->posts as $post) {
                if (has_block('andw-news/latest-posts', $post->post_content)) {
                    $needs_tabs = true;
                    break;
                }
            }
        }
    }

    if ($needs_tabs) {
        wp_enqueue_style(
            'andw-news-tabs',
            ANDW_NEWS_PLUGIN_URL . 'assets/css/tabs.css',
            [],
            ANDW_NEWS_VERSION
        );

        wp_enqueue_script(
            'andw-news-tabs',
            ANDW_NEWS_PLUGIN_URL . 'assets/js/tabs.js',
            [],
            ANDW_NEWS_VERSION,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'andw_news_enqueue_assets');
