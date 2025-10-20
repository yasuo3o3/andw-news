<?php
/**
 * 投稿取得・並び順管理クラス
 *
 * andw-news投稿タイプの取得とSCFフィールドの処理を行う
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Query_Handler {

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 必要に応じてフックを追加
    }

    /**
     * ニュース投稿を取得
     *
     * @param array $args 取得パラメータ
     * @return array 投稿データ配列
     */
    public function get_news_posts($args = []) {
        $defaults = [
            'per_page' => 10,
            'cats' => [],
            'pinned_first' => false,
            'exclude_expired' => false,
            'layout' => 'list'
        ];

        $args = wp_parse_args($args, $defaults);

        // WP_Queryの引数を構築
        $query_args = [
            'post_type' => 'andw-news',
            'posts_per_page' => intval($args['per_page']),
            'post_status' => 'publish',
            'meta_query' => []
        ];

        // カテゴリ指定がある場合
        if (!empty($args['cats']) && is_array($args['cats'])) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'andw_news_category',
                    'field' => 'term_id',
                    'terms' => array_map('intval', $args['cats']),
                    'operator' => 'IN'
                ]
            ];
        }

        // ピン留め優先の場合
        if ($args['pinned_first']) {
            $query_args['meta_key'] = 'andw_news_pinned';
            $query_args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'DESC';
        }

        // 期限切れ除外の場合（今回は簡易実装）
        if ($args['exclude_expired']) {
            // 必要に応じて実装
        }

        $query = new WP_Query($query_args);
        $posts_data = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $posts_data[] = $this->prepare_post_data($post_id);
            }
            wp_reset_postdata();
        }

        return $posts_data;
    }

    /**
     * カテゴリ別ニュース投稿を取得（タブ用）
     *
     * @param array $args 取得パラメータ
     * @return array カテゴリ別の投稿データ
     */
    public function get_news_by_categories($args = []) {
        $categories = get_terms([
            'taxonomy' => 'andw_news_category',
            'hide_empty' => true
        ]);

        // エラーチェック
        if (is_wp_error($categories) || empty($categories)) {
            return [];
        }

        $categorized_posts = [];

        foreach ($categories as $category) {
            $category_args = $args;
            $category_args['cats'] = [$category->term_id];

            $posts = $this->get_news_posts($category_args);

            if (!empty($posts)) {
                $categorized_posts[] = [
                    'category' => $category,
                    'posts' => $posts
                ];
            }
        }

        return $categorized_posts;
    }

    /**
     * 投稿データを準備
     *
     * @param int $post_id 投稿ID
     * @return array 整形された投稿データ
     */
    private function prepare_post_data($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return [];
        }

        // 基本データ
        $data = [
            'id' => $post_id,
            'title' => esc_html(get_the_title($post_id)),
            'date' => get_the_date('Y.m.d', $post_id),
            'excerpt' => $this->get_post_excerpt($post_id),
            'thumbnail' => $this->get_post_thumbnail($post_id),
            'link_url' => $this->get_post_link_url($post_id),
            'link_target' => $this->get_post_link_target($post_id),
            'event_date' => $this->get_event_date($post_id)
        ];

        return $data;
    }

    /**
     * 投稿の抜粋を取得
     *
     * @param int $post_id 投稿ID
     * @return string 抜粋
     */
    private function get_post_excerpt($post_id) {
        $excerpt = get_the_excerpt($post_id);
        if (empty($excerpt)) {
            $content = get_post_field('post_content', $post_id);
            $excerpt = wp_trim_words(strip_tags($content), 20, '...');
        }
        return esc_html($excerpt);
    }

    /**
     * 投稿のサムネイルを取得
     *
     * @param int $post_id 投稿ID
     * @return string サムネイルHTML
     */
    private function get_post_thumbnail($post_id) {
        if (has_post_thumbnail($post_id)) {
            return get_the_post_thumbnail($post_id, 'medium', ['class' => 'andw-news-thumbnail']);
        }
        return '';
    }

    /**
     * 投稿のリンクURLを取得
     *
     * @param int $post_id 投稿ID
     * @return string リンクURL
     */
    private function get_post_link_url($post_id) {
        // SCFフィールドを確認
        $link_type = get_post_meta($post_id, 'andw_link_type', true);

        switch ($link_type) {
            case 'internal':
                $internal_link = get_post_meta($post_id, 'andw_internal_link', true);
                if ($internal_link) {
                    return esc_url(get_permalink($internal_link));
                }
                break;

            case 'external':
                $external_link = get_post_meta($post_id, 'andw_external_link', true);
                if ($external_link) {
                    return esc_url($external_link);
                }
                break;

            default:
                // selfまたは未設定の場合は投稿自体のURL
                return esc_url(get_permalink($post_id));
        }

        return esc_url(get_permalink($post_id));
    }

    /**
     * 投稿のリンクターゲットを取得
     *
     * @param int $post_id 投稿ID
     * @return string リンクターゲット
     */
    private function get_post_link_target($post_id) {
        $link_target = get_post_meta($post_id, 'andw_link_target', true);
        return esc_attr($link_target ?: '_self');
    }

    /**
     * イベント日付を取得
     *
     * @param int $post_id 投稿ID
     * @return string イベント日付HTML
     */
    private function get_event_date($post_id) {
        $event_type = get_post_meta($post_id, 'andw_event_type', true);

        if (empty($event_type) || $event_type === 'none') {
            return '';
        }

        $event_html = '';

        switch ($event_type) {
            case 'single':
                $single_date = get_post_meta($post_id, 'andw_event_single_date', true);
                if ($single_date) {
                    $formatted_date = date('Y.m.d', strtotime($single_date));
                    $event_html = '<span class="andw-event-date">' . esc_html($formatted_date) . '</span>';
                }
                break;

            case 'period':
                $start_date = get_post_meta($post_id, 'andw_event_start_date', true);
                $end_date = get_post_meta($post_id, 'andw_event_end_date', true);
                if ($start_date && $end_date) {
                    $formatted_start = date('Y.m.d', strtotime($start_date));
                    $formatted_end = date('Y.m.d', strtotime($end_date));
                    $event_html = '<span class="andw-event-period">' . esc_html($formatted_start . ' - ' . $formatted_end) . '</span>';
                }
                break;

            case 'free-text':
                $free_text = get_post_meta($post_id, 'andw_event_free_text', true);
                if ($free_text) {
                    $event_html = '<span class="andw-event-text">' . esc_html($free_text) . '</span>';
                }
                break;
        }

        return $event_html;
    }
}