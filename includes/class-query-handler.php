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
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for conditional meta filtering
            'meta_query' => []
        ];

        // カテゴリ指定がある場合
        if (!empty($args['cats']) && is_array($args['cats'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category filtering
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
            // ピン留めフィールドでソート（ピン留め投稿を優先、その後日付順）
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- needs meta fallback for pinned order
            $query_args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => 'andw-news-pinned',
                    'value' => '1',
                    'compare' => '='
                ],
                [
                    'key' => 'andw-news-pinned',
                    'compare' => 'NOT EXISTS'
                ]
            ];
            $query_args['orderby'] = [
                'meta_value_num' => 'DESC',
                'date' => 'DESC'
            ];
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for pinned post ordering
            $query_args['meta_key'] = 'andw-news-pinned';
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
            'orderby' => 'term_order',
            'order' => 'ASC',
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
     * 「すべて」タブ用の全投稿を取得
     *
     * @param array $args 取得パラメータ
     * @return array 投稿データ配列
     */
    public function get_all_news_for_tabs($args = []) {
        return $this->get_news_posts($args);
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
            'date' => get_the_date('Y.m.d', $post_id), // 後方互換性のため維持
            'date_raw' => get_the_date('Y-m-d', $post_id), // 日付フォーマット用の生データ
            'excerpt' => $this->get_post_excerpt($post_id),
            'thumbnail' => $this->get_post_thumbnail($post_id),
            'link_url' => $this->get_post_link_url($post_id),
            'link_target' => $this->get_post_link_target($post_id),
            'event_date' => $this->get_event_date($post_id),
            'pinned' => $this->is_post_pinned($post_id),
            'categories' => $this->get_post_categories($post_id)
        ];

        // SCFフィールドを動的に追加
        $data = array_merge($data, $this->get_custom_fields($post_id));

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
            $excerpt = wp_trim_words(wp_strip_all_tags($content), 20, '...');
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

        // デフォルトサムネイルを取得
        $default_thumbnail_id = get_option('andw_news_default_thumbnail', 0);
        if ($default_thumbnail_id && wp_attachment_is_image($default_thumbnail_id)) {
            return wp_get_attachment_image($default_thumbnail_id, 'medium', false, ['class' => 'andw-news-thumbnail default']);
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
        $link_type = get_post_meta($post_id, 'andw-link-type', true);


        switch ($link_type) {
            case 'internal':
                $internal_link = get_post_meta($post_id, 'andw-internal-link', true);


                if (!empty($internal_link) && is_numeric($internal_link)) {
                    $internal_url = get_permalink($internal_link);


                    // 有効なURLが取得できた場合のみ返す
                    if ($internal_url && $internal_url !== false) {
                        return esc_url($internal_url);
                    }
                }

                // 内部リンクが無効な場合は自身のページへのフォールバック
                break;

            case 'external':
                $external_link = get_post_meta($post_id, 'andw-external-link', true);


                if (!empty($external_link)) {
                    return esc_url($external_link);
                }
                break;

            case 'self':
            default:
                // selfまたは未設定の場合は投稿自体のURL
                break;
        }

        $self_url = get_permalink($post_id);


        return esc_url($self_url);
    }

    /**
     * 投稿のリンクターゲットを取得
     *
     * @param int $post_id 投稿ID
     * @return string リンクターゲット
     */
    private function get_post_link_target($post_id) {
        $link_target = get_post_meta($post_id, 'andw-link-target', true);
        return esc_attr($link_target ?: '_self');
    }

    /**
     * イベント日付を取得
     *
     * @param int $post_id 投稿ID
     * @return string イベント日付HTML
     */
    private function get_event_date($post_id) {
        $event_type = get_post_meta($post_id, 'andw-event-type', true);

        if (empty($event_type) || $event_type === 'none') {
            return '';
        }

        $event_html = '';

        switch ($event_type) {
            case 'single':
                $single_date = get_post_meta($post_id, 'andw-event-single-date', true);
                if ($single_date) {
                    $formatted_date = wp_date('Y.m.d', strtotime($single_date));
                    $event_html = '<span class="andw-event-date">' . esc_html($formatted_date) . '</span>';
                }
                break;

            case 'period':
                $start_date = get_post_meta($post_id, 'andw-event-start-date', true);
                $end_date = get_post_meta($post_id, 'andw-event-end-date', true);
                if ($start_date && $end_date) {
                    $formatted_start = wp_date('Y.m.d', strtotime($start_date));
                    $formatted_end = wp_date('Y.m.d', strtotime($end_date));
                    $event_html = '<span class="andw-event-period">' . esc_html($formatted_start . ' - ' . $formatted_end) . '</span>';
                }
                break;

            case 'free-text':
                $free_text = get_post_meta($post_id, 'andw-event-free-text', true);
                if ($free_text) {
                    $event_html = '<span class="andw-event-text">' . esc_html($free_text) . '</span>';
                }
                break;
        }

        return $event_html;
    }

    /**
     * カスタムフィールド（SCF）を動的に取得
     *
     * @param int $post_id 投稿ID
     * @return array カスタムフィールドの配列
     */
    private function get_custom_fields($post_id) {
        $custom_fields = [];


        // 明示的にチェックするandwフィールドのリスト
        $expected_fields = [
            'andw-news-pinned',
            'andw-link-type',
            'andw-internal-link',
            'andw-external-link',
            'andw-link-target',
            'andw-target-blank',
            'andw-event-type',
            'andw-event-single-date',
            'andw-event-start-date',
            'andw-event-end-date',
            'andw-event-free-text',
            'andw-subcontents'
        ];

        // 明示的フィールドを最初に処理
        foreach ($expected_fields as $field_key) {
            $field_value = get_post_meta($post_id, $field_key, true);

            // SCFプラグインが有効な場合は SCF::get() も試す
            if (empty($field_value) && class_exists('SCF') && method_exists('SCF', 'get')) {
                $scf_value = SCF::get($field_key, $post_id);
                if (!empty($scf_value)) {
                    $field_value = $scf_value;
                }
            }

            // HTMLエスケープ処理（ただし、URLや一部フィールドは除外）
            $no_escape_fields = ['andw-external-link', 'andw-internal-link'];
            if (in_array($field_key, $no_escape_fields)) {
                $custom_fields[$field_key] = $field_value;
            } else {
                $custom_fields[$field_key] = !empty($field_value) ? esc_html($field_value) : '';
            }
        }

        // その他のandwプレフィックスフィールドも取得
        $all_meta = get_post_meta($post_id);
        if (is_array($all_meta)) {
            foreach ($all_meta as $meta_key => $meta_values) {
                // 未処理のandwプレフィックスフィールドのみ処理
                if ((strpos($meta_key, 'andw_') === 0 || strpos($meta_key, 'andw-') === 0)
                    && !isset($custom_fields[$meta_key])) {

                    $field_value = isset($meta_values[0]) ? $meta_values[0] : '';
                    $custom_fields[$meta_key] = !empty($field_value) ? esc_html($field_value) : '';
                }
            }
        }

        return $custom_fields;
    }

    /**
     * 投稿がピン留めされているかチェック
     *
     * @param int $post_id 投稿ID
     * @return bool ピン留め状態
     */
    private function is_post_pinned($post_id) {
        $pinned = get_post_meta($post_id, 'andw-news-pinned', true);
        return $pinned === '1' || $pinned === 1;
    }

    /**
     * 投稿のカテゴリーを取得
     *
     * @param int $post_id 投稿ID
     * @return string カテゴリーHTML
     */
    private function get_post_categories($post_id) {
        $categories = get_terms([
            'taxonomy' => 'andw_news_category',
            'object_ids' => $post_id,
            'orderby' => 'term_order',
            'order' => 'ASC',
            'hide_empty' => false
        ]);

        $categories_html = '';

        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $categories_html .= '<span class="andw-category andw-category-' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</span>';
            }
        }

        return $categories_html;
    }
}