# andW News v2 簡素化計画

## 背景

現行の andW News はカスタム投稿タイプ＋テンプレートエンジン＋SCF連携と多機能だが、
カスタマイズ性を追求しすぎて逆に使わなくなってしまった。

v2 では **通常の「投稿（post）」に対して飛び先URLを変更できるようにし、
表示はWordPress標準のしくみ＋シンプルな独自ブロック1つ** で完結させる。

---

## コンセプト

- カスタム投稿タイプは使わない。通常の「投稿」を使う
- プラグインの役割は2つだけ:
  1. 投稿にリンク先を設定できるUIを提供する
  2. 新着一覧をブロックとして表示する（表示オプション付き）

---

## 機能一覧

### 1. リンク先変更機能

#### 投稿編集画面のメタボックス

| フィールド | 型 | 説明 |
|---|---|---|
| リンク先URL | text (URL) | 空欄なら通常のパーマリンク |
| 新しいタブで開く | checkbox | `_blank` で開くかどうか |

- メタキー: `andw_link_url`, `andw_link_target`
- 投稿編集画面のサイドバーまたはメタボックスとして表示

#### パーマリンクの差し替え

`post_link` フィルターで、`andw_link_url` が設定されている投稿のパーマリンクを差し替える。

```php
add_filter('post_link', function ($url, $post) {
    $custom_url = get_post_meta($post->ID, 'andw_link_url', true);
    if (!empty($custom_url) && !is_admin()) {
        return esc_url($custom_url);
    }
    return $url;
}, 10, 2);
```

**効果範囲:**
- `core/latest-posts` ブロック
- 「最近の投稿」ウィジェット
- 本プラグインの独自ブロック
- テーマのアーカイブ・検索結果
- `get_permalink()` を使うすべての箇所

**除外:**
- `is_admin()` の場合は差し替えない（管理画面での編集リンクを壊さないため）

---

### 2. 新着一覧ブロック

ブロック名: `andw-news/latest-posts`

#### ブロック設定（InspectorControls）

| 設定項目 | 型 | デフォルト | 説明 |
|---|---|---|---|
| レイアウト | select | `list` | `list` / `cards` / `tabs` |
| 表示件数 | number | 10 | 1〜50 |
| カテゴリーで絞り込み | multi-select | 全て | WordPress標準カテゴリー |
| カテゴリー表示 | toggle | ON | カテゴリーバッジを表示するか |
| タグ表示 | toggle | OFF | タグを表示するか |
| サムネイル表示 | toggle | ON | アイキャッチ画像を表示するか |
| タブ切り替え | toggle | OFF | ONのときカテゴリー別タブUIを表示 |
| ピン留め優先 | toggle | OFF | ピン留め投稿を先頭に |

#### レイアウト

**list（リスト）**
```
日付 | [カテゴリー] タイトル
日付 | [カテゴリー] タイトル
```

**cards（カード）**
```
┌──────────┐ ┌──────────┐
│ サムネイル │ │ サムネイル │
│ カテゴリー │ │ カテゴリー │
│ タイトル   │ │ タイトル   │
│ 抜粋       │ │ 抜粋       │
└──────────┘ └──────────┘
```

**tabs（タブ）**
```
[すべて] [ニュース] [イベント] [お知らせ]
─────────────────────────────────────
日付 | [カテゴリー] タイトル
日付 | [カテゴリー] タイトル
```

タブONのとき、選択されたカテゴリーがタブとして表示される。
「すべて」タブが先頭に自動追加される。

#### リンクの target 属性

ブロックの出力HTMLで、`andw_link_target` が `_blank` の投稿は
`<a>` タグに `target="_blank" rel="noopener noreferrer"` を付与する。

---

### 3. ピン留め機能

- 投稿編集画面のメタボックスにチェックボックスを追加
- メタキー: `andw_pinned`（`1` or 空）
- ブロック設定で「ピン留め優先」がONのとき、ピン留め投稿を先頭に表示

---

## 削除する機能（現行からの差分）

| 削除対象 | 理由 |
|---|---|
| カスタム投稿タイプ `andw-news` | 通常の投稿を使うため不要 |
| カスタムタクソノミー `andw_news_category` | WordPress標準カテゴリーを使うため不要 |
| テンプレートエンジン（トークン、条件分岐） | ブロックで固定レイアウトを提供するため不要 |
| テンプレート管理画面（CRUD、プレビュー） | 同上 |
| Smart Custom Fields（SCF）連携 | メタボックスを自前で用意するため不要 |
| ショートコード `[andw_news]` | ブロックに一本化 |
| イベント日付関連フィールド一式 | スコープ外。必要なら別プラグインで |
| トランジェントキャッシュ機構 | ブロック出力はWP標準キャッシュに任せる |
| CSS上書き機能（テーマ側ファイル検出） | シンプルなCSS構成にするため不要 |
| 管理画面のCSS無効化設定 | 同上 |
| デフォルトサムネイル設定 | ブロック内で placeholder 対応 |

---

## ファイル構成（v2）

```
andw-news/
├── andw-news.php              # メインファイル（フィルター登録、メタボックス）
├── includes/
│   └── class-block.php        # ブロックのサーバーサイドレンダリング
├── assets/
│   ├── css/
│   │   ├── list.css           # リストレイアウト
│   │   ├── cards.css          # カードレイアウト
│   │   └── tabs.css           # タブレイアウト
│   ├── js/
│   │   └── tabs.js            # タブ切り替え（フロント用）
│   └── block/
│       ├── block.json         # ブロックメタデータ
│       └── index.js           # ブロックエディタ（React）
├── uninstall.php              # メタデータのクリーンアップ
├── readme.txt
└── README.md
```

**現行から削除されるファイル:**
- `includes/class-post-type.php`
- `includes/class-template-manager.php`
- `includes/class-query-handler.php`（ロジックは class-block.php に統合）
- `includes/class-shortcode.php`
- `includes/class-admin.php`
- `includes/class-gutenberg-block.php`（class-block.php に置き換え）
- `assets/css/ul_list.css`
- `assets/js/admin.js`

---

## データマイグレーション

既存の `andw-news` カスタム投稿タイプの投稿がある場合：

1. 投稿タイプを `andw-news` → `post` に変更（`wp_posts.post_type`）
2. タクソノミー `andw_news_category` → `category` にマッピング
3. SCFメタキーを新しいメタキーに変換:
   - `andw-external-link` / `andw_external_link` → `andw_link_url`
   - `andw-link-target` / `andw_link_target` → `andw_link_target`
   - `andw-news-pinned` / `andw_news_pinned` → `andw_pinned`
4. 不要なメタデータの削除

**注意:** マイグレーションは管理画面にボタンとして用意し、ユーザーが明示的に実行する。
自動実行はしない。マイグレーション実行前にDBバックアップを促す。

---

## 実装ステップ

### Phase 1: コア機能
1. メインファイルを書き換え（CPT登録を削除、メタボックス追加）
2. `post_link` フィルター実装
3. 不要なクラスファイルを削除

### Phase 2: ブロック
4. `block.json` を新仕様で再定義
5. ブロックエディタ（React）を新規作成
6. サーバーサイドレンダリング（`class-block.php`）を実装
7. 3レイアウト（list / cards / tabs）のCSS整備

### Phase 3: タブUI
8. タブ切り替えJS（フロント用）を現行から流用・簡素化
9. カテゴリー別タブのサーバーサイドHTML生成

### Phase 4: マイグレーション & クリーンアップ
10. マイグレーションツール実装
11. `uninstall.php` を更新
12. README / readme.txt を更新

---

## 備考

- ショートコードは提供しない。ブロックに一本化することで管理コストを下げる
- テンプレートの自由編集は提供しない。デザイン変更はCSSで行う
- `post_link` フィルターにより、`core/latest-posts` など他のブロックでも飛び先が変わる。
  これは意図した動作であり、本プラグインの中心的な価値
