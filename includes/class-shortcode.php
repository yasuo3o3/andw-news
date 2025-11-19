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
     * 使用されたテンプレートのCSS出力待ちリスト
     *
     * @var array
     */
    private static $pending_css_templates = [];

    /**
     * CSS出力フックが設定済みかどうか
     *
     * @var bool
     */
    private static $footer_hook_added = false;

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

        // 使用するテンプレートをCSS出力待ちリストに追加（キャッシュヒット時でも実行される）
        $this->schedule_template_css_output($layout);

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
        $this->add_cache_key($cache_key);

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

        // テンプレートのスラグを取得
        $template_slug = $template_manager->get_template_slug($layout);

        // レイアウトクラスでラップ（スラグベース）
        $output = '<div class="andw-news-wrapper andw-news-layout-' . esc_attr($layout) . ' andw-news--' . esc_attr($template_slug) . '">';
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
        $all_posts = $query_handler->get_all_news_for_tabs($args);

        if (empty($all_posts)) {
            return '<div class="andw-news-empty">' . esc_html__('お知らせはありません。', 'andw-news') . '</div>';
        }

        $template_manager = new ANDW_News_Template_Manager();
        $tab_template = $template_manager->get_template('tabs');

        if (!$tab_template) {
            return '<div class="andw-news-error">' . esc_html__('タブテンプレートが見つかりません。', 'andw-news') . '</div>';
        }

        // タブテンプレートをCSS出力待ちリストに追加
        $this->schedule_template_css_output('tabs');

        // タブテンプレートのスラグを取得
        $tabs_slug = $template_manager->get_template_slug('tabs');

        $output = '<div class="andw-tabs andw-news--' . esc_attr($tabs_slug) . '" data-andw-tabs>';

        // タブナビゲーション
        $output .= '<ul class="andw-tabs__nav" role="tablist">';

        // 「すべて」タブ
        $output .= sprintf(
            '<li class="andw-tabs__nav-item andw-tabs__nav-item--active" role="tab" aria-controls="andw-tab-all" aria-selected="true" tabindex="0" data-tab-target="andw-tab-all">%s</li>',
            esc_html__('すべて', 'andw-news')
        );

        // カテゴリータブ
        foreach ($categorized_posts as $category_data) {
            $tab_id = 'andw-tab-' . $category_data['category']->term_id;

            $output .= sprintf(
                '<li class="andw-tabs__nav-item" role="tab" aria-controls="%s" aria-selected="false" tabindex="-1" data-tab-target="%s">%s</li>',
                esc_attr($tab_id),
                esc_attr($tab_id),
                esc_html($category_data['category']->name)
            );
        }
        $output .= '</ul>';

        // タブコンテンツ
        $output .= '<div class="andw-tabs__content">';

        // 「すべて」タブのコンテンツ
        $output .= '<div class="andw-tabs__pane andw-tabs__pane--active" id="andw-tab-all" role="tabpanel" aria-hidden="false">';
        $all_content = $template_manager->render_multiple_posts($all_posts, $tab_template);
        $output .= $all_content;
        $output .= '</div>';

        // カテゴリータブのコンテンツ
        foreach ($categorized_posts as $category_data) {
            $tab_id = 'andw-tab-' . $category_data['category']->term_id;

            $output .= sprintf(
                '<div class="andw-tabs__pane" id="%s" role="tabpanel" aria-hidden="true">',
                esc_attr($tab_id)
            );

            $category_content = $template_manager->render_multiple_posts($category_data['posts'], $tab_template);
            $output .= $category_content;

            $output .= '</div>';
        }
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * キャッシュキーをリストに追加
     *
     * @param string $cache_key キャッシュキー
     */
    private function add_cache_key($cache_key) {
        $cache_keys = get_option('andw_news_cache_keys', []);
        if (!in_array($cache_key, $cache_keys, true)) {
            $cache_keys[] = sanitize_key($cache_key);
            // 重複削除と空値除去
            $cache_keys = array_unique(array_filter($cache_keys));
            update_option('andw_news_cache_keys', $cache_keys);
        }
    }

    /**
     * テンプレートのCSS出力をスケジュール
     *
     * @param string $template_name テンプレート名
     */
    private function schedule_template_css_output($template_name) {
        // CSS無効化設定を確認
        $disable_css = get_option('andw_news_disable_css', false);
        if ($disable_css) {
            return;
        }

        // 重複を避けて待ちリストに追加
        if (!in_array($template_name, self::$pending_css_templates, true)) {
            self::$pending_css_templates[] = $template_name;
        }

        // wp_footerフックを一度だけ設定
        if (!self::$footer_hook_added) {
            add_action('wp_footer', [$this, 'output_template_css'], 20);
            self::$footer_hook_added = true;
        }
    }

    /**
     * wp_footerでテンプレート用CSSを出力
     */
    public function output_template_css() {
        if (empty(self::$pending_css_templates)) {
            return;
        }

        echo "\n<!-- andW News Template CSS -->\n<style id=\"andw-news-template-css\">\n";

        foreach (self::$pending_css_templates as $template_name) {
            $this->output_single_template_css($template_name);
        }

        echo "</style>\n<!-- /andW News Template CSS -->\n";

        // 出力済みリストをクリア
        self::$pending_css_templates = [];
    }

    /**
     * 単一テンプレートのCSS（ベース＋カスタム）を出力
     *
     * @param string $template_name テンプレート名
     */
    private function output_single_template_css($template_name) {
        // ベースCSSを出力
        $this->output_base_template_css($template_name);

        // カスタムCSSを出力
        $this->output_custom_template_css($template_name);
    }

    /**
     * ベーステンプレートCSSを出力
     *
     * @param string $template_name テンプレート名
     */
    private function output_base_template_css($template_name) {
        $css_content = '';

        // テーマのCSSを優先してチェック
        $theme_css_path = get_stylesheet_directory() . '/andw-news-changer/' . $template_name . '.css';
        if (file_exists($theme_css_path)) {
            $css_content = file_get_contents($theme_css_path);
        } else {
            // プラグインのCSSを使用
            $plugin_css_path = ANDW_NEWS_PLUGIN_DIR . 'assets/css/' . $template_name . '.css';
            if (file_exists($plugin_css_path)) {
                $css_content = file_get_contents($plugin_css_path);
            }
        }

        if (!empty($css_content)) {
            echo "/* Base CSS for template: {$template_name} */\n";
            echo wp_strip_all_tags($css_content);
            echo "\n\n";
        }
    }

    /**
     * カスタムテンプレートCSSを出力
     *
     * @param string $template_name テンプレート名
     */
    private function output_custom_template_css($template_name) {
        $template_manager = new ANDW_News_Template_Manager();
        $templates = $template_manager->get_templates();

        if (isset($templates[$template_name]['css']) && !empty($templates[$template_name]['css'])) {
            echo "/* Custom CSS for template: {$template_name} */\n";
            echo wp_strip_all_tags($templates[$template_name]['css']);
            echo "\n\n";
        }
    }
}