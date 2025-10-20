<?php
/**
 * andW News Changer アンインストール処理
 *
 * プラグイン削除時にのみ実行される
 */

// 直接アクセスと不正な呼び出しを防ぐ
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * プラグインデータを削除
 */
function andw_news_uninstall_cleanup() {
    // プラグインが作成したオプションを削除
    $options_to_delete = [
        'andw_news_templates',
        'andw_news_default_template',
        'andw_news_disable_css'
    ];

    foreach ($options_to_delete as $option) {
        delete_option($option);
        delete_site_option($option); // マルチサイト対応
    }

    // プラグイン接頭辞のTransientsを削除
    global $wpdb;

    // andw_news_ で始まるTransientsを削除
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_andw_news_%',
        '_transient_timeout_andw_news_%'
    ));

    // マルチサイトの場合はsite_optionsも削除
    if (is_multisite()) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            '_site_transient_andw_news_%',
            '_site_transient_timeout_andw_news_%'
        ));
    }

    // オブジェクトキャッシュも削除（可能な場合）
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('andw_news');
    }

    // 個別のキャッシュキーを削除
    $cache_keys = [
        'andw_news_templates',
        'andw_news_default_template'
    ];

    foreach ($cache_keys as $cache_key) {
        wp_cache_delete($cache_key, 'andw_news');
    }
}

// クリーンアップを実行
andw_news_uninstall_cleanup();