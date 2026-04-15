document.addEventListener('DOMContentLoaded', () => {
    const makerSelect = document.getElementById('select-maker');
    const modelSelect = document.getElementById('select-model');
    const yearSelect = document.getElementById('select-year');
    const mileageInput = document.getElementById('input-mileage');
    const calcBtn = document.getElementById('btn-calculate');
    const form = document.getElementById('sell-form');

    const resultArea = document.getElementById('result-area');
    const resultTitle = document.getElementById('result-title');
    const resultMin = document.getElementById('result-min');
    const resultMax = document.getElementById('result-max');
    const resultBasisText = document.getElementById('result-basis-text');
    const resultNote = document.getElementById('result-note');
    const resultDetails = document.getElementById('result-details');

    // 1. メーカー選択で車種リストを取得
    if (makerSelect) {
        makerSelect.addEventListener('change', async (e) => {
            const makerId = e.target.value;

            // リセット
            modelSelect.innerHTML = '<option value="">読み込み中...</option>';
            modelSelect.disabled = true;
            calcBtn.disabled = true;

            if (!makerId) {
                modelSelect.innerHTML = '<option value="">メーカーを選択してください</option>';
                return;
            }

            try {
                const response = await fetch(`/api/manufacturers/${makerId}/models-light`);
                const models = await response.json();

                if (models.length > 0) {
                    let html = '<option value="">車種を選択</option>';
                    models.forEach(m => {
                        html += `<option value="${m.id}">${m.name}</option>`;
                    });
                    modelSelect.innerHTML = html;
                    modelSelect.disabled = false;
                    modelSelect.classList.remove('text-gray-400', 'bg-gray-50');
                    modelSelect.classList.add('text-gray-900', 'bg-white');
                } else {
                    modelSelect.innerHTML = '<option value="">車種が見つかりません</option>';
                }
            } catch (error) {
                console.error('Failed to load models', error);
                modelSelect.innerHTML = '<option value="">エラーが発生しました</option>';
            }
        });
    }

    // 2. 車種選択でボタン有効化
    if (modelSelect) {
        modelSelect.addEventListener('change', (e) => {
            if (e.target.value) {
                calcBtn.disabled = false;
            } else {
                calcBtn.disabled = true;
            }
        });
    }

    // 3. 計算実行
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // ローディング表示
            const originalText = calcBtn.innerHTML;
            calcBtn.disabled = true;
            calcBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> 計算中...';
            if(window.lucide) lucide.createIcons();

            const data = {
                bike_model_id: modelSelect.value,
                year: yearSelect.value,
                mileage: mileageInput ? mileageInput.value : '',
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            try {
                const response = await fetch('/api/sell/calculate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // 結果の埋め込み
                    let title = `${result.maker_name} ${result.model_name}`;
                    if (result.year) title += ` (${result.year}年式)`;

                    resultTitle.textContent = title;
                    resultMin.textContent = result.purchase_min;
                    resultMax.textContent = result.purchase_max;

                    // 基準テキスト
                    if (result.data_count > 0 && result.base_sold_price > 0) {
                        resultBasisText.textContent = `${result.data_count}件の売却実績データから算出`;
                    } else {
                        resultBasisText.textContent = `市場平均価格 ${result.retail_avg}万円 から推計`;
                    }

                    // フォールバック注記
                    if (result.is_fallback) {
                        resultNote.classList.remove('hidden');
                    } else {
                        resultNote.classList.add('hidden');
                    }

                    // 算出根拠の詳細
                    if (result.factors && result.data_count > 0 && !result.is_fallback) {
                        resultDetails.classList.remove('hidden');

                        document.getElementById('detail-count').textContent = `${result.data_count}件`;

                        const avgPrice = result.base_sold_price > 0
                            ? `${(result.base_sold_price / 10000).toFixed(1)}万円`
                            : '-';
                        document.getElementById('detail-avg').textContent = avgPrice;

                        const f = result.factors;

                        // 売却日数
                        if (f.avg_days > 0) {
                            document.getElementById('detail-speed').textContent = `${f.avg_days}日（${f.speed_label}）`;
                            document.getElementById('detail-speed-row').classList.remove('hidden');
                        } else {
                            document.getElementById('detail-speed-row').classList.add('hidden');
                        }

                        // 走行距離補正
                        if (f.mileage_factor !== 1.00) {
                            document.getElementById('detail-mileage').textContent = `x${f.mileage_factor.toFixed(2)}`;
                            document.getElementById('detail-mileage-row').classList.remove('hidden');
                        } else {
                            document.getElementById('detail-mileage-row').classList.add('hidden');
                        }

                        // 年式補正
                        if (f.year_factor !== 1.00) {
                            document.getElementById('detail-year').textContent = `x${f.year_factor.toFixed(2)}`;
                            document.getElementById('detail-year-row').classList.remove('hidden');
                        } else {
                            document.getElementById('detail-year-row').classList.add('hidden');
                        }

                        // 信頼度
                        const confMap = { high: '★★★（高い）', medium: '★★☆（普通）', low: '★☆☆（低い）' };
                        document.getElementById('detail-confidence').textContent = confMap[result.confidence] || '-';
                    } else {
                        resultDetails.classList.add('hidden');
                    }

                    // 結果エリアを表示してスクロール
                    resultArea.classList.remove('hidden');
                    resultArea.classList.add('animate-in', 'fade-in', 'slide-in-from-bottom-8', 'duration-700');

                    setTimeout(() => {
                        resultArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);

                    if(window.lucide) lucide.createIcons();

                } else {
                    alert('申し訳ありません。データが不足しているため相場を算出できませんでした。');
                }

            } catch (error) {
                console.error(error);
                alert('エラーが発生しました。もう一度お試しください。');
            } finally {
                calcBtn.disabled = false;
                calcBtn.innerHTML = originalText;
                if(window.lucide) lucide.createIcons();
            }
        });
    }
});
