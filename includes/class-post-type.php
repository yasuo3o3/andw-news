<?php
/**
 * カスタム投稿タイプ・タクソノミー登録クラス
 *
 * andw-news投稿タイプとandw_news_categoryタクソノミーの登録を行う
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Post_Type {

    /**
     * コンストラクタ
     */
    public function __construct() {
        if (did_action('init')) {
            $this->register_post_type();
            $this->register_taxonomy();
        } else {
            add_action('init', [$this, 'register_post_type']);
            add_action('init', [$this, 'register_taxonomy']);
        }
    }

    /**
     * カスタム投稿タイプを登録
     */
    public function register_post_type() {
        $labels = [
            'name' => __('お知らせ', 'andw-news'),
            'singular_name' => __('お知らせ', 'andw-news'),
            'add_new' => __('新規追加', 'andw-news'),
            'add_new_item' => __('新しいお知らせを追加', 'andw-news'),
            'edit_item' => __('お知らせを編集', 'andw-news'),
            'new_item' => __('新しいお知らせ', 'andw-news'),
            'view_item' => __('お知らせを表示', 'andw-news'),
            'search_items' => __('お知らせを検索', 'andw-news'),
            'not_found' => __('お知らせが見つかりませんでした', 'andw-news'),
            'not_found_in_trash' => __('ゴミ箱にお知らせはありません', 'andw-news'),
            'menu_name' => __('お知らせ', 'andw-news')
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true, // Gutenbergエディター対応
            'query_var' => true,
            'rewrite' => ['slug' => 'news'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-megaphone',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'taxonomies' => ['andw_news_category']
        ];

        register_post_type('andw-news', $args);
    }

    /**
     * カスタムタクソノミーを登録
     */
    public function register_taxonomy() {
        $labels = [
            'name' => __('お知らせカテゴリ', 'andw-news'),
            'singular_name' => __('カテゴリ', 'andw-news'),
            'search_items' => __('カテゴリを検索', 'andw-news'),
            'all_items' => __('すべてのカテゴリ', 'andw-news'),
            'parent_item' => __('親カテゴリ', 'andw-news'),
            'parent_item_colon' => __('親カテゴリ:', 'andw-news'),
            'edit_item' => __('カテゴリを編集', 'andw-news'),
            'update_item' => __('カテゴリを更新', 'andw-news'),
            'add_new_item' => __('新しいカテゴリを追加', 'andw-news'),
            'new_item_name' => __('新しいカテゴリ名', 'andw-news'),
            'menu_name' => __('カテゴリ', 'andw-news')
        ];

        $args = [
            'labels' => $labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true, // Gutenbergエディター対応
            'rewrite' => ['slug' => 'news-category']
        ];

        register_taxonomy('andw_news_category', 'andw-news', $args);
    }
}
