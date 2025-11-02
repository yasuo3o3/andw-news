# andW News

**Contributors:** yasuo3o3
**Tags:** news, custom-post-type, template, layout, shortcode
**Requires at least:** WordPress 6.5
**Tested up to:** WordPress 6.6
**Requires PHP:** 7.4
**Stable tag:** 0.1.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

カスタム投稿タイプ「andw-news」の記事を様々なテンプレートで表示するWordPressプラグイン

## Description

andW Newsは、カスタム投稿タイプ「andw-news」の記事を柔軟なテンプレートシステムで表示できるWordPressプラグインです。

### 主な機能

- 複数のレイアウトテンプレート（リスト、カード、タブ）
- テンプレートの管理（作成、編集、複製、削除）
- ショートコード対応
- Gutenbergブロック対応
- Smart Custom Fields（SCF）連携
- テーマCSS上書き機能
- ピン留め・優先表示機能
- カテゴリーバッジ表示機能

### 使用方法

#### ショートコード

```php
[andw_news layout="cards" per_page="10"]
```

#### 利用可能な属性

- `layout` - テンプレート名（list, cards, tabs, tabs_by_category等）
- `cats` - カテゴリID（カンマ区切り）
- `per_page` - 表示件数
- `pinned_first` - ピン留め優先表示（1 or 0）
- `exclude_expired` - 期限切れ除外（1 or 0）

#### Gutenbergブロック

ブロックエディタで「andW News List」ブロックを検索してご利用ください。

### テンプレートカスタマイズ

プラグインの管理画面「お知らせチェンジャー設定」から、HTMLテンプレートを自由に編集できます。

#### 利用可能なトークン

- `{title}` - 記事タイトル
- `{date}` - 投稿日
- `{excerpt}` - 抜粋
- `{thumbnail}` - サムネイル画像
- `{event_date}` - イベント日付（SCFフィールド）
- `{link_url}` - リンクURL
- `{link_target}` - リンクターゲット
- `{categories}` - カテゴリーバッジ（HTML形式）

#### 日付フォーマット機能

日付トークンには柔軟なフォーマットオプションが利用できます：

**基本的な使い方:**
- `{date}` - デフォルト形式（例：2025.1.31）
- `{date:jp}` - 日本語形式（例：2025年1月31日）
- `{date:short}` - 短縮形式（例：1/31）
- `{event_date:jp}` - イベント日付の日本語形式

**定義済みフォーマット:**
- `jp` → Y年n月j日（2025年1月31日）
- `jp_full` → Y年m月d日（2025年01月31日）
- `short` → n/j（1/31）
- `short_full` → m/d（01/31）
- `iso` → Y-m-d（2025-01-31）
- `dot` → Y.m.d（2025.1.31）
- `slash` → Y/m/d（2025/1/31）
- `w` → Y年n月j日(D)（2025年1月31日(金)）
- `w_full` → Y年m月d日(D)（2025年01月31日(金)）

**カスタムフォーマット:**
PHPの日付フォーマット文字列も直接使用可能：
- `{date:Y年n月j日}` → 2025年1月31日
- `{date:m/d/Y}` → 01/31/2025
- `{date:F j, Y}` → January 31, 2025

**使用例:**
```html
<li>
    <time datetime="{date:iso}">{date:jp}</time>
    {if categories}
        <div class="andw-categories-wrapper">
            {categories}
        </div>
    {/if}
    <a href="{link_url}">{title}</a>
</li>
```

### カテゴリーバッジ機能

各投稿に割り当てられたカテゴリーを色分けされたバッジとして表示できます。

#### 利用可能なカテゴリー色

- **news**: 青系バッジ（#e3f2fd / #1976d2）
- **event**: 紫系バッジ（#f3e5f5 / #7b1fa2）
- **info**: 緑系バッジ（#e8f5e8 / #388e3c）
- **important**: 赤系バッジ（#ffebee / #d32f2f）
- **notice**: オレンジ系バッジ（#fff3e0 / #f57c00）
- その他: グレー系バッジ（デフォルト）

#### カテゴリーバッジの出力例

```html
<div class="andw-categories-wrapper">
  <span class="andw-category andw-category-news">ニュース</span>
  <span class="andw-category andw-category-event">イベント</span>
  <span class="andw-category andw-category-important">重要</span>
</div>
```

#### SCFフィールド条件分岐の例

**リンクタイプによる分岐:**
```html
{if andw-link-type="none"}
    {title}
{/if}
{if andw-link-type="external"}
    <a href="{link_url}" target="{link_target}">{title}</a>
{/if}
```

**イベント日付による分岐:**
```html
{if andw-event-type="single"}
    {andw-event-single-date}
{/if}
{if andw-event-type="period"}
    {andw-event-start-date} - {andw-event-end-date}
{/if}
```

### CSS上書き

テーマの以下のパスにCSSファイルを配置すると、プラグインのデフォルトCSSを上書きできます：

```
/wp-content/themes/テーマ名/andw-news/レイアウト名.css
```

### Smart Custom Fields 対応フィールド

- `andw_news_pinned` - ピン留め設定
- `andw_link_type` - リンクタイプ（self/internal/external）
- `andw_internal_link` - 内部リンク投稿ID
- `andw_external_link` - 外部リンクURL
- `andw_link_target` - リンクターゲット
- `andw_event_type` - イベントタイプ
- その他イベント関連フィールド

## Installation

1. プラグインファイルを `/wp-content/plugins/andw-news/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューでプラグインを有効化
3. 「お知らせチェンジャー設定」メニューで設定を行う

## Frequently Asked Questions

### カスタム投稿タイプ「andw-news」が存在しない場合は？

このプラグインは既存の「andw-news」投稿タイプを前提としています。投稿タイプが存在しない場合は別途作成していただく必要があります。

### テンプレートが表示されない場合は？

プラグインの管理画面でテンプレートが正しく設定されているか確認してください。また、CSSが無効化されていないかも確認してください。

### Smart Custom Fieldsが必要ですか？

基本的な表示にはSCFは必須ではありませんが、イベント日付やリンク設定などの高度な機能を利用する場合は推奨されます。

## Screenshots

1. 管理画面のテンプレート管理
2. リストレイアウト表示例
3. カードレイアウト表示例
4. タブレイアウト表示例
5. Gutenbergブロックの設定画面

## Changelog

### 0.1.0

- 初回リリース
- 基本テンプレート機能
- ショートコード対応
- Gutenbergブロック対応
- 管理画面実装

## Upgrade Notice

### 0.1.0

初回リリースです。

## 開発者向け情報

### フィルターフック

プラグインでは以下のフィルターフックを提供予定です：

- `andw_news_query_args` - クエリ引数のフィルター
- `andw_news_post_data` - 投稿データのフィルター
- `andw_news_template_tokens` - テンプレートトークンのフィルター

### アクションフック

- `andw_news_before_render` - レンダリング前
- `andw_news_after_render` - レンダリング後

## License

このプラグインはGPLv2以降のライセンスの下で配布されています。

## Development

### Requirements

- WordPress 6.5以上
- PHP 7.4以上
- Smart Custom Fields（推奨）

### File Structure

```
andw-news/
├── andw-news.php              # メインプラグインファイル
├── includes/                  # PHPクラスファイル
│   ├── class-template-manager.php
│   ├── class-query-handler.php
│   ├── class-shortcode.php
│   ├── class-gutenberg-block.php
│   └── class-admin.php
├── assets/                    # CSS/JSアセット
│   ├── css/
│   ├── js/
│   └── block/
├── languages/                 # 翻訳ファイル
├── readme.txt                 # WordPress.org用readme
├── README.md                  # GitHub用README
└── uninstall.php             # アンインストール処理
```

### Security Features

- nonce + current_user_can() による権限チェック
- esc_html/esc_url/wp_kses による出力エスケープ
- 翻訳対応（Text Domain: andw-news）
- 接頭辞 andw_news_ による名前空間管理

### Contributing

1. このリポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/amazing-feature`)
3. 変更をコミット (`git commit -m 'Add some amazing feature'`)
4. ブランチにプッシュ (`git push origin feature/amazing-feature`)
5. プルリクエストを作成

## Support

- [Issues](https://github.com/yasuo3o3/andw-news/issues)
- [Documentation](https://docs.example.com/andw-news)

## Author

**yasuo3o3**
- Website: https://yasuo-o.xyz/
- GitHub: [@yasuo3o3](https://github.com/yasuo3o3)