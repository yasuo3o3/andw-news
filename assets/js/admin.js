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
                                <th><label for="template-wrapper-html">ラッパーHTML</label></th>
                                <td>
                                    <textarea id="template-wrapper-html" class="large-text code" rows="5" required>${templateData.wrapper_html || ''}</textarea>
                                    <p class="description">
                                        <strong>必須:</strong> {items} を含む必要があります。<br>
                                        <strong>例:</strong> &lt;ul class="news"&gt;{items}&lt;/ul&gt;
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="template-item-html">アイテムHTML</label></th>
                                <td>
                                    <textarea id="template-item-html" class="large-text code" rows="8" required>${templateData.item_html || ''}</textarea>
                                    <p class="description">
                                        各投稿に適用されるテンプレート。<br>
                                        <strong>例:</strong> &lt;li&gt;&lt;a href="{link_url}"&gt;{title}&lt;/a&gt;&lt;/li&gt;
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

                        <!-- 通知エリア -->
                        <div id="template-editor-notification" style="margin: 15px 0; display: none;"></div>

                        <p class="submit">
                            <button type="submit" class="button button-primary">保存</button>
                            <button type="button" class="button" id="cancel-edit">閉じる</button>
                        </p>
                    </form>
                </div>
            </div>
        `;

        $('body').append(html);

        // 保存完了フラグ
        let saveCompleted = false;

        // イベントハンドラー
        $('#template-editor-form').on('submit', function(e) {
            e.preventDefault();
            saveTemplate(templateName, isEdit, function() {
                saveCompleted = true;
            });
        });

        $('#cancel-edit').on('click', function() {
            $('#template-editor-modal').remove();
            // 保存が完了している場合はページをリロード
            if (saveCompleted) {
                location.reload();
            }
        });

        // ESCキーでモーダルを閉じる
        $(document).on('keyup.template-editor', function(e) {
            if (e.keyCode === 27) {
                $('#template-editor-modal').remove();
                $(document).off('keyup.template-editor');
                // ESCでも保存後はリロード
                if (saveCompleted) {
                    location.reload();
                }
            }
        });
    }


    /**
     * テンプレートを保存
     */
    function saveTemplate(originalName, isEdit, onSaveCallback) {
        const templateData = {
            name: $('#template-name').val(),
            description: $('#template-description').val(),
            wrapper_html: $('#template-wrapper-html').val(),
            item_html: $('#template-item-html').val()
        };

        const data = {
            action: 'andw_news_save_template',
            original_name: originalName,
            template_name: $('#template-name').val(),
            template_data: templateData,
            is_edit: isEdit,
            nonce: andwNewsAdmin.nonce
        };

        // 保存ボタンを無効化してローディング状態にする
        const $saveButton = $('#template-editor-form button[type="submit"]');
        const originalButtonText = $saveButton.text();
        $saveButton.prop('disabled', true).text('保存中...');

        $.ajax({
            url: andwNewsAdmin.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージをモーダル内に表示
                    showModalNotification('テンプレートを保存しました！', 'success');

                    // 保存完了コールバックを呼び出し
                    if (onSaveCallback) {
                        onSaveCallback();
                    }

                    // ボタンを「閉じる」に変更
                    $saveButton.prop('disabled', false).text('閉じる').removeClass('button-primary').addClass('button-secondary');
                    $('#cancel-edit').text('閉じる');

                    // 閉じるボタンのクリックイベントを再設定（ページリロード付き）
                    $saveButton.off('click').on('click', function() {
                        $('#template-editor-modal').remove();
                        location.reload();
                    });
                } else {
                    // エラーメッセージをモーダル内に表示
                    showModalNotification('保存に失敗しました: ' + (response.data.message || '不明なエラー'), 'error');
                    // ボタンを元に戻す
                    $saveButton.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function() {
                showModalNotification('保存に失敗しました。ネットワークエラーの可能性があります。', 'error');
                // ボタンを元に戻す
                $saveButton.prop('disabled', false).text(originalButtonText);
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
                    showNotification('テンプレートを複製しました', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('複製に失敗しました: ' + (response.data.message || '不明なエラー'), 'error');
                }
            },
            error: function() {
                showNotification('複製に失敗しました。ネットワークエラーの可能性があります。', 'error');
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
                    showNotification('テンプレートを削除しました', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('削除に失敗しました: ' + (response.data.message || '不明なエラー'), 'error');
                }
            },
            error: function() {
                showNotification('削除に失敗しました。ネットワークエラーの可能性があります。', 'error');
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
                    showNotification('デフォルトテンプレートを設定しました', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('設定に失敗しました: ' + (response.data.message || '不明なエラー'), 'error');
                }
            },
            error: function() {
                showNotification('設定に失敗しました。ネットワークエラーの可能性があります。', 'error');
            }
        });
    }

    /**
     * モーダル内通知メッセージを表示
     */
    function showModalNotification(message, type) {
        const $notificationArea = $('#template-editor-notification');

        // 通知タイプに応じたスタイルとアイコンを設定
        const bgColor = type === 'success' ? '#00a32a' : '#d63638';
        const icon = type === 'success' ? '✓' : '✗';

        // 通知HTML作成
        const notificationHtml = `
            <div style="
                background: ${bgColor};
                color: white;
                padding: 12px 16px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
                animation: slideDown 0.3s ease-out;
            ">
                <span style="font-size: 16px;">${icon}</span>
                <span>${message}</span>
            </div>
        `;

        // 既存の通知をクリアして新しい通知を表示
        $notificationArea.html(notificationHtml).show();

        // CSSアニメーションを追加（1回だけ）
        if (!$('#modal-notification-style').length) {
            $('head').append(`
                <style id="modal-notification-style">
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                </style>
            `);
        }
    }

    /**
     * 通知メッセージを表示（画面右上用）
     */
    function showNotification(message, type) {
        // 既存の通知を削除
        $('.andw-notification').remove();

        // 通知タイプに応じたクラスとアイコンを設定
        const typeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const icon = type === 'success' ? '✓' : '✗';

        // 通知要素を作成
        const notification = $(`
            <div class="andw-notification notice ${typeClass} is-dismissible" style="
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 100001;
                max-width: 400px;
                padding: 12px 16px;
                margin: 0;
                background: ${type === 'success' ? '#00a32a' : '#d63638'};
                color: white;
                border: none;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
            ">
                <span style="font-size: 16px;">${icon}</span>
                <span>${message}</span>
                <button type="button" class="notice-dismiss" style="
                    background: none;
                    border: none;
                    color: white;
                    cursor: pointer;
                    padding: 0;
                    margin-left: auto;
                    font-size: 18px;
                    line-height: 1;
                    width: 20px;
                    height: 20px;
                ">×</button>
            </div>
        `);

        // 通知をページに追加
        $('body').append(notification);

        // 閉じるボタンのイベント
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });

        // 成功メッセージは3秒後に自動で消す
        if (type === 'success') {
            setTimeout(function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    }

})(jQuery);