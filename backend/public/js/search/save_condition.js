document.addEventListener('DOMContentLoaded', () => {
    const saveBtn = document.getElementById('save-search-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
            // 現在のURLパラメータを取得
            const params = new URLSearchParams(window.location.search);
            const data = Object.fromEntries(params.entries());

            // ★追加: CSRFトークンの存在チェック
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfTokenMeta) {
                console.error('CSRF token meta tag not found');
                alert('エラー: セキュリティトークンが見つかりません。\nブラウザを再読み込みしてみてください。');
                return;
            }
            const csrfToken = csrfTokenMeta.content;

            try {
                // ボタンをローディング状態にする
                const originalContent = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> 保存中...';
                if (window.lucide) lucide.createIcons();

                const response = await fetch('/api/saved-searches', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                });

                // JSONレスポンスかどうかを確認
                const contentType = response.headers.get("content-type");
                let result;
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    result = await response.json();
                } else {
                    throw new Error("サーバーエラーが発生しました（応答がJSONではありません）");
                }

                if (response.ok) {
                    alert('条件を保存しました！\n新着が入るとメールで通知されます。');
                    saveBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> 保存済み';
                    saveBtn.classList.replace('border-blue-600', 'border-green-600');
                    saveBtn.classList.replace('text-blue-600', 'text-green-600');
                } else {
                    alert(result.message || '保存に失敗しました。');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalContent; // 元に戻す
                }
                
                if (window.lucide) lucide.createIcons();

            } catch (e) {
                console.error(e);
                alert('エラーが発生しました: ' + e.message);
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'この条件を保存する'; // テキストを戻す
            }
        });
    }
});