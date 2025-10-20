<?php
/**
 * テンプレート管理クラス
 *
 * Options APIを使用してテンプレートの保存・管理を行う
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANDW_News_Template_Manager {

    /**
     * テンプレートオプション名
     */
    const TEMPLATES_OPTION = 'andw_news_templates';
    const DEFAULT_TEMPLATE_OPTION = 'andw_news_default_template';

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 必要に応じてフックを追加
    }

    /**
     * デフォルトテンプレートをインストール
     */
    public function install_default_templates() {
        $templates = $this->get_templates();

        // 既にテンプレートが存在する場合はスキップ
        if (!empty($templates)) {
            return;
        }

        $default_templates = [
            'list' => [
                'name' => 'リスト',
                'html' => '<div class="andw-news-list">
                    <article class="andw-news-item">
                        <div class="andw-news-content">
                            <time class="andw-news-date">{date}</time>
                            <h3 class="andw-news-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                            <div class="andw-news-excerpt">{excerpt}</div>
                        </div>
                    </article>
                </div>',
                'description' => 'シンプルなリスト表示'
            ],
            'cards' => [
                'name' => 'カード',
                'html' => '<div class="andw-news-cards">
                    <div class="andw-news-card">
                        <div class="andw-news-card-thumbnail">{thumbnail}</div>
                        <div class="andw-news-card-content">
                            <time class="andw-news-card-date">{date}</time>
                            <h3 class="andw-news-card-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                            <div class="andw-news-card-excerpt">{excerpt}</div>
                            {event_date}
                        </div>
                    </div>
                </div>',
                'description' => 'カード形式での表示'
            ],
            'tabs' => [
                'name' => 'タブ',
                'html' => '<div class="andw-news-tab-content">
                    <article class="andw-news-tab-item">
                        <div class="andw-news-tab-meta">
                            <time class="andw-news-tab-date">{date}</time>
                            {event_date}
                        </div>
                        <h3 class="andw-news-tab-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                        <div class="andw-news-tab-excerpt">{excerpt}</div>
                    </article>
                </div>',
                'description' => 'タブ切り替え表示'
            ]
        ];

        update_option(self::TEMPLATES_OPTION, $default_templates);
        update_option(self::DEFAULT_TEMPLATE_OPTION, 'list');
    }

    /**
     * 全テンプレートを取得
     *
     * @return array テンプレート配列
     */
    public function get_templates() {
        return get_option(self::TEMPLATES_OPTION, []);
    }

    /**
     * 単一テンプレートを取得
     *
     * @param string $template_name テンプレート名
     * @return array|null テンプレートデータまたはnull
     */
    public function get_template($template_name) {
        $templates = $this->get_templates();
        return isset($templates[$template_name]) ? $templates[$template_name] : null;
    }

    /**
     * テンプレートを保存
     *
     * @param string $template_name テンプレート名
     * @param array $template_data テンプレートデータ
     * @return bool 成功/失敗
     */
    public function save_template($template_name, $template_data) {
        if (empty($template_name) || !is_array($template_data)) {
            return false;
        }

        $templates = $this->get_templates();

        // HTMLをサニタイズ
        $allowed_tags = [
            'div' => ['class' => true, 'id' => true, 'data-*' => true],
            'article' => ['class' => true, 'id' => true, 'data-*' => true],
            'section' => ['class' => true, 'id' => true, 'data-*' => true],
            'header' => ['class' => true, 'id' => true, 'data-*' => true],
            'footer' => ['class' => true, 'id' => true, 'data-*' => true],
            'h1' => ['class' => true, 'id' => true],
            'h2' => ['class' => true, 'id' => true],
            'h3' => ['class' => true, 'id' => true],
            'h4' => ['class' => true, 'id' => true],
            'h5' => ['class' => true, 'id' => true],
            'h6' => ['class' => true, 'id' => true],
            'p' => ['class' => true, 'id' => true],
            'a' => ['href' => true, 'class' => true, 'id' => true, 'target' => true, 'rel' => true],
            'span' => ['class' => true, 'id' => true, 'data-*' => true],
            'time' => ['class' => true, 'id' => true, 'datetime' => true],
            'img' => ['src' => true, 'alt' => true, 'class' => true, 'id' => true, 'width' => true, 'height' => true, 'loading' => true],
            'figure' => ['class' => true, 'id' => true],
            'figcaption' => ['class' => true, 'id' => true],
            'blockquote' => ['class' => true, 'id' => true, 'cite' => true],
            'cite' => ['class' => true],
            'code' => ['class' => true],
            'pre' => ['class' => true],
            'ul' => ['class' => true, 'id' => true],
            'ol' => ['class' => true, 'id' => true],
            'li' => ['class' => true, 'id' => true],
            'dl' => ['class' => true, 'id' => true],
            'dt' => ['class' => true],
            'dd' => ['class' => true],
            'strong' => ['class' => true],
            'em' => ['class' => true],
            'small' => ['class' => true],
            'mark' => ['class' => true],
            'del' => ['class' => true, 'datetime' => true],
            'ins' => ['class' => true, 'datetime' => true],
            'sub' => ['class' => true],
            'sup' => ['class' => true],
            'br' => [],
            'hr' => ['class' => true],
        ];

        $template_data['html'] = wp_kses($template_data['html'], $allowed_tags);
        $template_data['name'] = sanitize_text_field($template_data['name'] ?? '');
        $template_data['description'] = sanitize_textarea_field($template_data['description'] ?? '');

        $templates[$template_name] = $template_data;
        return update_option(self::TEMPLATES_OPTION, $templates);
    }

    /**
     * テンプレートを削除
     *
     * @param string $template_name テンプレート名
     * @return bool 成功/失敗
     */
    public function delete_template($template_name) {
        if (empty($template_name)) {
            return false;
        }

        $templates = $this->get_templates();

        if (!isset($templates[$template_name])) {
            return false;
        }

        unset($templates[$template_name]);

        // 削除されたテンプレートがデフォルトの場合、フォールバック
        $default_template = get_option(self::DEFAULT_TEMPLATE_OPTION);
        if ($default_template === $template_name) {
            $remaining_templates = array_keys($templates);
            $new_default = !empty($remaining_templates) ? $remaining_templates[0] : 'list';
            update_option(self::DEFAULT_TEMPLATE_OPTION, $new_default);
        }

        return update_option(self::TEMPLATES_OPTION, $templates);
    }

    /**
     * テンプレートを複製
     *
     * @param string $source_template_name 複製元テンプレート名
     * @param string $new_template_name 新しいテンプレート名
     * @return bool 成功/失敗
     */
    public function duplicate_template($source_template_name, $new_template_name) {
        if (empty($source_template_name) || empty($new_template_name)) {
            return false;
        }

        $source_template = $this->get_template($source_template_name);
        if (!$source_template) {
            return false;
        }

        $new_template = $source_template;
        $new_template['name'] = sanitize_text_field($new_template['name'] . ' のコピー');

        return $this->save_template($new_template_name, $new_template);
    }

    /**
     * デフォルトテンプレートを設定
     *
     * @param string $template_name テンプレート名
     * @return bool 成功/失敗
     */
    public function set_default_template($template_name) {
        if (empty($template_name)) {
            return false;
        }

        $template = $this->get_template($template_name);
        if (!$template) {
            return false;
        }

        return update_option(self::DEFAULT_TEMPLATE_OPTION, $template_name);
    }

    /**
     * デフォルトテンプレート名を取得
     *
     * @return string デフォルトテンプレート名
     */
    public function get_default_template() {
        return get_option(self::DEFAULT_TEMPLATE_OPTION, 'list');
    }

    /**
     * トークンを実際の値に置換
     *
     * @param string $html HTMLテンプレート
     * @param array $data 置換データ
     * @return string 置換後のHTML
     */
    public function replace_tokens($html, $data) {
        $tokens = [
            '{title}' => $data['title'] ?? '',
            '{date}' => $data['date'] ?? '',
            '{excerpt}' => $data['excerpt'] ?? '',
            '{thumbnail}' => $data['thumbnail'] ?? '',
            '{event_date}' => $data['event_date'] ?? '',
            '{link_url}' => $data['link_url'] ?? '',
            '{link_target}' => $data['link_target'] ?? '_self',
        ];

        // 動的にSCFフィールドのトークンを追加
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // andwプレフィックスのフィールドをトークンとして追加
                if ((strpos($key, 'andw_') === 0 || strpos($key, 'andw-') === 0) && !isset($tokens['{' . $key . '}'])) {
                    $tokens['{' . $key . '}'] = $value;
                }
            }
        }

        return str_replace(array_keys($tokens), array_values($tokens), $html);
    }
}