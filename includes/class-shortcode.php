<?php
/**
 * ショートコード処理クラス
 *
 * [andw_news] ショートコードの登録と処理を行う
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Shortcode {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_shortcode('andw_news', [$this, 'render_shortcode']);
    }

    /**
     * ショートコードをレンダリング
     *
     * @param array $atts ショートコード属性
     * @return string レンダリング結果
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'layout' => '',
            'cats' => '',
            'per_page' => 10,
            'pinned_first' => '0',
            'exclude_expired' => '0'
        ], $atts, 'andw_news');

        // 属性を処理
        $layout = sanitize_text_field($atts['layout']);
        $cats = !empty($atts['cats']) ? array_map('intval', explode(',', $atts['cats'])) : [];
        $per_page = intval($atts['per_page']);
        $pinned_first = $atts['pinned_first'] === '1';
        $exclude_expired = $atts['exclude_expired'] === '1';

        // レイアウトが未指定の場合はデフォルトを使用
        if (empty($layout)) {
            $template_manager = new ANDW_News_Template_Manager();
            $layout = $template_manager->get_default_template();
        }

        // キャッシュキーを生成
        $cache_key = 'andw_news_' . md5(serialize($atts));
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // 投稿データを取得
        $query_handler = new ANDW_News_Query_Handler();

        if ($layout === 'tabs_by_category') {
            $result = $this->render_tabs_layout($query_handler, [
                'per_page' => $per_page,
                'pinned_first' => $pinned_first,
                'exclude_expired' => $exclude_expired
            ]);
        } else {
            $posts = $query_handler->get_news_posts([
                'per_page' => $per_page,
                'cats' => $cats,
                'pinned_first' => $pinned_first,
                'exclude_expired' => $exclude_expired,
                'layout' => $layout
            ]);

            $result = $this->render_posts($posts, $layout);
        }

        // 結果をキャッシュ（15分）
        set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * 投稿一覧をレンダリング
     *
     * @param array $posts 投稿データ配列
     * @param string $layout レイアウト名
     * @return string レンダリング結果
     */
    private function render_posts($posts, $layout) {
        if (empty($posts)) {
            return '<div class="andw-news-empty">' . esc_html__('お知らせはありません。', 'andw-news') . '</div>';
        }

        $template_manager = new ANDW_News_Template_Manager();
        $template = $template_manager->get_template($layout);

        if (!$template) {
            return '<div class="andw-news-error">' . esc_html__('テンプレートが見つかりません。', 'andw-news') . '</div>';
        }

        // 新しいテンプレートシステムを使用してレンダリング
        $rendered_content = $template_manager->render_multiple_posts($posts, $template);

        // レイアウトクラスでラップ
        $output = '<div class="andw-news-wrapper andw-news-layout-' . esc_attr($layout) . '">';
        $output .= $rendered_content;
        $output .= '</div>';

        return $output;
    }

    /**
     * タブレイアウトをレンダリング
     *
     * @param ANDW_News_Query_Handler $query_handler クエリハンドラー
     * @param array $args 引数
     * @return string レンダリング結果
     */
    private function render_tabs_layout($query_handler, $args) {
        $categorized_posts = $query_handler->get_news_by_categories($args);

        if (empty($categorized_posts)) {
            return '<div class="andw-news-empty">' . esc_html__('お知らせはありません。', 'andw-news') . '</div>';
        }

        $template_manager = new ANDW_News_Template_Manager();
        $tab_template = $template_manager->get_template('tabs');

        if (!$tab_template) {
            return '<div class="andw-news-error">' . esc_html__('タブテンプレートが見つかりません。', 'andw-news') . '</div>';
        }

        $output = '<div class="andw-tabs" data-andw-tabs>';

        // タブナビゲーション
        $output .= '<ul class="andw-tabs__nav" role="tablist">';
        $first_tab = true;
        foreach ($categorized_posts as $index => $category_data) {
            $tab_id = 'andw-tab-' . $category_data['category']->term_id;
            $active_class = $first_tab ? ' andw-tabs__nav-item--active' : '';
            $aria_selected = $first_tab ? 'true' : 'false';

            $output .= sprintf(
                '<li class="andw-tabs__nav-item%s" role="tab" aria-controls="%s" aria-selected="%s" tabindex="%s" data-tab-target="%s">%s</li>',
                esc_attr($active_class),
                esc_attr($tab_id),
                esc_attr($aria_selected),
                $first_tab ? '0' : '-1',
                esc_attr($tab_id),
                esc_html($category_data['category']->name)
            );

            $first_tab = false;
        }
        $output .= '</ul>';

        // タブコンテンツ
        $output .= '<div class="andw-tabs__content">';
        $first_tab = true;
        foreach ($categorized_posts as $category_data) {
            $tab_id = 'andw-tab-' . $category_data['category']->term_id;
            $active_class = $first_tab ? ' andw-tabs__pane--active' : '';

            $output .= sprintf(
                '<div class="andw-tabs__pane%s" id="%s" role="tabpanel" aria-hidden="%s">',
                esc_attr($active_class),
                esc_attr($tab_id),
                $first_tab ? 'false' : 'true'
            );

            // 新しいテンプレートシステムを使用してカテゴリ内の投稿をレンダリング
            $category_content = $template_manager->render_multiple_posts($category_data['posts'], $tab_template);
            $output .= $category_content;

            $output .= '</div>';
            $first_tab = false;
        }
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}