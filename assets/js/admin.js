/**
 * andW News - 管理画面JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initTemplatePreview();
        initTemplateActions();
    });

    /**
     * テンプレートプレビュー機能を初期化
     */
    function initTemplatePreview() {
        $('#template-select').on('change', function() {
            const templateName = $(this).val();
            if (templateName) {
                loadTemplatePreview(templateName);
            } else {
                $('#preview-area').html('<p>テンプレートを選択してください。</p>');
            }
        });
    }

    /**
     * テンプレートアクション機能を初期化
     */
    function initTemplateActions() {
        // 新規作成
        $('#create-template').on('click', function() {
            showTemplateEditor('', {
                name: '',
                html: '',
                description: ''
            });
        });

        // 複製
        $('#duplicate-template').on('click', function() {
            const templateName = $('#template-select').val();
            if (!templateName) {
                alert('複製するテンプレートを選択してください。');
                return;
            }
            duplicateTemplate(templateName);
        });

        // 削除
        $('#delete-template').on('click', function() {
            const templateName = $('#template-select').val();
            if (!templateName) {
                alert('削除するテンプレートを選択してください。');
                return;
            }
            if (confirm('本当に「' + templateName + '」テンプレートを削除しますか？')) {
                deleteTemplate(templateName);
            }
        });

        // デフォルトに設定
        $('#set-default').on('click', function() {
            const templateName = $('#template-select').val();
            if (!templateName) {
                alert('デフォルトに設定するテンプレートを選択してください。');
                return;
            }
            setDefaultTemplate(templateName);
        });

        // 編集
        $('#edit-template').on('click', function() {
            const templateName = $('#template-select').val();
            if (!templateName) {
                alert('編集するテンプレートを選択してください。');
                return;
            }
            editTemplate(templateName);
        });
    }

    /**
     * テンプレートプレビューを読み込み
     */
    function loadTemplatePreview(templateName) {
        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'andw_news_preview_template',
                template_name: templateName,
                nonce: andwNewsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#preview-area').html(response.data.html);
                } else {
                    $('#preview-area').html('<p style="color: red;">プレビューの読み込みに失敗しました。</p>');
                }
            },
            error: function() {
                $('#preview-area').html('<p style="color: red;">プレビューの読み込みに失敗しました。</p>');
            }
        });
    }

    /**
     * テンプレートエディタを表示
     */
    function showTemplateEditor(templateName, templateData) {
        const isEdit = templateName !== '';
        const title = isEdit ? 'テンプレート編集: ' + templateName : '新規テンプレート作成';

        const html = `
            <div id="template-editor-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    background: white;
                    width: 90%;
                    max-width: 800px;
                    max-height: 90%;
                    padding: 20px;
                    border-radius: 8px;
                    overflow-y: auto;
                ">
                    <h2>${title}</h2>
                    <form id="template-editor-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="template-name">テンプレート名</label></th>
                                <td><input type="text" id="template-name" value="${templateData.name}" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="template-description">説明</label></th>
                                <td><textarea id="template-description" class="large-text" rows="2">${templateData.description}</textarea></td>
                            </tr>
                            <tr>
                                <th><label for="template-type">テンプレートタイプ</label></th>
                                <td>
                                    <select id="template-type" class="regular-text">
                                        <option value="new" ${templateData.wrapper_html ? 'selected' : ''}>新形式（推奨）</option>
                                        <option value="legacy" ${templateData.html && !templateData.wrapper_html ? 'selected' : ''}>従来形式</option>
                                    </select>
                                    <p class="description">新形式は複数投稿の表示で正しいHTML構造を生成します。</p>
                                </td>
                            </tr>
                            <tr id="wrapper-html-row">
                                <th><label for="template-wrapper-html">ラッパーHTML</label></th>
                                <td>
                                    <textarea id="template-wrapper-html" class="large-text code" rows="5">${templateData.wrapper_html || ''}</textarea>
                                    <p class="description">
                                        <strong>必須:</strong> {items} を含む必要があります。<br>
                                        <strong>例:</strong> &lt;ul class="news"&gt;{items}&lt;/ul&gt;
                                    </p>
                                </td>
                            </tr>
                            <tr id="item-html-row">
                                <th><label for="template-item-html">アイテムHTML</label></th>
                                <td>
                                    <textarea id="template-item-html" class="large-text code" rows="8">${templateData.item_html || ''}</textarea>
                                    <p class="description">
                                        各投稿に適用されるテンプレート。<br>
                                        <strong>例:</strong> &lt;li&gt;&lt;a href="{link_url}"&gt;{title}&lt;/a&gt;&lt;/li&gt;
                                    </p>
                                </td>
                            </tr>
                            <tr id="legacy-html-row">
                                <th><label for="template-html">HTMLテンプレート（従来形式）</label></th>
                                <td>
                                    <textarea id="template-html" class="large-text code" rows="15">${templateData.html || ''}</textarea>
                                    <p class="description">
                                        <strong>警告:</strong> 複数投稿で不正なHTML構造が生成される可能性があります。
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="2">
                                    <p class="description">
                                        <strong>基本トークン:</strong> {title}, {date}, {excerpt}, {thumbnail}, {event_date}, {link_url}, {link_target}<br>
                                        <strong>SCFフィールド:</strong> {andw-news-pinned}, {andw-link-type}, {andw-internal-link}, {andw-external-link}, {andw-link-target}, {andw-event-type}, {andw-event-single-date}, {andw-event-start-date}, {andw-event-end-date}, {andw-event-free-text}, {andw-subcontents} 等<br>
                                        <strong>条件分岐:</strong> {if field_name}内容{/if}, {ifnot field_name}内容{/ifnot}, {if field_name="value"}内容{else}別の内容{/if}
                                    </p>
                                </th>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">保存</button>
                            <button type="button" class="button" id="cancel-edit">キャンセル</button>
                        </p>
                    </form>
                </div>
            </div>
        `;

        $('body').append(html);

        // テンプレートタイプ切り替えハンドラー
        $('#template-type').on('change', function() {
            toggleTemplateFields($(this).val());
        });

        // 初期状態設定
        toggleTemplateFields($('#template-type').val());

        // イベントハンドラー
        $('#template-editor-form').on('submit', function(e) {
            e.preventDefault();
            saveTemplate(templateName, isEdit);
        });

        $('#cancel-edit').on('click', function() {
            $('#template-editor-modal').remove();
        });

        // ESCキーでモーダルを閉じる
        $(document).on('keyup.template-editor', function(e) {
            if (e.keyCode === 27) {
                $('#template-editor-modal').remove();
                $(document).off('keyup.template-editor');
            }
        });
    }

    /**
     * テンプレートフィールドの表示切り替え
     */
    function toggleTemplateFields(templateType) {
        if (templateType === 'new') {
            $('#wrapper-html-row').show();
            $('#item-html-row').show();
            $('#legacy-html-row').hide();
            $('#template-wrapper-html').attr('required', true);
            $('#template-item-html').attr('required', true);
            $('#template-html').attr('required', false);
        } else {
            $('#wrapper-html-row').hide();
            $('#item-html-row').hide();
            $('#legacy-html-row').show();
            $('#template-wrapper-html').attr('required', false);
            $('#template-item-html').attr('required', false);
            $('#template-html').attr('required', true);
        }
    }

    /**
     * テンプレートを保存
     */
    function saveTemplate(originalName, isEdit) {
        const templateType = $('#template-type').val();
        const templateData = {
            name: $('#template-name').val(),
            description: $('#template-description').val()
        };

        // テンプレートタイプに応じてデータを追加
        if (templateType === 'new') {
            templateData.wrapper_html = $('#template-wrapper-html').val();
            templateData.item_html = $('#template-item-html').val();
        } else {
            templateData.html = $('#template-html').val();
        }

        const data = {
            action: 'andw_news_save_template',
            original_name: originalName,
            template_name: $('#template-name').val(),
            template_data: templateData,
            is_edit: isEdit,
            nonce: andwNewsAdmin.nonce
        };

        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#template-editor-modal').remove();
                    location.reload(); // 簡易的にページをリロード
                } else {
                    alert('保存に失敗しました: ' + (response.data.message || '不明なエラー'));
                }
            },
            error: function() {
                alert('保存に失敗しました。');
            }
        });
    }

    /**
     * テンプレートを編集
     */
    function editTemplate(templateName) {
        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'andw_news_get_template',
                template_name: templateName,
                nonce: andwNewsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showTemplateEditor(templateName, response.data);
                } else {
                    alert('テンプレートの読み込みに失敗しました。');
                }
            },
            error: function() {
                alert('テンプレートの読み込みに失敗しました。');
            }
        });
    }

    /**
     * テンプレートを複製
     */
    function duplicateTemplate(templateName) {
        const displayName = prompt('新しいテンプレート名を入力してください:', templateName + ' のコピー');
        if (!displayName) return;

        // 内部キーを英数字で生成（表示名とは別）
        const timestamp = Date.now();
        const internalKey = templateName + '_copy_' + timestamp;

        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'andw_news_duplicate_template',
                source_name: templateName,
                new_name: internalKey,
                display_name: displayName,
                nonce: andwNewsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('複製に失敗しました: ' + (response.data.message || '不明なエラー'));
                }
            },
            error: function() {
                alert('複製に失敗しました。');
            }
        });
    }

    /**
     * テンプレートを削除
     */
    function deleteTemplate(templateName) {
        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'andw_news_delete_template',
                template_name: templateName,
                nonce: andwNewsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('削除に失敗しました: ' + (response.data.message || '不明なエラー'));
                }
            },
            error: function() {
                alert('削除に失敗しました。');
            }
        });
    }

    /**
     * デフォルトテンプレートを設定
     */
    function setDefaultTemplate(templateName) {
        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'andw_news_set_default_template',
                template_name: templateName,
                nonce: andwNewsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('デフォルトテンプレートを設定しました。');
                    location.reload();
                } else {
                    alert('設定に失敗しました: ' + (response.data.message || '不明なエラー'));
                }
            },
            error: function() {
                alert('設定に失敗しました。');
            }
        });
    }

})(jQuery);