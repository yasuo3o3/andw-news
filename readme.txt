=== andW News Changer ===
Contributors: yasuo3o3
Tags: news, custom-post-type, template, layout, shortcode
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

カスタム投稿タイプ「andw-news」の記事を様々なテンプレートで表示するプラグイン

== Description ==

andW News Changerは、カスタム投稿タイプ「andw-news」の記事を柔軟なテンプレートシステムで表示できるWordPressプラグインです。

= 主な機能 =

* 複数のレイアウトテンプレート（リスト、カード、タブ）
* テンプレートの管理（作成、編集、複製、削除）
* ショートコード対応
* Gutenbergブロック対応
* Smart Custom Fields（SCF）連携
* テーマCSS上書き機能
* ピン留め・優先表示機能

= 使用方法 =

**ショートコード:**
`[andw_news layout="cards" per_page="10"]`

**利用可能な属性:**
* `layout` - テンプレート名（list, cards, tabs, tabs_by_category等）
* `cats` - カテゴリID（カンマ区切り）
* `per_page` - 表示件数
* `pinned_first` - ピン留め優先表示（1 or 0）
* `exclude_expired` - 期限切れ除外（1 or 0）

**Gutenbergブロック:**
ブロックエディタで「andW News List」ブロックを検索してご利用ください。

= テンプレートカスタマイズ =

プラグインの管理画面「お知らせチェンジャー設定」から、HTMLテンプレートを自由に編集できます。

**利用可能なトークン:**
* `{title}` - 記事タイトル
* `{date}` - 投稿日
* `{excerpt}` - 抜粋
* `{thumbnail}` - サムネイル画像
* `{event_date}` - イベント日付（SCFフィールド）
* `{link_url}` - リンクURL
* `{link_target}` - リンクターゲット
* `{andw_subcontents}` - サブコンテンツ（SCFフィールド）
* `{andw_link_type}` - リンクタイプ（SCFフィールド）
* その他の`andw_`または`andw-`プレフィックスのSCFフィールド

= CSS上書き =

テーマの以下のパスにCSSファイルを配置すると、プラグインのデフォルトCSSを上書きできます：
`/wp-content/themes/テーマ名/andw-news-changer/レイアウト名.css`

= Smart Custom Fields 対応フィールド =

* `andw_news_pinned` - ピン留め設定
* `andw_link_type` - リンクタイプ（self/internal/external）
* `andw_internal_link` - 内部リンク投稿ID
* `andw_external_link` - 外部リンクURL
* `andw_link_target` - リンクターゲット
* `andw_event_type` - イベントタイプ
* その他イベント関連フィールド

== Installation ==

1. プラグインファイルを `/wp-content/plugins/andw-news-changer/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューでプラグインを有効化
3. 「お知らせチェンジャー設定」メニューで設定を行う

== Frequently Asked Questions ==

= カスタム投稿タイプ「andw-news」が存在しない場合は？ =

このプラグインは既存の「andw-news」投稿タイプを前提としています。投稿タイプが存在しない場合は別途作成していただく必要があります。

= テンプレートが表示されない場合は？ =

プラグインの管理画面でテンプレートが正しく設定されているか確認してください。また、CSSが無効化されていないかも確認してください。

= Smart Custom Fieldsが必要ですか？ =

基本的な表示にはSCFは必須ではありませんが、イベント日付やリンク設定などの高度な機能を利用する場合は推奨されます。

== Screenshots ==

1. 管理画面のテンプレート管理
2. リストレイアウト表示例
3. カードレイアウト表示例
4. タブレイアウト表示例
5. Gutenbergブロックの設定画面

== Changelog ==

= 0.0.1 =
* 初回リリース
* 基本テンプレート機能
* ショートコード対応
* Gutenbergブロック対応
* 管理画面実装

== Upgrade Notice ==

= 0.0.1 =
初回リリースです。

== 開発者向け情報 ==

= フィルターフック =

プラグインでは以下のフィルターフックを提供予定です：
* `andw_news_query_args` - クエリ引数のフィルター
* `andw_news_post_data` - 投稿データのフィルター
* `andw_news_template_tokens` - テンプレートトークンのフィルター

= アクションフック =

* `andw_news_before_render` - レンダリング前
* `andw_news_after_render` - レンダリング後

== License ==

このプラグインはGPLv2以降のライセンスの下で配布されています。