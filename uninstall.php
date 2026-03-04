<?php
/**
 * andW News アンインストール処理
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function andw_news_uninstall_cleanup() {
    global $wpdb;

    // v2 メタデータを削除
    $meta_keys = ['andw_link_url', 'andw_link_target', 'andw_pinned'];
    foreach ($meta_keys as $key) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete($wpdb->postmeta, ['meta_key' => $key]);
    }

    // v1 のオプションが残っていれば削除
    $options = [
        'andw_news_templates',
        'andw_news_default_template',
        'andw_news_disable_css',
        'andw_news_default_thumbnail',
        'andw_news_cache_keys',
    ];

    foreach ($options as $option) {
        delete_option($option);
        delete_site_option($option);
    }

    // Transient の削除
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_andw_news_%',
        '_transient_timeout_andw_news_%'
    ));

    if (is_multisite()) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            '_site_transient_andw_news_%',
            '_site_transient_timeout_andw_news_%'
        ));
    }

    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('andw_news');
    }
}

andw_news_uninstall_cleanup();
