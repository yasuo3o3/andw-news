<?php
/**
 * 管理画面処理クラス
 *
 * WordPress管理画面でのテンプレート管理を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Admin {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_andw_news_preview_template', [$this, 'ajax_preview_template']);
        add_action('wp_ajax_andw_news_save_template', [$this, 'ajax_save_template']);
        add_action('wp_ajax_andw_news_get_template', [$this, 'ajax_get_template']);
        add_action('wp_ajax_andw_news_duplicate_template', [$this, 'ajax_duplicate_template']);
        add_action('wp_ajax_andw_news_delete_template', [$this, 'ajax_delete_template']);
        add_action('wp_ajax_andw_news_set_default_template', [$this, 'ajax_set_default_template']);
        add_action('wp_ajax_andw_news_scf_debug', [$this, 'ajax_scf_debug']);
        add_action('wp_ajax_andw_news_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('admin_init', [$this, 'handle_form_submissions']);

        // キャッシュクリア関連のフック
        add_action('save_post', [$this, 'on_post_save']);
        add_action('delete_post', [$this, 'clear_news_cache']);
        add_action('wp_trash_post', [$this, 'on_post_status_change']);
        add_action('untrash_post', [$this, 'on_post_status_change']);
    }

    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        add_options_page(
            __('お知らせ設定', 'andw-news'),
            __('お知らせ設定', 'andw-news'),
            'manage_options',
            'andw-news',
            [$this, 'admin_page']
        );
    }

    /**
     * 管理画面ページを表示
     */
    public function admin_page() {
        // nonceチェック
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('このページにアクセスする権限がありません。', 'andw-news'));
        }

        $template_manager = new ANDW_News_Template_Manager();
        $templates = $template_manager->get_templates();
        $default_template = $template_manager->get_default_template();
        $disable_css = get_option('andw_news_disable_css', false);
        $default_thumbnail_id = get_option('andw_news_default_thumbnail', 0);

        // 重複テンプレート検出
        $duplicates = $this->detect_duplicate_templates($templates);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('お知らせ設定', 'andw-news'); ?></h1>

            <?php $this->show_admin_notices(); ?>
            <?php $this->show_duplicate_warnings($duplicates); ?>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- 左側: テンプレート管理 -->
                <div style="flex: 1;">
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('テンプレート管理', 'andw-news'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template-select"><?php echo esc_html__('テンプレート選択', 'andw-news'); ?></label>
                                </th>
                                <td>
                                    <select id="template-select" class="regular-text">
                                        <option value=""><?php echo esc_html__('選択してください', 'andw-news'); ?></option>
                                        <?php foreach ($templates as $key => $template): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $default_template); ?>>
                                                <?php echo esc_html($template['name']); ?>
                                                <?php if ($key === $default_template): ?>
                                                    (<?php echo esc_html__('デフォルト', 'andw-news'); ?>)
                                                <?php endif; ?>
                                                [<?php echo esc_html($key); ?>]
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="button" id="create-template" class="button button-primary">
                                <?php echo esc_html__('新規作成', 'andw-news'); ?>
                            </button>
                            <button type="button" id="edit-template" class="button">
                                <?php echo esc_html__('編集', 'andw-news'); ?>
                            </button>
                            <button type="button" id="duplicate-template" class="button">
                                <?php echo esc_html__('複製', 'andw-news'); ?>
                            </button>
                            <button type="button" id="delete-template" class="button button-secondary">
                                <?php echo esc_html__('削除', 'andw-news'); ?>
                            </button>
                            <button type="button" id="set-default" class="button">
                                <?php echo esc_html__('デフォルトに設定', 'andw-news'); ?>
                            </button>
                        </p>
                    </div>

                    <!-- CSS設定 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('CSS設定', 'andw-news'); ?></h2>

                        <form method="post" action="">
                            <?php wp_nonce_field('andw_news_css_settings', 'andw_news_css_nonce'); ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('プラグインCSS', 'andw-news'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="disable_css" value="1" <?php checked($disable_css); ?> />
                                            <?php echo esc_html__('プラグインのCSSを無効化する', 'andw-news'); ?>
                                        </label>
                                        <p class="description">
                                            <?php echo esc_html__('チェックすると、プラグインのデフォルトCSSが読み込まれなくなります。', 'andw-news'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <input type="submit" name="save_css_settings" class="button button-primary"
                                       value="<?php echo esc_attr__('CSS設定を保存', 'andw-news'); ?>" />
                            </p>
                        </form>
                    </div>

                    <!-- デフォルトサムネイル設定 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('デフォルトサムネイル設定', 'andw-news'); ?></h2>

                        <form method="post" action="">
                            <?php wp_nonce_field('andw_news_thumbnail_settings', 'andw_news_thumbnail_nonce'); ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('デフォルト画像', 'andw-news'); ?></th>
                                    <td>
                                        <div id="default-thumbnail-preview" style="margin-bottom: 10px;">
                                            <?php if ($default_thumbnail_id): ?>
                                                <?php echo wp_get_attachment_image($default_thumbnail_id, 'medium', false, ['style' => 'max-width: 200px; height: auto;']); ?>
                                            <?php else: ?>
                                                <p class="description"><?php echo esc_html__('デフォルト画像が設定されていません。', 'andw-news'); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <input type="hidden" id="default-thumbnail-id" name="default_thumbnail_id" value="<?php echo esc_attr($default_thumbnail_id); ?>" />

                                        <button type="button" id="select-thumbnail" class="button">
                                            <?php echo esc_html__('画像を選択', 'andw-news'); ?>
                                        </button>

                                        <?php if ($default_thumbnail_id): ?>
                                            <button type="button" id="remove-thumbnail" class="button" style="margin-left: 10px;">
                                                <?php echo esc_html__('画像を削除', 'andw-news'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <p class="description">
                                            <?php echo esc_html__('アイキャッチ画像が設定されていない投稿で使用されるデフォルト画像です。', 'andw-news'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <input type="submit" name="save_thumbnail_settings" class="button button-primary"
                                       value="<?php echo esc_attr__('デフォルトサムネイル設定を保存', 'andw-news'); ?>" />
                            </p>
                        </form>
                    </div>

                    <!-- キャッシュ管理 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('キャッシュ管理', 'andw-news'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('パフォーマンス', 'andw-news'); ?></th>
                                <td>
                                    <p class="description">
                                        <?php echo esc_html__('テンプレートや投稿を変更すると、キャッシュは自動的にクリアされます。', 'andw-news'); ?>
                                    </p>
                                    <button type="button" id="clear-cache" class="button">
                                        <?php echo esc_html__('キャッシュを手動クリア', 'andw-news'); ?>
                                    </button>
                                    <div id="cache-status" style="margin-top: 10px;"></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 右側: プレビュー -->
                <div style="flex: 1;">
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('プレビュー', 'andw-news'); ?></h2>
                        <div id="preview-area" style="border: 1px solid #ddd; padding: 20px; min-height: 300px; background: #f9f9f9;">
                            <p><?php echo esc_html__('テンプレートを選択してください。', 'andw-news'); ?></p>
                        </div>
                    </div>

                    <!-- 日付フォーマット機能の説明 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('日付フォーマット機能', 'andw-news'); ?></h2>
                        <div style="padding: 10px; font-size: 13px; line-height: 1.6;">
                            <p><strong><?php echo esc_html__('基本的な使い方:', 'andw-news'); ?></strong></p>
                            <ul style="margin-left: 20px;">
                                <li><code>{date}</code> - <?php echo esc_html__('デフォルト形式（例：2025.1.31）', 'andw-news'); ?></li>
                                <li><code>{date:jp}</code> - <?php echo esc_html__('日本語形式（例：2025年1月31日）', 'andw-news'); ?></li>
                                <li><code>{date:short}</code> - <?php echo esc_html__('短縮形式（例：1/31）', 'andw-news'); ?></li>
                                <li><code>{event_date:jp}</code> - <?php echo esc_html__('イベント日付の日本語形式', 'andw-news'); ?></li>
                            </ul>

                            <p><strong><?php echo esc_html__('定義済みフォーマット:', 'andw-news'); ?></strong></p>
                            <ul style="margin-left: 20px; font-size: 12px;">
                                <li><code>jp</code> → Y年n月j日（2025年1月31日）</li>
                                <li><code>jp_full</code> → Y年m月d日（2025年01月31日）</li>
                                <li><code>short</code> → n/j（1/31）</li>
                                <li><code>short_full</code> → m/d（01/31）</li>
                                <li><code>iso</code> → Y-m-d（2025-01-31）</li>
                                <li><code>dot</code> → Y.m.d（2025.1.31）</li>
                                <li><code>slash</code> → Y/m/d（2025/1/31）</li>
                                <li><code>w</code> → Y年n月j日(D)（2025年1月31日(金)）</li>
                                <li><code>w_full</code> → Y年m月d日(D)（2025年01月31日(金)）</li>
                            </ul>

                            <p><strong><?php echo esc_html__('カスタムフォーマット:', 'andw-news'); ?></strong></p>
                            <p style="margin-left: 20px; font-size: 12px;">
                                <?php echo esc_html__('PHPの日付フォーマット文字列も直接使用可能', 'andw-news'); ?><br>
                                <code>{date:Y年n月j日}</code> → 2025年1月31日<br>
                                <code>{date:m/d/Y}</code> → 01/31/2025
                            </p>

                            <details style="margin-top: 15px;">
                                <summary style="cursor: pointer; font-weight: bold;">
                                    <?php echo esc_html__('使用例を表示', 'andw-news'); ?>
                                </summary>
                                <div style="background: #f5f5f5; padding: 10px; margin-top: 10px; border-radius: 4px;">
                                    <pre style="font-size: 11px; margin: 0;"><code>&lt;li&gt;
    &lt;time datetime="{date:iso}"&gt;{date:jp}&lt;/time&gt;
    &lt;a href="{link_url}"&gt;{title}&lt;/a&gt;
&lt;/li&gt;</code></pre>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- 使用方法 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('使用方法', 'andw-news'); ?></h2>
                        <h4><?php echo esc_html__('ショートコード', 'andw-news'); ?></h4>
                        <code>[andw_news layout="list" per_page="10"]</code>

                        <h4><?php echo esc_html__('利用可能な属性', 'andw-news'); ?></h4>
                        <ul>
                            <li><strong>layout</strong>: テンプレート名</li>
                            <li><strong>cats</strong>: カテゴリID（カンマ区切り）</li>
                            <li><strong>per_page</strong>: 表示件数</li>
                            <li><strong>pinned_first</strong>: ピン留め優先（1 or 0）</li>
                            <li><strong>exclude_expired</strong>: 期限切れ除外（1 or 0）</li>
                        </ul>

                        <h4><?php echo esc_html__('Gutenbergブロック', 'andw-news'); ?></h4>
                        <p><?php echo esc_html__('「andW News List」ブロックを検索して使用できます。', 'andw-news'); ?></p>
                    </div>

                    <!-- SCFデバッグ -->
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('SCFデバッグ', 'andw-news'); ?></h2>
                        <?php $this->render_scf_debug_section(); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * フォーム送信を処理
     */
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // CSS設定の保存
        if (isset($_POST['save_css_settings']) && isset($_POST['andw_news_css_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['andw_news_css_nonce'])), 'andw_news_css_settings')) {
            $disable_css = !empty($_POST['disable_css']);
            update_option('andw_news_disable_css', $disable_css);

            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                     esc_html__('CSS設定を保存しました。', 'andw-news') . '</p></div>';
            });
        }

        // デフォルトサムネイル設定の保存
        if (isset($_POST['save_thumbnail_settings']) && isset($_POST['andw_news_thumbnail_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['andw_news_thumbnail_nonce'])), 'andw_news_thumbnail_settings')) {
            $thumbnail_id = isset($_POST['default_thumbnail_id']) ? intval(wp_unslash($_POST['default_thumbnail_id'])) : 0;

            if ($thumbnail_id > 0) {
                // 画像IDが有効かチェック
                if (wp_attachment_is_image($thumbnail_id)) {
                    update_option('andw_news_default_thumbnail', $thumbnail_id);
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' .
                             esc_html__('デフォルトサムネイル設定を保存しました。', 'andw-news') . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' .
                             esc_html__('無効な画像IDです。', 'andw-news') . '</p></div>';
                    });
                }
            } else {
                // 画像IDが0の場合は削除
                delete_option('andw_news_default_thumbnail');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                         esc_html__('デフォルトサムネイル設定を削除しました。', 'andw-news') . '</p></div>';
                });
            }
        }
    }

    /**
     * 管理画面通知を表示
     */
    private function show_admin_notices() {
        // 必要に応じて通知を表示
    }

    /**
     * Ajax: テンプレートプレビュー
     */
    public function ajax_preview_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_name = sanitize_text_field(wp_unslash($_POST['template_name'] ?? ''));

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        // サンプルデータでプレビューを生成（複数件のサンプル）
        $sample_data = [
            [
                'title' => 'サンプルニュースタイトル1',
                'date' => '2024.01.15', // 後方互換性のため維持
                'date_raw' => '2024-01-15', // 日付フォーマット用の生データ
                'excerpt' => 'これは1つ目のサンプルのニュース記事です。実際の投稿データに置き換わります。',
                'thumbnail' => $this->get_sample_thumbnail(),
                'event_date' => '<span class="andw-event-date">2024.01.20</span>',
                'link_url' => '#',
                'link_target' => '_self',
                'andw-link-type' => 'self',
                'andw-subcontents' => 'サンプルサブコンテンツです',
                'categories' => '<span class="category">お知らせ</span>'
            ],
            [
                'title' => 'サンプルニュースタイトル2',
                'date' => '2024.01.10', // 後方互換性のため維持
                'date_raw' => '2024-01-10', // 日付フォーマット用の生データ
                'excerpt' => 'これは2つ目のサンプルのニュース記事です。複数件表示のテストに使用されます。',
                'thumbnail' => $this->get_sample_thumbnail(),
                'event_date' => '<span class="andw-event-date">2024.01.25</span>',
                'link_url' => '#',
                'link_target' => '_self',
                'andw-link-type' => 'external',
                'andw-subcontents' => '外部リンクのサンプルサブコンテンツ',
                'categories' => '<span class="category">イベント</span>'
            ],
            [
                'title' => 'サムネイルなしのサンプル',
                'date' => '2024.01.05', // 後方互換性のため維持
                'date_raw' => '2024-01-05', // 日付フォーマット用の生データ
                'excerpt' => 'これはサムネイルがない投稿のサンプルです。デフォルトサムネイル機能のテストに使用されます。',
                'thumbnail' => $this->get_default_thumbnail_for_preview(),
                'event_date' => '<span class="andw-event-date">2024.01.15</span>',
                'link_url' => '#',
                'link_target' => '_self',
                'andw-link-type' => 'none',
                'andw-subcontents' => 'デフォルトサムネイルのテスト',
                'categories' => '<span class="category">テスト</span>'
            ]
        ];

        $template_manager = new ANDW_News_Template_Manager();
        $template = $template_manager->get_template($template_name);

        if (!$template) {
            wp_send_json_error(['message' => 'Template not found']);
        }

        // 新方式のテンプレートシステムを使用してプレビューを生成
        $preview_html = $template_manager->render_multiple_posts($sample_data, $template);

        wp_send_json_success(['html' => $preview_html]);
    }

    /**
     * Ajax: テンプレート保存
     */
    public function ajax_save_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_name = sanitize_text_field(filter_input(INPUT_POST, 'template_name', FILTER_SANITIZE_STRING) ?? '');

        // 生POST取得（$_POSTに直接触れない）
        $raw_post_data = filter_input(
            INPUT_POST,
            'template_data',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );

        if (!is_array($raw_post_data)) {
            wp_send_json_error(['message' => 'Invalid template data format']);
        }

        // 基本フィールドをサニタイズ
        $template_data = [];
        $template_data['name'] = sanitize_text_field(wp_unslash($raw_post_data['name'] ?? ''));
        $template_data['description'] = sanitize_textarea_field(wp_unslash($raw_post_data['description'] ?? ''));

        // HTMLフィールドはtemplate-manager側でサニタイズされるため、ここではホワイトリスト化のみ
        $html_fields = ['html', 'wrapper_html', 'item_html'];
        foreach ($html_fields as $field) {
            if (isset($raw_post_data[$field]) && is_string($raw_post_data[$field])) {
                $template_data[$field] = wp_unslash($raw_post_data[$field]);
            } else {
                $template_data[$field] = '';
            }
        }

        // CSSフィールド（template-manager側でサニタイズされる）
        if (isset($raw_post_data['css']) && is_string($raw_post_data['css'])) {
            $template_data['css'] = wp_unslash($raw_post_data['css']);
        } else {
            $template_data['css'] = '';
        }

        $is_edit = !empty(filter_input(INPUT_POST, 'is_edit'));
        $original_name = sanitize_text_field(filter_input(INPUT_POST, 'original_name', FILTER_SANITIZE_STRING) ?? '');

        if (empty($template_name) || empty($template_data)) {
            wp_send_json_error(['message' => 'Required fields are missing']);
        }

        $template_manager = new ANDW_News_Template_Manager();

        // 編集の場合で名前が変更された場合は古いテンプレートを削除
        if ($is_edit && $original_name !== $template_name && !empty($original_name)) {
            $template_manager->delete_template($original_name);
        }

        $result = $template_manager->save_template($template_name, $template_data);

        if ($result) {
            // テンプレート保存成功時にキャッシュをクリア
            $this->clear_news_cache();
            wp_send_json_success(['message' => 'Template saved successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to save template']);
        }
    }

    /**
     * Ajax: テンプレート取得
     */
    public function ajax_get_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_name = sanitize_text_field(wp_unslash($_POST['template_name'] ?? ''));

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $template = $template_manager->get_template($template_name);

        if (!$template) {
            wp_send_json_error(['message' => 'Template not found']);
        }

        wp_send_json_success($template);
    }

    /**
     * Ajax: テンプレート複製
     */
    public function ajax_duplicate_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $source_name = sanitize_text_field(wp_unslash($_POST['source_name'] ?? ''));
        $new_name = sanitize_text_field(wp_unslash($_POST['new_name'] ?? ''));
        $display_name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));

        if (empty($source_name) || empty($new_name)) {
            wp_send_json_error(['message' => 'Source and new template names are required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $result = $template_manager->duplicate_template($source_name, $new_name, $display_name);

        if ($result) {
            // テンプレート複製成功時にキャッシュをクリア
            $this->clear_news_cache();
            wp_send_json_success(['message' => 'Template duplicated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to duplicate template']);
        }
    }

    /**
     * Ajax: テンプレート削除
     */
    public function ajax_delete_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_name = sanitize_text_field(wp_unslash($_POST['template_name'] ?? ''));

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $result = $template_manager->delete_template($template_name);

        if ($result) {
            // テンプレート削除成功時にキャッシュをクリア
            $this->clear_news_cache();
            wp_send_json_success(['message' => 'Template deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete template']);
        }
    }

    /**
     * サンプル画像HTMLを取得
     *
     * @return string サンプル画像HTML
     */
    private function get_sample_thumbnail() {
        $sample_image_path = ANDW_NEWS_PLUGIN_DIR . 'assets/sample-thumbnail.png';

        if (file_exists($sample_image_path)) {
            return '<img src="' . esc_url(ANDW_NEWS_PLUGIN_URL . 'assets/sample-thumbnail.png') . '" alt="サンプル画像" class="andw-news-thumbnail" style="width:100px;height:80px;object-fit:cover;">';
        } else {
            // フォールバック: CSS背景のプレースホルダー
            return '<div class="andw-news-thumbnail" style="width:100px;height:80px;background:#ddd;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">サンプル画像</div>';
        }
    }

    /**
     * デフォルトサムネイル機能のテスト用画像HTMLを取得
     *
     * @return string デフォルトサムネイル画像HTML
     */
    private function get_default_thumbnail_for_preview() {
        // デフォルトサムネイル設定を取得
        $default_thumbnail_id = get_option('andw_news_default_thumbnail', 0);

        if ($default_thumbnail_id && wp_attachment_is_image($default_thumbnail_id)) {
            return wp_get_attachment_image($default_thumbnail_id, 'medium', false, ['class' => 'andw-news-thumbnail default']);
        }

        // デフォルトサムネイルが設定されていない場合は空文字を返す
        return '';
    }

    /**
     * Ajax: デフォルトテンプレート設定
     */
    public function ajax_set_default_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_name = sanitize_text_field(wp_unslash($_POST['template_name'] ?? ''));

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $result = $template_manager->set_default_template($template_name);

        if ($result) {
            // デフォルトテンプレート設定成功時にキャッシュをクリア
            $this->clear_news_cache();
            wp_send_json_success(['message' => 'Default template set successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to set default template']);
        }
    }

    /**
     * Ajax: キャッシュクリア
     */
    public function ajax_clear_cache() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // キャッシュをクリア
        $this->clear_news_cache();

        wp_send_json_success(['message' => 'Cache cleared successfully']);
    }

    /**
     * 重複テンプレートを検出
     *
     * @param array $templates テンプレート配列
     * @return array 重複している表示名の配列
     */
    private function detect_duplicate_templates($templates) {
        $name_count = [];
        $duplicates = [];

        foreach ($templates as $key => $template) {
            $name = $template['name'];
            if (!isset($name_count[$name])) {
                $name_count[$name] = [];
            }
            $name_count[$name][] = $key;
        }

        foreach ($name_count as $name => $keys) {
            if (count($keys) > 1) {
                $duplicates[] = [
                    'name' => $name,
                    'keys' => $keys
                ];
            }
        }

        return $duplicates;
    }

    /**
     * 重複警告を表示
     *
     * @param array $duplicates 重複テンプレート配列
     */
    private function show_duplicate_warnings($duplicates) {
        if (empty($duplicates)) {
            return;
        }

        foreach ($duplicates as $duplicate) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('重複テンプレート警告', 'andw-news') . ':</strong> ';
            echo sprintf(
                // translators: %1$s is template name, %2$s is comma-separated keys.
                esc_html__('「%1$s」という名前のテンプレートが複数存在します（キー: %2$s）。混乱を避けるため、不要なテンプレートを削除することをお勧めします。', 'andw-news'),
                esc_html($duplicate['name']),
                esc_html(implode(', ', $duplicate['keys']))
            );
            echo '</p></div>';
        }
    }

    /**
     * SCFデバッグセクションをレンダリング
     */
    private function render_scf_debug_section() {
        ?>
        <form id="scf-debug-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="debug-post-id"><?php echo esc_html__('投稿ID', 'andw-news'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="debug-post-id" class="regular-text" placeholder="<?php echo esc_attr__('空白で最新投稿', 'andw-news'); ?>" />
                        <p class="description"><?php echo esc_html__('デバッグしたいandw-news投稿のIDを入力してください。', 'andw-news'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="button" id="run-scf-debug" class="button button-primary">
                    <?php echo esc_html__('SCFデバッグ実行', 'andw-news'); ?>
                </button>
            </p>
        </form>
        <div id="scf-debug-results" style="margin-top: 20px; display: none;">
            <h4><?php echo esc_html__('デバッグ結果', 'andw-news'); ?></h4>
            <pre id="scf-debug-output" style="background: #f1f1f1; padding: 10px; overflow: auto; max-height: 400px; font-size: 12px;"></pre>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('run-scf-debug').addEventListener('click', function() {
                var postId = document.getElementById('debug-post-id').value;
                var button = this;
                var originalText = button.textContent;

                button.textContent = '<?php echo esc_js(__('実行中...', 'andw-news')); ?>';
                button.disabled = true;

                var formData = new FormData();
                formData.append('action', 'andw_news_scf_debug');
                formData.append('post_id', postId);
                formData.append('nonce', '<?php echo esc_js(wp_create_nonce('andw_news_scf_debug')); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('scf-debug-output').textContent = JSON.stringify(data.data, null, 2);
                        document.getElementById('scf-debug-results').style.display = 'block';
                    } else {
                        alert('<?php echo esc_js(__('エラーが発生しました: ', 'andw-news')); ?>' + (data.data || '<?php echo esc_js(__('不明なエラー', 'andw-news')); ?>'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('<?php echo esc_js(__('通信エラーが発生しました', 'andw-news')); ?>');
                })
                .finally(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: SCFデバッグを実行
     */
    public function ajax_scf_debug() {
        // nonce検証
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'andw_news_scf_debug')) {
            wp_send_json_error(__('不正なリクエストです。', 'andw-news'));
            return;
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません。', 'andw-news'));
        }

        $post_id = isset($_POST['post_id']) && !empty($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : null;

        try {
            $debug_info = $this->debug_scf_fields($post_id);
            wp_send_json_success($debug_info);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * SCFフィールドデバッグ処理
     */
    private function debug_scf_fields($post_id = null) {
        if (!$post_id) {
            // andw-news投稿タイプの最初の投稿を取得
            $posts = get_posts([
                'post_type' => 'andw-news',
                'numberposts' => 1,
                'post_status' => 'publish'
            ]);

            if (empty($posts)) {
                throw new Exception(esc_html__('andw-news投稿が見つかりません', 'andw-news'));
            }

            $post_id = $posts[0]->ID;
        }

        $debug_info = [];
        $debug_info['post_id'] = $post_id;
        $debug_info['post_title'] = get_the_title($post_id);

        // SCFプラグインがアクティブかチェック
        $debug_info['scf_active'] = class_exists('Smart_Custom_Fields');

        // 全メタデータを取得
        $all_meta = get_post_meta($post_id);
        $debug_info['all_meta'] = $all_meta;

        // 特定のandwフィールドをチェック
        $andw_fields = [
            'andw_news_pinned',
            'andw_link_type',
            'andw_internal_link',
            'andw_external_link',
            'andw_link_target',
            'andw_event_type',
            'andw_event_single_date',
            'andw_event_start_date',
            'andw_event_end_date',
            'andw_event_free_text',
            'andw_subcontents'
        ];

        $debug_info['andw_fields'] = [];
        foreach ($andw_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            $debug_info['andw_fields'][$field] = [
                'value' => $value,
                'type' => gettype($value),
                'empty' => empty($value),
                'raw_value' => wp_json_encode($value)
            ];
        }

        // SCF特有の取得方法もテスト
        if (class_exists('Smart_Custom_Fields')) {
            $debug_info['scf_methods'] = [];
            foreach ($andw_fields as $field) {
                // SCFの場合はSCF::get()メソッドを使うことがある
                if (method_exists('SCF', 'get')) {
                    $scf_value = SCF::get($field, $post_id);
                    $debug_info['scf_methods'][$field] = [
                        'scf_get' => $scf_value,
                        'type' => gettype($scf_value)
                    ];
                }
            }
        }

        return $debug_info;
    }

    /**
     * andW Newsのキャッシュをクリア
     */
    public function clear_news_cache() {
        // 保存されたキャッシュキーリストを取得
        $cache_keys = get_option('andw_news_cache_keys', []);

        // 各キャッシュキーを個別に削除
        foreach ($cache_keys as $cache_key) {
            delete_transient($cache_key);
            delete_site_transient($cache_key);
        }

        // キャッシュキーリストを初期化
        if (!empty($cache_keys)) {
            delete_option('andw_news_cache_keys');
        }
    }

    /**
     * 投稿保存時のキャッシュクリア処理
     *
     * @param int $post_id 投稿ID
     */
    public function on_post_save($post_id) {
        // andw-news投稿タイプのみ処理
        if (get_post_type($post_id) === 'andw-news') {
            $this->clear_news_cache();
        }
    }

    /**
     * 投稿ステータス変更時のキャッシュクリア処理
     *
     * @param int $post_id 投稿ID
     */
    public function on_post_status_change($post_id) {
        // andw-news投稿タイプのみ処理
        if (get_post_type($post_id) === 'andw-news') {
            $this->clear_news_cache();
        }
    }
}