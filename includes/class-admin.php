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
        add_action('admin_init', [$this, 'handle_form_submissions']);
    }

    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        add_options_page(
            __('お知らせ設定', 'andw-news-changer'),
            __('お知らせ設定', 'andw-news-changer'),
            'manage_options',
            'andw-news-changer',
            [$this, 'admin_page']
        );
    }

    /**
     * 管理画面ページを表示
     */
    public function admin_page() {
        // nonceチェック
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'andw-news-changer'));
        }

        $template_manager = new ANDW_News_Template_Manager();
        $templates = $template_manager->get_templates();
        $default_template = $template_manager->get_default_template();
        $disable_css = get_option('andw_news_disable_css', false);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('お知らせ設定', 'andw-news-changer'); ?></h1>

            <?php $this->show_admin_notices(); ?>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- 左側: テンプレート管理 -->
                <div style="flex: 1;">
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('テンプレート管理', 'andw-news-changer'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template-select"><?php echo esc_html__('テンプレート選択', 'andw-news-changer'); ?></label>
                                </th>
                                <td>
                                    <select id="template-select" class="regular-text">
                                        <option value=""><?php echo esc_html__('選択してください', 'andw-news-changer'); ?></option>
                                        <?php foreach ($templates as $key => $template): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $default_template); ?>>
                                                <?php echo esc_html($template['name']); ?>
                                                <?php if ($key === $default_template): ?>
                                                    (<?php echo esc_html__('デフォルト', 'andw-news-changer'); ?>)
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
                                <?php echo esc_html__('新規作成', 'andw-news-changer'); ?>
                            </button>
                            <button type="button" id="edit-template" class="button">
                                <?php echo esc_html__('編集', 'andw-news-changer'); ?>
                            </button>
                            <button type="button" id="duplicate-template" class="button">
                                <?php echo esc_html__('複製', 'andw-news-changer'); ?>
                            </button>
                            <button type="button" id="delete-template" class="button button-secondary">
                                <?php echo esc_html__('削除', 'andw-news-changer'); ?>
                            </button>
                            <button type="button" id="set-default" class="button">
                                <?php echo esc_html__('デフォルトに設定', 'andw-news-changer'); ?>
                            </button>
                        </p>
                    </div>

                    <!-- CSS設定 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('CSS設定', 'andw-news-changer'); ?></h2>

                        <form method="post" action="">
                            <?php wp_nonce_field('andw_news_css_settings', 'andw_news_css_nonce'); ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('プラグインCSS', 'andw-news-changer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="disable_css" value="1" <?php checked($disable_css); ?> />
                                            <?php echo esc_html__('プラグインのCSSを無効化する', 'andw-news-changer'); ?>
                                        </label>
                                        <p class="description">
                                            <?php echo esc_html__('チェックすると、プラグインのデフォルトCSSが読み込まれなくなります。', 'andw-news-changer'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <input type="submit" name="save_css_settings" class="button button-primary"
                                       value="<?php echo esc_attr__('CSS設定を保存', 'andw-news-changer'); ?>" />
                            </p>
                        </form>
                    </div>
                </div>

                <!-- 右側: プレビュー -->
                <div style="flex: 1;">
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('プレビュー', 'andw-news-changer'); ?></h2>
                        <div id="preview-area" style="border: 1px solid #ddd; padding: 20px; min-height: 300px; background: #f9f9f9;">
                            <p><?php echo esc_html__('テンプレートを選択してください。', 'andw-news-changer'); ?></p>
                        </div>
                    </div>

                    <!-- 使用方法 -->
                    <div class="card">
                        <h2 class="title"><?php echo esc_html__('使用方法', 'andw-news-changer'); ?></h2>
                        <h4><?php echo esc_html__('ショートコード', 'andw-news-changer'); ?></h4>
                        <code>[andw_news layout="list" per_page="10"]</code>

                        <h4><?php echo esc_html__('利用可能な属性', 'andw-news-changer'); ?></h4>
                        <ul>
                            <li><strong>layout</strong>: テンプレート名</li>
                            <li><strong>cats</strong>: カテゴリID（カンマ区切り）</li>
                            <li><strong>per_page</strong>: 表示件数</li>
                            <li><strong>pinned_first</strong>: ピン留め優先（1 or 0）</li>
                            <li><strong>exclude_expired</strong>: 期限切れ除外（1 or 0）</li>
                        </ul>

                        <h4><?php echo esc_html__('Gutenbergブロック', 'andw-news-changer'); ?></h4>
                        <p><?php echo esc_html__('「andW News List」ブロックを検索して使用できます。', 'andw-news-changer'); ?></p>
                    </div>
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
        if (isset($_POST['save_css_settings']) && wp_verify_nonce($_POST['andw_news_css_nonce'], 'andw_news_css_settings')) {
            $disable_css = !empty($_POST['disable_css']);
            update_option('andw_news_disable_css', $disable_css);

            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                     esc_html__('CSS設定を保存しました。', 'andw-news-changer') . '</p></div>';
            });
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

        $template_name = sanitize_text_field($_POST['template_name'] ?? '');

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        // サンプルデータでプレビューを生成
        $sample_data = [
            'title' => 'サンプルニュースタイトル',
            'date' => '2024.01.15',
            'excerpt' => 'これはサンプルのニュース記事です。実際の投稿データに置き換わります。',
            'thumbnail' => $this->get_sample_thumbnail(),
            'event_date' => '<span class="andw-event-date">2024.01.20</span>',
            'link_url' => '#',
            'link_target' => '_self'
        ];

        $template_manager = new ANDW_News_Template_Manager();
        $template = $template_manager->get_template($template_name);

        if (!$template) {
            wp_send_json_error(['message' => 'Template not found']);
        }

        $preview_html = $template_manager->replace_tokens($template['html'], $sample_data);

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

        $template_name = sanitize_text_field($_POST['template_name'] ?? '');
        $template_data = $_POST['template_data'] ?? [];
        $is_edit = !empty($_POST['is_edit']);
        $original_name = sanitize_text_field($_POST['original_name'] ?? '');

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

        $template_name = sanitize_text_field($_POST['template_name'] ?? '');

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

        $source_name = sanitize_text_field($_POST['source_name'] ?? '');
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');

        if (empty($source_name) || empty($new_name)) {
            wp_send_json_error(['message' => 'Source and new template names are required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $result = $template_manager->duplicate_template($source_name, $new_name);

        if ($result) {
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

        $template_name = sanitize_text_field($_POST['template_name'] ?? '');

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $result = $template_manager->delete_template($template_name);

        if ($result) {
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
     * Ajax: デフォルトテンプレート設定
     */
    public function ajax_set_default_template() {
        check_ajax_referer('andw_news_admin', 'nonce');

        if (!current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_name = sanitize_text_field($_POST['template_name'] ?? '');

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Template name is required']);
        }

        $template_manager = new ANDW_News_Template_Manager();
        $result = $template_manager->set_default_template($template_name);

        if ($result) {
            wp_send_json_success(['message' => 'Default template set successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to set default template']);
        }
    }
}