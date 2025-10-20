/**
 * andW News Changer - 管理画面JavaScript
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
                                <th><label for="template-html">HTMLテンプレート</label></th>
                                <td>
                                    <textarea id="template-html" class="large-text code" rows="15" required>${templateData.html}</textarea>
                                    <p class="description">
                                        使用可能なトークン: {title}, {date}, {excerpt}, {thumbnail}, {event_date}, {link_url}, {link_target}
                                    </p>
                                </td>
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
     * テンプレートを保存
     */
    function saveTemplate(originalName, isEdit) {
        const data = {
            action: 'andw_news_save_template',
            original_name: originalName,
            template_name: $('#template-name').val(),
            template_data: {
                name: $('#template-name').val(),
                description: $('#template-description').val(),
                html: $('#template-html').val()
            },
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
        const newName = prompt('新しいテンプレート名を入力してください:', templateName + '_copy');
        if (!newName) return;

        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'andw_news_duplicate_template',
                source_name: templateName,
                new_name: newName,
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