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
                $template_updated = false;

                // 旧形式から新形式への変換
                if (!isset($template['wrapper_html']) || !isset($template['item_html'])) {
                    $templates[$key] = $this->convert_legacy_to_new($template);
                    $template_updated = true;
                }

                // スラグがない場合は追加
                if (!isset($template['slug'])) {
                    $templates[$key]['slug'] = $this->generate_slug_from_name($template['name'] ?? $key);
                    $template_updated = true;
                }

                if ($template_updated) {
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
                'slug' => 'list',
                'wrapper_html' => '<div class="andw-news-list">{items}</div>',
                'item_html' => '<article class="andw-news-item{if pinned} andw-news-item--pinned{/if}">
                        <div class="andw-news-content">
                            <div class="andw-news-meta">
                                <time class="andw-news-date">{date}</time>
                                {if pinned}<span class="andw-news-pinned-badge">ピン留め</span>{/if}
                            </div>
                            <h3 class="andw-news-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                            <div class="andw-news-excerpt">{excerpt}</div>
                            {if andw-subcontents}<div class="andw-news-subcontents">{andw-subcontents}</div>{/if}
                        </div>
                    </article>',
                'description' => 'シンプルなリスト表示'
            ],
            'cards' => [
                'name' => 'カード',
                'slug' => 'cards',
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
                            {if andw-subcontents}<div class="andw-news-card-subcontents">{andw-subcontents}</div>{/if}
                            {event_date}
                        </div>
                    </div>',
                'description' => 'カード形式での表示'
            ],
            'tabs' => [
                'name' => 'タブ',
                'slug' => 'tabs',
                'wrapper_html' => '<div class="andw-news-tab-content">{items}</div>',
                'item_html' => '<article class="andw-news-tab-item{if pinned} andw-news-tab-item--pinned{/if}">
                        <div class="andw-news-tab-meta">
                            <time class="andw-news-tab-date">{date}</time>
                            {if pinned}<span class="andw-news-pinned-badge">ピン留め</span>{/if}
                            {event_date}
                        </div>
                        <h3 class="andw-news-tab-title"><a href="{link_url}" target="{link_target}">{title}</a></h3>
                        <div class="andw-news-tab-excerpt">{excerpt}</div>
                        {if andw-subcontents}<div class="andw-news-tab-subcontents">{andw-subcontents}</div>{/if}
                    </article>',
                'description' => 'タブ切り替え表示'
            ],
            'ul_list' => [
                'name' => 'ULリスト',
                'slug' => 'ul-list',
                'wrapper_html' => '<ul class="news">{items}</ul>',
                'item_html' => '<li>
                        <time datetime="{date}">{date}</time>
                        <a href="{link_url}" target="{link_target}">{event_date} {title}</a>
                        {if andw-subcontents}<div class="andw-news-ul-subcontents">{andw-subcontents}</div>{/if}
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
            'img' => ['src' => true, 'alt' => true, 'class' => true, 'id' => true, 'width' => true, 'height' => true, 'loading' => true, 'fetchpriority' => true, 'decoding' => true, 'srcset' => true, 'sizes' => true],
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
            // フィルタータブとインタラクティブ要素
            'button' => ['class' => true, 'id' => true, 'type' => true, 'data-*' => true, 'onclick' => true],
            // スタイルとスクリプト（ラッパーHTML用）
            'style' => ['type' => true, 'media' => true],
            'script' => ['type' => true, 'src' => true, 'defer' => true, 'async' => true],
            '#comment' => [], // HTMLコメントを許可
        ];

        // 新形式と従来形式のHTMLフィールドをサニタイズ
        if (isset($template_data['html'])) {
            $template_data['html'] = wp_kses($template_data['html'], $allowed_tags);
        }
        if (isset($template_data['wrapper_html'])) {
            $template_data['wrapper_html'] = $this->sanitize_wrapper_html($template_data['wrapper_html']);
        }
        if (isset($template_data['item_html'])) {
            $template_data['item_html'] = wp_kses($template_data['item_html'], $allowed_tags);
        }

        $template_data['name'] = sanitize_text_field($template_data['name'] ?? '');
        $template_data['description'] = sanitize_textarea_field($template_data['description'] ?? '');

        // スラグの処理
        $template_data['slug'] = $this->sanitize_template_slug($template_data['slug'] ?? '', $template_data['name'], $template_name);

        // カスタムCSSをサニタイズ
        if (isset($template_data['css'])) {
            $template_data['css'] = $this->sanitize_css($template_data['css']);
        }

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

        // 日付フォーマット付きトークンを先に処理
        $html = $this->process_date_format_tokens($html, $data);

        $tokens = [
            '{title}' => $data['title'] ?? '',
            '{date}' => $data['date'] ?? '',
            '{excerpt}' => $data['excerpt'] ?? '',
            '{thumbnail}' => $data['thumbnail'] ?? '',
            '{event_date}' => $data['event_date'] ?? '',
            '{link_url}' => $data['link_url'] ?? '',
            '{link_target}' => $data['link_target'] ?? '_self',
            '{pinned}' => $data['pinned'] ?? false,
            '{categories}' => $data['categories'] ?? '',
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


        $result = str_replace(array_keys($tokens), array_values($tokens), $html);


        return $result;
    }

    /**
     * 日付フォーマット付きトークンを処理
     *
     * @param string $html HTMLテンプレート
     * @param array $data データ配列
     * @return string 処理後のHTML
     */
    private function process_date_format_tokens($html, $data) {
        // {date:format} 形式のトークンを検索
        $pattern = '/\{date:([^}]+)\}/';

        $html = preg_replace_callback($pattern, function($matches) use ($data) {
            $format = trim($matches[1]);

            // 優先順位: date_raw > date
            $date_value = $data['date_raw'] ?? $data['date'] ?? '';

            if (empty($date_value)) {
                return '';
            }

            // フォーマット名の短縮形を展開
            $format_map = [
                'jp' => 'Y年n月j日',
                'jp_full' => 'Y年m月d日',
                'short' => 'n/j',
                'short_full' => 'm/d',
                'iso' => 'Y-m-d',
                'dot' => 'Y.m.d',
                'slash' => 'Y/m/d',
                'w' => 'Y年n月j日(D)',
                'w_full' => 'Y年m月d日(D)',
            ];

            // マップされたフォーマットがあれば使用、なければそのまま使用
            $actual_format = $format_map[$format] ?? $format;

            // 日付文字列をタイムスタンプに変換（複数形式に対応）
            $timestamp = $this->parse_date_string($date_value);
            if ($timestamp === false) {
                return $date_value; // 変換できない場合は元の値を返す
            }

            // WordPress の wp_date 関数を使用してフォーマット
            return wp_date($actual_format, $timestamp);
        }, $html);

        // {event_date:format} 形式のトークンも処理
        $pattern = '/\{event_date:([^}]+)\}/';

        $html = preg_replace_callback($pattern, function($matches) use ($data) {
            $format = trim($matches[1]);
            $event_date_value = $data['event_date'] ?? '';

            if (empty($event_date_value)) {
                return '';
            }

            // フォーマット名の短縮形を展開
            $format_map = [
                'jp' => 'Y年n月j日',
                'jp_full' => 'Y年m月d日',
                'short' => 'n/j',
                'short_full' => 'm/d',
                'iso' => 'Y-m-d',
                'dot' => 'Y.m.d',
                'slash' => 'Y/m/d',
                'w' => 'Y年n月j日(D)',
                'w_full' => 'Y年m月d日(D)',
            ];

            // マップされたフォーマットがあれば使用、なければそのまま使用
            $actual_format = $format_map[$format] ?? $format;

            // HTMLタグが含まれている場合の処理（event_dateは複雑な形式の場合がある）
            if (strpos($event_date_value, '<') !== false) {
                return $event_date_value; // HTMLが含まれている場合はそのまま返す
            }

            // 日付文字列をタイムスタンプに変換（複数形式に対応）
            $timestamp = $this->parse_date_string($event_date_value);
            if ($timestamp === false) {
                return $event_date_value; // 変換できない場合は元の値を返す
            }

            // WordPress の wp_date 関数を使用してフォーマット
            return wp_date($actual_format, $timestamp);
        }, $html);

        return $html;
    }

    /**
     * 日付文字列を解析してタイムスタンプを取得
     *
     * @param string $date_string 日付文字列
     * @return int|false タイムスタンプまたはfalse
     */
    private function parse_date_string($date_string) {
        if (empty($date_string)) {
            return false;
        }

        // 複数の日付形式を試行
        $formats = [
            'Y-m-d',        // 2025-01-31
            'Y.m.d',        // 2025.1.31 or 2025.01.31
            'Y/m/d',        // 2025/1/31 or 2025/01/31
            'Y-m-d H:i:s',  // 2025-01-31 12:34:56
            'm/d/Y',        // 1/31/2025
            'd/m/Y',        // 31/1/2025
            'd.m.Y',        // 31.1.2025
        ];

        // まずstrtotimeで試行
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            return $timestamp;
        }

        // 各フォーマットでDateTimeを使って解析を試行
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                return $date->getTimestamp();
            }
        }

        // ドット区切りの日付を特別処理（2025.1.31 -> 2025-01-31）
        if (preg_match('/^(\d{4})\.(\d{1,2})\.(\d{1,2})$/', $date_string, $matches)) {
            $normalized = sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
            $timestamp = strtotime($normalized);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return false;
    }

    /**
     * 条件分岐を処理
     *
     * @param string $html HTMLテンプレート
     * @param array $data データ配列
     * @return string 処理後のHTML
     */
    private function process_conditionals($html, $data) {

        // 処理の最大回数を制限（無限ループ防止）
        $max_iterations = 10;
        $iteration = 0;

        do {
            $original_html = $html;
            $iteration++;

            // 1. {if field_name}content{else}content{/if} 形式を最初に処理（優先度最高）
            $pattern_else = '/\{if\s+([^}]+)\}((?:[^{]++|\{(?!\/if\}|else\}))*+)\{else\}((?:[^{]++|\{(?!\/if\}))*+)\{\/if\}/s';
            $html = preg_replace_callback($pattern_else, function($matches) use ($data) {
                $condition = trim($matches[1]);
                $true_content = $matches[2];
                $false_content = $matches[3];

                $result = $this->evaluate_condition($condition, $data);

                return $result ? $true_content : $false_content;
            }, $html);

            // 2. {ifnot field_name}content{/ifnot} 形式を処理
            $pattern_not = '/\{ifnot\s+([^}]+)\}((?:[^{]++|\{(?!\/ifnot\}))*+)\{\/ifnot\}/s';
            $html = preg_replace_callback($pattern_not, function($matches) use ($data) {
                $condition = trim($matches[1]);
                $content = $matches[2];

                $result = !$this->evaluate_condition($condition, $data);

                return $result ? $content : '';
            }, $html);

            // 3. {if field_name}content{/if} 形式を最後に処理
            $pattern = '/\{if\s+([^}]+)\}((?:[^{]++|\{(?!\/if\}|else\}))*+)\{\/if\}/s';
            $html = preg_replace_callback($pattern, function($matches) use ($data) {
                $condition = trim($matches[1]);
                $content = $matches[2];

                $result = $this->evaluate_condition($condition, $data);

                return $result ? $content : '';
            }, $html);

        } while ($html !== $original_html && $iteration < $max_iterations);

        // 最大反復回数に達した場合は警告なしで処理継続

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
        $condition = trim($condition);

        // 等値比較: field_name="value" または field_name='value'
        if (preg_match('/^([^=]+)=["\'](.*?)["\']$/', $condition, $matches)) {
            $field = trim($matches[1]);
            $expected_value = $matches[2];
            $actual_value = $data[$field] ?? '';

            return $actual_value === $expected_value;
        }

        // 不等比較: field_name!="value" または field_name!='value'
        if (preg_match('/^([^!]+)!=["\'](.*?)["\']$/', $condition, $matches)) {
            $field = trim($matches[1]);
            $expected_value = $matches[2];
            $actual_value = $data[$field] ?? '';

            return $actual_value !== $expected_value;
        }

        // 存在チェック: field_name（値が空でない）
        $field = trim($condition);
        $value = $data[$field] ?? '';

        // 特殊な値の処理
        $is_not_empty = false;
        if (is_string($value)) {
            $is_not_empty = $value !== '' && $value !== '0' && $value !== 'false';
        } elseif (is_numeric($value)) {
            $is_not_empty = $value != 0;
        } elseif (is_bool($value)) {
            $is_not_empty = $value;
        } else {
            $is_not_empty = !empty($value);
        }

        return $is_not_empty;
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

    /**
     * CSSを安全にサニタイズ
     *
     * @param string $css CSS文字列
     * @return string サニタイズ済みCSS
     */
    private function sanitize_css($css) {
        if (empty($css)) {
            return '';
        }

        // @import文を除去（セキュリティリスク対策）
        $css = preg_replace('/@import[^;]+;/i', '', $css);

        // javascript:URLを除去
        $css = preg_replace('/javascript:/i', '', $css);

        // vbscript:URLを除去
        $css = preg_replace('/vbscript:/i', '', $css);

        // data:URLのうちdangerous typesを除去
        $css = preg_replace('/data:(?!image\/|text\/css)[^;,]+[;,]/i', '', $css);

        // expression()を除去（IE用CSS expression attack対策）
        $css = preg_replace('/expression\s*\(/i', '', $css);

        // 改行文字を正規化
        $css = preg_replace('/\r\n|\r|\n/', "\n", $css);

        // 基本的なサニタイゼーション（HTMLタグ除去は行わない）
        return trim($css);
    }

    /**
     * ラッパーHTMLを安全にサニタイズ（<style>と<script>内容を保持）
     *
     * @param string $html ラッパーHTML文字列
     * @return string サニタイズ済みHTML
     */
    private function sanitize_wrapper_html($html) {
        if (empty($html)) {
            return '';
        }

        // 基本的なHTMLタグ用の許可リスト
        $allowed_tags = [
            'div' => ['class' => true, 'id' => true, 'data-*' => true],
            'ul' => ['class' => true, 'id' => true],
            'li' => ['class' => true, 'id' => true, 'data-*' => true],
            'button' => ['class' => true, 'id' => true, 'type' => true, 'data-*' => true],
            'span' => ['class' => true, 'id' => true, 'data-*' => true],
        ];

        // <style>タグ内容を一時保存
        $style_contents = [];
        $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/s', function($matches) use (&$style_contents) {
            $index = count($style_contents);
            $style_contents[$index] = $this->sanitize_css($matches[1]);
            return "<!--STYLE_PLACEHOLDER_$index-->";
        }, $html);

        // <script>タグ内容を一時保存（基本的なJavaScript検証）
        $script_contents = [];
        $html = preg_replace_callback('/<script[^>]*>(.*?)<\/script>/s', function($matches) use (&$script_contents) {
            $script = $matches[1];

            // 危険なJavaScript関数を除去
            $script = preg_replace('/eval\s*\(/i', '', $script);
            $script = preg_replace('/innerHTML\s*=/i', '', $script);
            $script = preg_replace('/outerHTML\s*=/i', '', $script);
            $script = preg_replace('/document\.write/i', '', $script);

            $index = count($script_contents);
            $script_contents[$index] = trim($script);
            return "<!--SCRIPT_PLACEHOLDER_$index-->";
        }, $html);

        // HTMLタグをサニタイズ
        $html = wp_kses($html, $allowed_tags);

        // <style>タグを復元
        $html = preg_replace_callback('/<!--STYLE_PLACEHOLDER_(\d+)-->/', function($matches) use ($style_contents) {
            $index = intval($matches[1]);
            if (isset($style_contents[$index])) {
                return '<style>' . $style_contents[$index] . '</style>';
            }
            return '';
        }, $html);

        // <script>タグを復元
        $html = preg_replace_callback('/<!--SCRIPT_PLACEHOLDER_(\d+)-->/', function($matches) use ($script_contents) {
            $index = intval($matches[1]);
            if (isset($script_contents[$index])) {
                return '<script>' . $script_contents[$index] . '</script>';
            }
            return '';
        }, $html);

        return trim($html);
    }

    /**
     * テンプレートスラグをサニタイズ・生成
     *
     * @param string $slug 入力されたスラグ
     * @param string $name テンプレート名
     * @param string $template_name 既存のテンプレートキー
     * @return string サニタイズ済みスラグ
     */
    private function sanitize_template_slug($slug, $name, $template_name) {
        // スラグが空の場合は名前から自動生成
        if (empty($slug)) {
            $slug = $this->generate_slug_from_name($name);
        }

        // スラグをサニタイズ（英数字・ハイフン・アンダースコアのみ）
        $slug = sanitize_title($slug);
        $slug = preg_replace('/[^a-z0-9\-_]/i', '', $slug);

        // 空になった場合はデフォルト値
        if (empty($slug)) {
            $slug = 'template-' . uniqid();
        }

        // 重複チェック（自分自身は除外）
        $slug = $this->ensure_unique_slug($slug, $template_name);

        return $slug;
    }

    /**
     * 名前からスラグを生成
     *
     * @param string $name テンプレート名
     * @return string 生成されたスラグ
     */
    private function generate_slug_from_name($name) {
        // 基本的な置換ルール
        $replacements = [
            'サムネイルカード' => 'thumbnail-card',
            'サムネイル' => 'thumbnail',
            'カード' => 'card',
            'リスト' => 'list',
            'タブ' => 'tabs',
            'ニュース' => 'news',
            'お知らせ' => 'notice',
            'イベント' => 'event',
            'ブログ' => 'blog',
            'アーカイブ' => 'archive',
            'グリッド' => 'grid',
            'マガジン' => 'magazine'
        ];

        $slug = $name;

        // 日本語から英語への変換
        foreach ($replacements as $jp => $en) {
            $slug = str_replace($jp, $en, $slug);
        }

        // 残った日本語をローマ字化（簡易版）
        $slug = $this->japanese_to_romaji($slug);

        // WordPressのsanitize_title関数を使用
        $slug = sanitize_title($slug);

        return $slug ?: 'template';
    }

    /**
     * 簡易的な日本語→ローマ字変換
     *
     * @param string $text 日本語テキスト
     * @return string ローマ字
     */
    private function japanese_to_romaji($text) {
        // ひらがな・カタカナの基本的な変換テーブル
        $romaji_map = [
            'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
            'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
            'が' => 'ga', 'ぎ' => 'gi', 'ぐ' => 'gu', 'げ' => 'ge', 'ご' => 'go',
            'さ' => 'sa', 'し' => 'shi', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
            'ざ' => 'za', 'じ' => 'ji', 'ず' => 'zu', 'ぜ' => 'ze', 'ぞ' => 'zo',
            'た' => 'ta', 'ち' => 'chi', 'つ' => 'tsu', 'て' => 'te', 'と' => 'to',
            'だ' => 'da', 'ぢ' => 'di', 'づ' => 'du', 'で' => 'de', 'ど' => 'do',
            'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
            'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'fu', 'へ' => 'he', 'ほ' => 'ho',
            'ば' => 'ba', 'び' => 'bi', 'ぶ' => 'bu', 'べ' => 'be', 'ぼ' => 'bo',
            'ぱ' => 'pa', 'ぴ' => 'pi', 'ぷ' => 'pu', 'ぺ' => 'pe', 'ぽ' => 'po',
            'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
            'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
            'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
            'わ' => 'wa', 'ゐ' => 'wi', 'ゑ' => 'we', 'を' => 'wo', 'ん' => 'n',
            // カタカナ
            'ア' => 'a', 'イ' => 'i', 'ウ' => 'u', 'エ' => 'e', 'オ' => 'o',
            'カ' => 'ka', 'キ' => 'ki', 'ク' => 'ku', 'ケ' => 'ke', 'コ' => 'ko',
            'ガ' => 'ga', 'ギ' => 'gi', 'グ' => 'gu', 'ゲ' => 'ge', 'ゴ' => 'go',
            'サ' => 'sa', 'シ' => 'shi', 'ス' => 'su', 'セ' => 'se', 'ソ' => 'so',
            'ザ' => 'za', 'ジ' => 'ji', 'ズ' => 'zu', 'ゼ' => 'ze', 'ゾ' => 'zo',
            'タ' => 'ta', 'チ' => 'chi', 'ツ' => 'tsu', 'テ' => 'te', 'ト' => 'to',
            'ダ' => 'da', 'ヂ' => 'di', 'ヅ' => 'du', 'デ' => 'de', 'ド' => 'do',
            'ナ' => 'na', 'ニ' => 'ni', 'ヌ' => 'nu', 'ネ' => 'ne', 'ノ' => 'no',
            'ハ' => 'ha', 'ヒ' => 'hi', 'フ' => 'fu', 'ヘ' => 'he', 'ホ' => 'ho',
            'バ' => 'ba', 'ビ' => 'bi', 'ブ' => 'bu', 'ベ' => 'be', 'ボ' => 'bo',
            'パ' => 'pa', 'ピ' => 'pi', 'プ' => 'pu', 'ペ' => 'pe', 'ポ' => 'po',
            'マ' => 'ma', 'ミ' => 'mi', 'ム' => 'mu', 'メ' => 'me', 'モ' => 'mo',
            'ヤ' => 'ya', 'ユ' => 'yu', 'ヨ' => 'yo',
            'ラ' => 'ra', 'リ' => 'ri', 'ル' => 'ru', 'レ' => 're', 'ロ' => 'ro',
            'ワ' => 'wa', 'ヰ' => 'wi', 'ヱ' => 'we', 'ヲ' => 'wo', 'ン' => 'n'
        ];

        return str_replace(array_keys($romaji_map), array_values($romaji_map), $text);
    }

    /**
     * 重複しないスラグを確保
     *
     * @param string $slug 希望するスラグ
     * @param string $exclude_template 除外するテンプレート名
     * @return string 重複しないスラグ
     */
    private function ensure_unique_slug($slug, $exclude_template = '') {
        $templates = $this->get_templates();
        $original_slug = $slug;
        $counter = 1;

        while ($this->slug_exists($slug, $templates, $exclude_template)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * スラグが既存テンプレートで使用されているかチェック
     *
     * @param string $slug チェックするスラグ
     * @param array $templates テンプレート配列
     * @param string $exclude_template 除外するテンプレート名
     * @return bool 使用されている場合true
     */
    private function slug_exists($slug, $templates, $exclude_template = '') {
        foreach ($templates as $template_name => $template_data) {
            if ($template_name === $exclude_template) {
                continue;
            }
            if (isset($template_data['slug']) && $template_data['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }

    /**
     * テンプレートのスラグを取得
     *
     * @param string $template_name テンプレート名
     * @return string スラグ
     */
    public function get_template_slug($template_name) {
        $templates = $this->get_templates();
        if (isset($templates[$template_name]['slug'])) {
            return $templates[$template_name]['slug'];
        }
        // フォールバック: 名前からスラグを生成
        return $this->generate_slug_from_name($templates[$template_name]['name'] ?? $template_name);
    }
}