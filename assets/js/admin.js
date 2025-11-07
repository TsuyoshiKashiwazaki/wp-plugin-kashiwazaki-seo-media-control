jQuery(document).ready(function ($) {
    let selectedItems = [];
    let isEditing = false;

    // 編集可能フィールドのクリックイベント
    $(document).on('click', '.ksmc-editable', function (e) {
        e.preventDefault();

        if (isEditing) return;

        const $cell = $(this);
        const field = $cell.data('field');
        const mediaId = $cell.data('media-id');
        const $display = $cell.find('.ksmc-display');
        const $input = $cell.find('.ksmc-input');

        if (!field || !mediaId || $display.length === 0 || $input.length === 0) {
            return;
        }

        startEdit($cell, $display, $input, field, mediaId);
    });

    function startEdit($cell, $display, $input, field, mediaId) {
        isEditing = true;
        $cell.addClass('ksmc-editing');
        $display.hide();
        $input.show().focus();

        if ($input.is('textarea')) {
            $input.css('height', Math.max(60, $input[0].scrollHeight) + 'px');
        }

        $input.on('keydown.ksmc', function (e) {
            if (e.keyCode === 27) { // Escape
                e.preventDefault();
                cancelEdit($cell, $display, $input);
            } else if (e.keyCode === 13) { // Enter
                if ($input.is('textarea') && !e.ctrlKey) {
                    return;
                }
                e.preventDefault();
                const newValue = $input.val();
                saveField($cell, $display, $input, field, mediaId, newValue);
            }
        });

        $input.on('blur.ksmc', function () {
            const newValue = $input.val();
            const originalValue = $display.text().trim();

            if (newValue !== originalValue) {
                saveField($cell, $display, $input, field, mediaId, newValue);
            } else {
                cancelEdit($cell, $display, $input);
            }
        });
    }

    function cancelEdit($cell, $display, $input) {
        isEditing = false;
        $cell.removeClass('ksmc-editing');
        $input.hide().off('.ksmc');
        $display.show();
    }

    function saveField($cell, $display, $input, field, mediaId, newValue) {
        $cell.removeClass('ksmc-editing').addClass('ksmc-saving');
        $input.prop('disabled', true).off('.ksmc');

        $.ajax({
            url: ksmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ksmc_update_field',
                nonce: ksmc_ajax.nonce,
                attachment_id: mediaId,
                field: field,
                value: newValue
            },
            success: function (response) {
                if (response.success) {
                    $display.text(newValue);
                    showMessage(response.data.message || '更新しました', 'success');
                } else {
                    let errorMsg = response.data ? response.data.message : '更新に失敗しました';
                    if (response.data && response.data.details) {
                        errorMsg += '\n\n詳細: ' + response.data.details;
                    }
                    showMessage(errorMsg, 'error');
                    $cell.addClass('ksmc-error');
                    setTimeout(() => $cell.removeClass('ksmc-error'), 3000);
                }
            },
            error: function (xhr, status, error) {
                logErrorToServer('フィールド更新エラー', status + ': ' + error, 'HTTP ' + xhr.status);

                let errorMsg = '通信エラーが発生しました';
                if (xhr.status) {
                    errorMsg += ' (HTTP ' + xhr.status + ')';
                }
                showMessage(errorMsg, 'error');
                $cell.addClass('ksmc-error');
                setTimeout(() => $cell.removeClass('ksmc-error'), 3000);
            },
            complete: function () {
                isEditing = false;
                $cell.removeClass('ksmc-saving');
                $input.prop('disabled', false).hide();
                $display.show();
            }
        });
    }

    // 所有者変更
    $(document).on('change', '.ksmc-owner-dropdown', function () {
        const $select = $(this);
        const mediaId = $select.data('media-id');
        const newOwnerId = $select.val();
        const originalValue = $select.data('original-value');

        if (newOwnerId === originalValue) return;

        if (!confirm('この媒体の所有者を変更しますか？')) {
            $select.val(originalValue);
            return;
        }

        $select.prop('disabled', true);

        // デバッグ情報をサーバーに送信（開発中のみ）
        // logErrorToServer('所有者変更開始', 'メディアID: ' + mediaId + ', 新所有者ID: ' + newOwnerId, 'AJAX URL: ' + ksmc_ajax.ajax_url);

        $.ajax({
            url: ksmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ksmc_change_owner',
                nonce: ksmc_ajax.nonce,
                attachment_id: mediaId,
                new_owner_id: newOwnerId
            },
            success: function (response) {
                // レスポンスの型をチェック
                if (typeof response === 'string') {
                    // HTMLが返された場合（通常はPHPエラーやWordPress認証の問題）
                    showMessage('所有者の変更に失敗しました\n\nサーバー側でエラーが発生している可能性があります', 'error');
                    $select.val(originalValue);
                    return;
                }

                if (response && response.success) {
                    showMessage(response.data.message || '所有者を変更しました', 'success');
                    $select.data('original-value', newOwnerId);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    let errorMsg = '所有者の変更に失敗しました';
                    if (response && response.data && response.data.message) {
                        errorMsg += '\n\n' + response.data.message;
                    }
                    showMessage(errorMsg, 'error');
                    $select.val(originalValue);
                }
            },
            error: function (xhr, status, error) {
                logErrorToServer('所有者変更通信エラー', status + ': ' + error, 'HTTP ' + xhr.status + ', URL: ' + ksmc_ajax.ajax_url);

                let errorMsg = '所有者変更で通信エラーが発生しました';
                if (xhr.status) {
                    errorMsg += ' (HTTP ' + xhr.status + ')';
                }
                showMessage(errorMsg, 'error');
                $select.val(originalValue);
            },
            complete: function () {
                $select.prop('disabled', false);
            }
        });
    });

    // 所有者セレクトの初期値を記録
    $('.ksmc-owner-dropdown').each(function () {
        $(this).data('original-value', $(this).val());
    });

    function showMessage(message, type) {
        const $existing = $('.ksmc-message');
        if ($existing.length) {
            $existing.remove();
        }

        // 改行文字をHTMLの改行タグに変換し、HTMLエスケープを適用
        const escapedMessage = $('<div>').text(message).html().replace(/\n/g, '<br>');

        const cssClass = type === 'success' ? 'notice-success' : 'notice-error';
        const $message = $('<div class="notice ' + cssClass + ' is-dismissible ksmc-message"><p>' + escapedMessage + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">この通知を閉じる</span></button></div>');

        $('.wrap h1').after($message);

        // 手動で閉じるボタンのイベント
        $message.find('.notice-dismiss').on('click', function () {
            $message.fadeOut();
        });

        // 成功メッセージは10秒後、エラーメッセージは手動で閉じるまで表示
        if (type === 'success') {
            setTimeout(function () {
                if ($message.is(':visible')) {
                    $message.fadeOut();
                }
            }, 10000);
        }
        // エラーメッセージは手動で閉じるまで表示し続ける
    }

    // 一括選択機能
    $('#ksmc-select-all').on('change', function () {
        $('.ksmc-media-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectedItems();
    });

    $('.ksmc-media-checkbox').on('change', function () {
        updateSelectedItems();
        updateSelectAllCheckbox();
    });

    function updateSelectedItems() {
        selectedItems = [];
        $('.ksmc-media-checkbox:checked').each(function () {
            selectedItems.push($(this).val());
        });
        updateBulkActionsState();
    }

    function updateSelectAllCheckbox() {
        // 表示中のチェックボックスのみを対象にする
        const $visibleCheckboxes = $('.ksmc-media-table tbody tr:visible .ksmc-media-checkbox');
        const total = $visibleCheckboxes.length;
        const checked = $visibleCheckboxes.filter(':checked').length;

        if (total === 0) {
            $('#ksmc-select-all').prop('indeterminate', false).prop('checked', false);
        } else {
            $('#ksmc-select-all').prop('indeterminate', checked > 0 && checked < total);
            $('#ksmc-select-all').prop('checked', checked === total);
        }
    }

    function updateBulkActionsState() {
        const $button = $('#ksmc-bulk-change');
        const $count = $('.ksmc-selected-count');

        if (selectedItems.length > 0) {
            $button.prop('disabled', false);
            if ($count.length === 0) {
                $button.after('<span class="ksmc-selected-count">' + selectedItems.length + '個選択中</span>');
            } else {
                $count.text(selectedItems.length + '個選択中');
            }
        } else {
            $button.prop('disabled', true);
            $count.remove();
        }
    }

    // 一括変更
    $('#ksmc-bulk-change').on('click', function () {
        if (selectedItems.length === 0) {
            showMessage('メディアを選択してください', 'error');
            return;
        }

        const newOwner = $('#ksmc-bulk-owner').val();

        if (!newOwner) {
            showMessage('新しい所有者を選択してください', 'error');
            return;
        }

        if (!confirm('選択したメディアの所有者を変更しますか？\n\n' + selectedItems.length + '個のメディアを処理します。')) {
            return;
        }

        const $button = $(this);
        const originalText = $button.text();

        // 大量処理の場合はバッチ処理を実行
        if (selectedItems.length > 100) {
            processBulkChangeBatch(selectedItems, newOwner, $button, originalText);
        } else {
            processBulkChangeSimple(selectedItems, newOwner, $button, originalText);
        }
    });

    // 通常の一括変更（100個以下）
    function processBulkChangeSimple(items, newOwner, $button, originalText) {
        $button.text('変更中...').prop('disabled', true);

        $.ajax({
            url: ksmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ksmc_bulk_change_owner',
                nonce: ksmc_ajax.nonce,
                attachment_ids: items,
                new_owner_id: newOwner
            },
            success: function (response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    let errorMsg = response.data ? response.data.message : '一括変更に失敗しました';
                    if (response.data && response.data.details) {
                        errorMsg += '\n\n詳細: ' + response.data.details;
                    }
                    showMessage(errorMsg, 'error');
                }
            },
            error: function (xhr, status, error) {
                let errorMsg = '一括変更で通信エラーが発生しました';
                if (xhr.status) {
                    errorMsg += ' (HTTP ' + xhr.status + ')';
                }
                showMessage(errorMsg, 'error');
            },
            complete: function () {
                $button.text(originalText).prop('disabled', false);
            }
        });
    }

    // バッチ処理（100個ずつ）
    function processBulkChangeBatch(items, newOwner, $button, originalText) {
        const batchSize = 100;
        const totalItems = items.length;
        const totalBatches = Math.ceil(totalItems / batchSize);
        let currentBatch = 0;
        let successCount = 0;
        let errorCount = 0;

        $button.prop('disabled', true);

        function processBatch() {
            if (currentBatch >= totalBatches) {
                // 全バッチ完了
                $button.text(originalText).prop('disabled', false);
                const message = totalItems + '個のメディア処理が完了しました\n\n' +
                    '成功: ' + successCount + '個\n' +
                    'エラー: ' + errorCount + '個';
                showMessage(message, errorCount === 0 ? 'success' : 'error');
                setTimeout(() => location.reload(), 2000);
                return;
            }

            const start = currentBatch * batchSize;
            const end = Math.min(start + batchSize, totalItems);
            const batchItems = items.slice(start, end);

            currentBatch++;
            $button.text('処理中... (' + currentBatch + '/' + totalBatches + ')');

            $.ajax({
                url: ksmc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ksmc_bulk_change_owner',
                    nonce: ksmc_ajax.nonce,
                    attachment_ids: batchItems,
                    new_owner_id: newOwner
                },
                success: function (response) {
                    if (response.success) {
                        successCount += batchItems.length;
                    } else {
                        errorCount += batchItems.length;
                        console.error('バッチ ' + currentBatch + ' エラー:', response);
                    }
                    // 次のバッチを処理
                    setTimeout(processBatch, 500); // 0.5秒待機
                },
                error: function (xhr, status, error) {
                    errorCount += batchItems.length;
                    console.error('バッチ ' + currentBatch + ' 通信エラー:', status, error);
                    // 次のバッチを処理
                    setTimeout(processBatch, 500);
                }
            });
        }

        processBatch();
    }



    // フィルタ機能
    let currentFilter = '';

    $('#ksmc-apply-filter').on('click', function () {
        applyOwnerFilter();
    });

    $('#ksmc-clear-filter').on('click', function () {
        clearOwnerFilter();
    });

    // Enterキーでフィルタ適用
    $('#ksmc-filter-owner').on('keypress', function (e) {
        if (e.which === 13) {
            applyOwnerFilter();
        }
    });

    function applyOwnerFilter() {
        const filterValue = $('#ksmc-filter-owner').val();
        const filterText = $('#ksmc-filter-owner option:selected').text();

        currentFilter = filterValue;

        if (filterValue === '') {
            clearOwnerFilter();
            return;
        }

        let visibleCount = 0;
        let totalCount = 0;

        $('.ksmc-media-table tbody tr').each(function () {
            totalCount++;
            const $row = $(this);
            const ownerSelect = $row.find('.ksmc-owner-dropdown');

            if (ownerSelect.length > 0) {
                const currentOwner = ownerSelect.val();
                if (currentOwner === filterValue) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                    // 非表示の行のチェックボックスはオフにする
                    $row.find('.ksmc-media-checkbox').prop('checked', false);
                }
            }
        });

        // フィルタステータスを更新
        $('#ksmc-filter-status').text(`${filterText}のメディア: ${visibleCount}件 (全${totalCount}件中)`);

        // チェックボックス状態を更新
        updateSelectedItems();
        updateSelectAllCheckbox();
    }

    function clearOwnerFilter() {
        currentFilter = '';
        $('.ksmc-media-table tbody tr').show();
        $('#ksmc-filter-owner').val('');
        $('#ksmc-filter-status').text('');

        // チェックボックス状態を更新
        updateSelectedItems();
        updateSelectAllCheckbox();
    }

    // 全選択を表示中のアイテムのみに制限
    $('#ksmc-select-all').off('change').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.ksmc-media-table tbody tr:visible .ksmc-media-checkbox').prop('checked', isChecked);
        updateSelectedItems();
    });

    // スマート変更（フィルタ中の全メディアを変更）
    $('#ksmc-smart-change').on('click', function () {
        if (!currentFilter) {
            showMessage('まず所有者でフィルタを適用してから、スマート変更を使用してください', 'error');
            return;
        }

        const newOwner = $('#ksmc-bulk-owner').val();
        if (!newOwner) {
            showMessage('変更先の所有者を選択してください', 'error');
            return;
        }

        if (currentFilter === newOwner) {
            showMessage('変更元と変更先の所有者が同じです', 'error');
            return;
        }

        // 表示中のすべてのメディアIDを取得
        const visibleItems = [];
        $('.ksmc-media-table tbody tr:visible').each(function () {
            const checkbox = $(this).find('.ksmc-media-checkbox');
            if (checkbox.length > 0) {
                visibleItems.push(checkbox.val());
            }
        });

        if (visibleItems.length === 0) {
            showMessage('変更対象のメディアがありません', 'error');
            return;
        }

        const filterText = $('#ksmc-filter-owner option:selected').text();
        const newOwnerText = $('#ksmc-bulk-owner option:selected').text();

        if (!confirm(`${filterText}のメディア${visibleItems.length}個を${newOwnerText}に変更しますか？`)) {
            return;
        }

        const $button = $(this);
        const originalText = $button.text();

        // バッチ処理または通常処理
        if (visibleItems.length > 100) {
            processBulkChangeBatch(visibleItems, newOwner, $button, originalText);
        } else {
            processBulkChangeSimple(visibleItems, newOwner, $button, originalText);
        }
    });

    // 初期化
    updateBulkActionsState();


});
