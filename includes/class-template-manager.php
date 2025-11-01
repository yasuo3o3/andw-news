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

        // 既存のテンプレートを新形式に変換
        if (!empty($templates)) {
            $updated = false;
            foreach ($templates as $key => $template) {
                if (!isset($template['wrapper_html']) || !isset($template['item_html'])) {
                    $templates[$key] = $this->convert_legacy_to_new($template);
                    $updated = true;
                }
            }
            if ($updated) {
                update_option(self::TEMPLATES_OPTION, $templates);
            }
            return;
        }

        $default_templates = [
            'list' => [
                'name' => 'リスト',
                'wrapper_html' => '<div class="andw-news-list">{items}</div>',
                'item_html' => '<article class="andw-news-item{if pinned} andw-news-item--pinned{/if}">
                        <div class="andw-news-content">
                            <div class="andw-news-meta">
                                <time class="andw-news-date">{date}</time>
                                {if pinned}<span class="andw-news-pinned-badge">ピン留め</span>{/if}
                            </div>
                            <h3 class="andw-news-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                            <div class="andw-news-excerpt">{excerpt}</div>
                        </div>
                    </article>',
                'description' => 'シンプルなリスト表示'
            ],
            'cards' => [
                'name' => 'カード',
                'wrapper_html' => '<div class="andw-news-cards">{items}</div>',
                'item_html' => '<div class="andw-news-card{if pinned} andw-news-card--pinned{/if}">
                        <div class="andw-news-card-thumbnail">{thumbnail}</div>
                        <div class="andw-news-card-content">
                            <div class="andw-news-card-meta">
                                <time class="andw-news-card-date">{date}</time>
                                {if pinned}<span class="andw-news-pinned-badge">ピン留め</span>{/if}
                            </div>
                            <h3 class="andw-news-card-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                            <div class="andw-news-card-excerpt">{excerpt}</div>
                            {event_date}
                        </div>
                    </div>',
                'description' => 'カード形式での表示'
            ],
            'tabs' => [
                'name' => 'タブ',
                'wrapper_html' => '<div class="andw-news-tab-content">{items}</div>',
                'item_html' => '<article class="andw-news-tab-item{if pinned} andw-news-tab-item--pinned{/if}">
                        <div class="andw-news-tab-meta">
                            <time class="andw-news-tab-date">{date}</time>
                            {if pinned}<span class="andw-news-pinned-badge">ピン留め</span>{/if}
                            {event_date}
                        </div>
                        <h3 class="andw-news-tab-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                        <div class="andw-news-tab-excerpt">{excerpt}</div>
                    </article>',
                'description' => 'タブ切り替え表示'
            ],
            'ul_list' => [
                'name' => 'ULリスト',
                'wrapper_html' => '<ul class="news">{items}</ul>',
                'item_html' => '<li>
                        <time datetime="{date}">{date}</time>
                        <a href="{link_url}" target="{link_target}">{event_date} {title}</a>
                    </li>',
                'description' => 'HTMLリスト形式での表示'
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
            '#comment' => [], // HTMLコメントを許可
        ];

        // 新形式と従来形式のHTMLフィールドをサニタイズ
        if (isset($template_data['html'])) {
            $template_data['html'] = wp_kses($template_data['html'], $allowed_tags);
        }
        if (isset($template_data['wrapper_html'])) {
            $template_data['wrapper_html'] = wp_kses($template_data['wrapper_html'], $allowed_tags);
        }
        if (isset($template_data['item_html'])) {
            $template_data['item_html'] = wp_kses($template_data['item_html'], $allowed_tags);
        }

        $template_data['name'] = sanitize_text_field($template_data['name'] ?? '');
        $template_data['description'] = sanitize_textarea_field($template_data['description'] ?? '');

        $templates[$template_name] = $template_data;

        // update_option は値が同じ場合 false を返すが、それも成功扱いとする
        $result = update_option(self::TEMPLATES_OPTION, $templates);
        if ($result === false) {
            // 値が変更されなかった場合でも、実際に保存されているかチェック
            $saved_templates = get_option(self::TEMPLATES_OPTION, []);
            return isset($saved_templates[$template_name]) && $saved_templates[$template_name] === $template_data;
        }
        return true;
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
     * @param string $new_template_name 新しいテンプレート名（内部キー）
     * @param string $display_name 表示名（オプション）
     * @return bool 成功/失敗
     */
    public function duplicate_template($source_template_name, $new_template_name, $display_name = '') {
        if (empty($source_template_name) || empty($new_template_name)) {
            return false;
        }

        $source_template = $this->get_template($source_template_name);
        if (!$source_template) {
            return false;
        }

        $new_template = $source_template;

        // 表示名が指定されていればそれを使用、なければデフォルト
        if (!empty($display_name)) {
            $new_template['name'] = sanitize_text_field($display_name);
        } else {
            $new_template['name'] = sanitize_text_field($new_template['name'] . ' のコピー');
        }

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
        // 条件分岐を先に処理
        $html = $this->process_conditionals($html, $data);

        $tokens = [
            '{title}' => $data['title'] ?? '',
            '{date}' => $data['date'] ?? '',
            '{excerpt}' => $data['excerpt'] ?? '',
            '{thumbnail}' => $data['thumbnail'] ?? '',
            '{event_date}' => $data['event_date'] ?? '',
            '{link_url}' => $data['link_url'] ?? '',
            '{link_target}' => $data['link_target'] ?? '_self',
            '{pinned}' => $data['pinned'] ?? false,
            '{andw-news-pinned}' => isset($data['andw-news-pinned']) ? $data['andw-news-pinned'] : ($data['pinned'] ? '1' : '0'),
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

    /**
     * 条件分岐を処理
     *
     * @param string $html HTMLテンプレート
     * @param array $data データ配列
     * @return string 処理後のHTML
     */
    private function process_conditionals($html, $data) {
        // {if field_name}content{/if} 形式の条件分岐を処理
        $pattern = '/\{if\s+([^}]+)\}(.*?)\{\/if\}/s';

        $html = preg_replace_callback($pattern, function($matches) use ($data) {
            $condition = trim($matches[1]);
            $content = $matches[2];

            // 条件を評価
            if ($this->evaluate_condition($condition, $data)) {
                return $content;
            } else {
                return '';
            }
        }, $html);

        // {ifnot field_name}content{/ifnot} 形式の否定条件を処理
        $pattern_not = '/\{ifnot\s+([^}]+)\}(.*?)\{\/ifnot\}/s';

        $html = preg_replace_callback($pattern_not, function($matches) use ($data) {
            $condition = trim($matches[1]);
            $content = $matches[2];

            // 条件を評価（否定）
            if (!$this->evaluate_condition($condition, $data)) {
                return $content;
            } else {
                return '';
            }
        }, $html);

        // {if field_name}content{else}content{/if} 形式のif-else処理
        $pattern_else = '/\{if\s+([^}]+)\}(.*?)\{else\}(.*?)\{\/if\}/s';

        $html = preg_replace_callback($pattern_else, function($matches) use ($data) {
            $condition = trim($matches[1]);
            $true_content = $matches[2];
            $false_content = $matches[3];

            // 条件を評価
            if ($this->evaluate_condition($condition, $data)) {
                return $true_content;
            } else {
                return $false_content;
            }
        }, $html);

        return $html;
    }

    /**
     * 条件を評価
     *
     * @param string $condition 条件文字列
     * @param array $data データ配列
     * @return bool 条件の結果
     */
    private function evaluate_condition($condition, $data) {
        // 等値比較: field_name="value"
        if (preg_match('/^([^=]+)=["\'](.*?)["\']$/', $condition, $matches)) {
            $field = trim($matches[1]);
            $expected_value = $matches[2];
            $actual_value = $data[$field] ?? '';

            return $actual_value === $expected_value;
        }

        // 不等比較: field_name!="value"
        if (preg_match('/^([^!]+)!=["\'](.*?)["\']$/', $condition, $matches)) {
            $field = trim($matches[1]);
            $expected_value = $matches[2];
            $actual_value = $data[$field] ?? '';

            return $actual_value !== $expected_value;
        }

        // 存在チェック: field_name（値が空でない）
        $field = trim($condition);
        $value = $data[$field] ?? '';

        return !empty($value);
    }


    /**
     * 複数投稿のレンダリング
     *
     * @param array $posts_data 投稿データ配列
     * @param array $template テンプレートデータ
     * @return string レンダリング結果
     */
    public function render_multiple_posts($posts_data, $template) {
        // 新形式のテンプレートデータがない場合は、従来データから変換を試行
        if (!isset($template['wrapper_html']) || !isset($template['item_html'])) {
            $template = $this->convert_legacy_to_new($template);
        }

        return $this->render_new_template($posts_data, $template);
    }

    /**
     * 新テンプレート形式でレンダリング
     *
     * @param array $posts_data 投稿データ配列
     * @param array $template テンプレートデータ
     * @return string レンダリング結果
     */
    private function render_new_template($posts_data, $template) {
        $items_html = '';

        // 各投稿をitem_htmlでレンダリング
        foreach ($posts_data as $post_data) {
            $item_html = $this->replace_tokens($template['item_html'], $post_data);
            $items_html .= $item_html;
        }

        // wrapper_htmlで全体をラップ
        $wrapper_html = str_replace('{items}', $items_html, $template['wrapper_html']);

        return $wrapper_html;
    }


    /**
     * 従来形式を新形式に自動変換
     *
     * @param array $template 従来形式のテンプレート
     * @return array 新形式のテンプレート
     */
    public function convert_legacy_to_new($template) {
        if (isset($template['wrapper_html']) && isset($template['item_html'])) {
            return $template; // 既に新形式
        }

        // デフォルトの新形式を設定
        if (isset($template['html'])) {
            // 従来のhtmlをitem_htmlとして使用
            $template['wrapper_html'] = '<div class="andw-news-wrapper">{items}</div>';
            $template['item_html'] = $template['html'];
        } else {
            // 空のテンプレートの場合はデフォルト値を設定
            $template['wrapper_html'] = '<div class="andw-news-wrapper">{items}</div>';
            $template['item_html'] = '<div class="andw-news-item">{title}</div>';
        }

        return $template;
    }
}