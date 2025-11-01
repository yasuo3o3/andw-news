<?php
/**
 * Gutenbergブロック処理クラス
 *
 * andW News List ブロックの登録と処理を行う
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Gutenberg_Block {

    /**
     * コンストラクタ
     */
    public function __construct() {
        if (did_action('init')) {
            $this->register_block();
        } else {
            add_action('init', [$this, 'register_block']);
        }

        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
    }

    /**
     * ブロックを登録
     */
    public function register_block() {
        // block.jsonが存在する場合はそれを使用、なければコードで登録
        $block_json_path = ANDW_NEWS_PLUGIN_DIR . 'assets/block/block.json';

        if (file_exists($block_json_path)) {
            register_block_type($block_json_path, [
                'render_callback' => [$this, 'render_block']
            ]);
        } else {
            register_block_type('andw-news-changer/news-list', [
                'attributes' => [
                    'layout' => [
                        'type' => 'string',
                        'default' => ''
                    ],
                    'categories' => [
                        'type' => 'array',
                        'default' => []
                    ],
                    'perPage' => [
                        'type' => 'number',
                        'default' => 10
                    ],
                    'pinnedFirst' => [
                        'type' => 'boolean',
                        'default' => false
                    ],
                    'excludeExpired' => [
                        'type' => 'boolean',
                        'default' => false
                    ]
                ],
                'render_callback' => [$this, 'render_block']
            ]);
        }
    }

    /**
     * ブロックエディタ用アセットを読み込み
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'andw-news-block',
            ANDW_NEWS_PLUGIN_URL . 'assets/block/block.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            ANDW_NEWS_VERSION,
            true
        );

        // ブロック用の設定データを渡す
        wp_localize_script('andw-news-block', 'andwNewsBlock', [
            'templates' => $this->get_templates_for_block(),
            'categories' => $this->get_categories_for_block()
        ]);
    }

    /**
     * ブロックをレンダリング
     *
     * @param array $attributes ブロック属性
     * @return string レンダリング結果
     */
    public function render_block($attributes) {
        $shortcode_attrs = [
            'layout' => $attributes['layout'] ?? '',
            'cats' => !empty($attributes['categories']) ? implode(',', $attributes['categories']) : '',
            'per_page' => $attributes['perPage'] ?? 10,
            'pinned_first' => !empty($attributes['pinnedFirst']) ? '1' : '0',
            'exclude_expired' => !empty($attributes['excludeExpired']) ? '1' : '0'
        ];

        // ショートコードクラスを使用してレンダリング
        $shortcode = new ANDW_News_Shortcode();
        return $shortcode->render_shortcode($shortcode_attrs);
    }

    /**
     * ブロック用テンプレート一覧を取得
     *
     * @return array テンプレート一覧
     */
    private function get_templates_for_block() {
        $template_manager = new ANDW_News_Template_Manager();
        $templates = $template_manager->get_templates();

        $block_templates = [];
        foreach ($templates as $key => $template) {
            $block_templates[] = [
                'value' => $key,
                'label' => $template['name']
            ];
        }

        // タブレイアウトを追加
        $block_templates[] = [
            'value' => 'tabs_by_category',
            'label' => 'カテゴリタブ'
        ];

        return $block_templates;
    }

    /**
     * ブロック用カテゴリ一覧を取得
     *
     * @return array カテゴリ一覧
     */
    private function get_categories_for_block() {
        $categories = get_terms([
            'taxonomy' => 'andw_news_category',
            'hide_empty' => false
        ]);

        $block_categories = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $block_categories[] = [
                    'value' => $category->term_id,
                    'label' => $category->name
                ];
            }
        }

        return $block_categories;
    }
}
