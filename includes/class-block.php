<?php
/**
 * andW News ブロック
 *
 * 新着一覧ブロックの登録とサーバーサイドレンダリング
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Block {

    public function __construct() {
        if (did_action('init')) {
            $this->register_block();
        } else {
            add_action('init', [$this, 'register_block']);
        }
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
    }

    /**
     * ブロックを登録
     */
    public function register_block() {
        $block_json = ANDW_NEWS_PLUGIN_DIR . 'assets/block/block.json';

        if (file_exists($block_json)) {
            register_block_type($block_json, [
                'render_callback' => [$this, 'render'],
            ]);
        }
    }

    /**
     * エディタ用アセットをエンキュー
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'andw-news-block-editor',
            ANDW_NEWS_PLUGIN_URL . 'assets/block/block.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
            ANDW_NEWS_VERSION,
            true
        );

        // カテゴリー一覧をエディタに渡す
        $categories = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ]);

        $cat_options = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $cat_options[] = [
                    'label' => $cat->name,
                    'value' => $cat->term_id,
                ];
            }
        }

        wp_localize_script('andw-news-block-editor', 'andwNewsBlock', [
            'categories' => $cat_options,
        ]);
    }

    /**
     * ブロックをレンダリング
     *
     * @param array $attributes ブロック属性
     * @return string HTML出力
     */
    public function render($attributes) {
        $per_page        = isset($attributes['perPage']) ? absint($attributes['perPage']) : 10;
        $categories      = !empty($attributes['categories']) ? array_map('absint', $attributes['categories']) : [];
        $show_categories = isset($attributes['showCategories']) ? (bool) $attributes['showCategories'] : true;
        $show_tabs       = isset($attributes['showTabs']) ? (bool) $attributes['showTabs'] : false;
        $pinned_first    = isset($attributes['pinnedFirst']) ? (bool) $attributes['pinnedFirst'] : false;

        if ($show_tabs) {
            return $this->render_tabs($per_page, $categories, $show_categories, $pinned_first);
        }

        return $this->render_list($per_page, $categories, $show_categories, $pinned_first);
    }

    /**
     * リスト表示をレンダリング
     */
    private function render_list($per_page, $categories, $show_categories, $pinned_first) {
        $posts = $this->query_posts($per_page, $categories, $pinned_first);

        if (empty($posts)) {
            return '<div class="andw-news-empty">お知らせはありません。</div>';
        }

        $output = '<div class="andw-news-wrapper andw-news-layout-list">';
        $output .= '<div class="andw-news-list">';

        foreach ($posts as $post) {
            $output .= $this->render_item($post, $show_categories);
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * タブ表示をレンダリング
     */
    private function render_tabs($per_page, $categories, $show_categories, $pinned_first) {
        // 「すべて」タブ用の全投稿
        $all_posts = $this->query_posts($per_page, $categories, $pinned_first);

        if (empty($all_posts)) {
            return '<div class="andw-news-empty">お知らせはありません。</div>';
        }

        // カテゴリー別に取得
        $cat_terms = $this->get_tab_categories($categories);
        $categorized = [];

        foreach ($cat_terms as $term) {
            $cat_posts = $this->query_posts($per_page, [$term->term_id], $pinned_first);
            if (!empty($cat_posts)) {
                $categorized[] = [
                    'term'  => $term,
                    'posts' => $cat_posts,
                ];
            }
        }

        // タブHTML（tabs.js が期待する構造）
        $output = '<div class="andw-tabs" data-andw-tabs>';

        // ナビゲーション
        $output .= '<ul class="andw-tabs__nav" role="tablist">';
        $output .= '<li class="andw-tabs__nav-item andw-tabs__nav-item--active" role="tab" '
                  . 'aria-controls="andw-tab-all" aria-selected="true" tabindex="0" '
                  . 'data-tab-target="andw-tab-all">すべて</li>';

        foreach ($categorized as $group) {
            $tab_id = 'andw-tab-' . $group['term']->term_id;
            $output .= sprintf(
                '<li class="andw-tabs__nav-item" role="tab" aria-controls="%s" '
                . 'aria-selected="false" tabindex="-1" data-tab-target="%s">%s</li>',
                esc_attr($tab_id),
                esc_attr($tab_id),
                esc_html($group['term']->name)
            );
        }
        $output .= '</ul>';

        // コンテンツペイン
        $output .= '<div class="andw-tabs__content">';

        // 「すべて」ペイン
        $output .= '<div class="andw-tabs__pane andw-tabs__pane--active" id="andw-tab-all" role="tabpanel" aria-hidden="false">';
        $output .= '<div class="andw-news-list">';
        foreach ($all_posts as $post) {
            $output .= $this->render_item($post, $show_categories);
        }
        $output .= '</div></div>';

        // カテゴリー別ペイン
        foreach ($categorized as $group) {
            $tab_id = 'andw-tab-' . $group['term']->term_id;
            $output .= sprintf(
                '<div class="andw-tabs__pane" id="%s" role="tabpanel" aria-hidden="true">',
                esc_attr($tab_id)
            );
            $output .= '<div class="andw-news-list">';
            foreach ($group['posts'] as $post) {
                $output .= $this->render_item($post, $show_categories);
            }
            $output .= '</div></div>';
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * 1件の投稿アイテムHTML
     */
    private function render_item($post, $show_categories) {
        $url     = get_permalink($post->ID); // post_link フィルターが自動適用
        $target  = get_post_meta($post->ID, 'andw_link_target', true);
        $pinned  = get_post_meta($post->ID, 'andw_pinned', true) === '1';

        $target_attr = '';
        if ($target === '_blank') {
            $target_attr = ' target="_blank" rel="noopener noreferrer"';
        }

        $pinned_class = $pinned ? ' andw-news-item--pinned' : '';

        $output = '<div class="andw-news-item' . $pinned_class . '">';

        // メタ行（日付 + カテゴリー 横並び）
        $output .= '<div class="andw-news-meta">';
        $output .= '<span class="andw-news-date">' . esc_html(get_the_date('Y.m.d', $post)) . '</span>';

        if ($show_categories) {
            $cats = get_the_category($post->ID);
            if (!empty($cats)) {
                foreach ($cats as $cat) {
                    $output .= '<span class="andw-category andw-category-' . esc_attr($cat->slug) . '">'
                              . esc_html($cat->name) . '</span>';
                }
            }
        }
        $output .= '</div>';

        // タイトル行
        $output .= '<div class="andw-news-title">';
        $output .= '<a href="' . esc_url($url) . '"' . $target_attr . '>'
                  . esc_html(get_the_title($post)) . '</a>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * 投稿をクエリ
     */
    private function query_posts($per_page, $categories, $pinned_first) {
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => $per_page,
            'post_status'    => 'publish',
        ];

        // カテゴリー絞り込み
        if (!empty($categories)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = [
                [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $categories,
                    'operator' => 'IN',
                ],
            ];
        }

        // ピン留め優先ソート
        if ($pinned_first) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'andw_pinned',
                    'value'   => '1',
                    'compare' => '=',
                ],
                [
                    'key'     => 'andw_pinned',
                    'compare' => 'NOT EXISTS',
                ],
            ];
            $args['orderby'] = [
                'meta_value_num' => 'DESC',
                'date'           => 'DESC',
            ];
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            $args['meta_key'] = 'andw_pinned';
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
        }

        $query = new WP_Query($args);

        return $query->posts;
    }

    /**
     * タブに表示するカテゴリーを取得
     */
    private function get_tab_categories($filter_ids = []) {
        $term_args = [
            'taxonomy'   => 'category',
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => true,
        ];

        if (!empty($filter_ids)) {
            $term_args['include'] = $filter_ids;
        }

        $terms = get_terms($term_args);

        if (is_wp_error($terms)) {
            return [];
        }

        return $terms;
    }
}
