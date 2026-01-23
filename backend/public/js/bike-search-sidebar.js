/**
 * MotoHub Sidebar UI Logic
 * 車種やメーカーが変更された際、キーワードだけでなく
 * 価格・距離などのパラメータもリセットして「全件表示」に近い状態から再開させます。
 */

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    const keywordInput = filterForm?.querySelector('input[name="keyword"]');
    if (!filterForm) return;

    /**
     * すべてのフィルタ条件を初期状態（リセット）にする関数
     */
    const resetAllFilters = () => {
        // 1. キーワードをクリア
        if (keywordInput) {
            keywordInput.value = "";
        }

        // 2. スライダーをリセット（最小・最大値へ戻す）
        const sliders = filterForm.querySelectorAll('input[type="range"]');
        sliders.forEach(slider => {
            if (slider.classList.contains('range-min')) {
                slider.value = slider.getAttribute('min');
            } else if (slider.classList.contains('range-max')) {
                slider.value = slider.getAttribute('max');
            }
            // 表示（ラベルやプログレスバー）を更新するためにinputイベントを発火
            slider.dispatchEvent(new Event('input'));
        });
    };

    /**
     * フォーム送信時のクリーンアップ
     */
    const submitCleanForm = () => {
        const inputs = filterForm.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            const val = input.value;
            const name = input.name;

            // 空の値を無効化してURLを綺麗にする
            if (!val && name !== 'sort') {
                input.disabled = true;
                return;
            }

            // スライダーがデフォルト境界値（下限なし・上限なし）にある場合は除外
            if (input.type === 'range') {
                const minAttr = input.getAttribute('min');
                const maxAttr = input.getAttribute('max');
                
                if (input.classList.contains('range-min') && val === minAttr) {
                    input.disabled = true;
                }
                if (input.classList.contains('range-max') && val === maxAttr) {
                    input.disabled = true;
                }
            }
        });

        filterForm.submit();
    };

    /**
     * フィルタ変更時の処理
     * @param {boolean} shouldReset パラメータをリセットするかどうか
     */
    const handleFilterChange = (shouldReset = false) => {
        if (shouldReset) {
            resetAllFilters();
        }

        if (window.innerWidth >= 1024) {
            // PC版は即座に送信
            setTimeout(() => submitCleanForm(), 50);
        }
    };

    // --- デュアルレンジスライダーの初期化 (UI制御用) ---
    const initDualSlider = (containerId, minGap = 1) => {
        const container = document.getElementById(containerId);
        if (!container) return;

        const rangeInputs = container.querySelectorAll("input");
        const progress = container.querySelector(".slider-progress");
        const type = containerId.split('-')[1];
        const labelMin = document.getElementById(`label-min-${type}`);
        const labelMax = document.getElementById(`label-max-${type}`);

        const updateUI = (event) => {
            let minVal = parseInt(rangeInputs[0].value);
            let maxVal = parseInt(rangeInputs[1].value);
            const minLimit = parseInt(rangeInputs[0].min);
            const maxLimit = parseInt(rangeInputs[0].max);

            if (maxVal - minVal < minGap) {
                if (event && event.target.className.includes("range-min")) {
                    rangeInputs[0].value = maxVal - minGap;
                    minVal = maxVal - minGap;
                } else if (event) {
                    rangeInputs[1].value = minVal + minGap;
                    maxVal = minVal + minGap;
                }
            }

            const formatLabel = (val, type, isMin) => {
                if (isMin && val <= minLimit) return "下限なし";
                if (!isMin && val >= maxLimit) return "上限なし";
                const formattedNum = val.toLocaleString();
                if (type === 'price') return `${formattedNum}万円`;
                if (type === 'mileage') return `${formattedNum}km`;
                if (type === 'year') return `${val}年`;
                return val;
            };

            if (labelMin) labelMin.textContent = formatLabel(minVal, type, true);
            if (labelMax) labelMax.textContent = formatLabel(maxVal, type, false);

            const minPercent = (minVal - rangeInputs[0].min) / (rangeInputs[0].max - rangeInputs[0].min) * 100;
            const maxPercent = (maxVal - rangeInputs[1].min) / (rangeInputs[1].max - rangeInputs[1].min) * 100;
            progress.style.left = minPercent + "%";
            progress.style.width = (maxPercent - minPercent) + "%";
        };

        rangeInputs.forEach(input => {
            input.addEventListener("input", updateUI);
            input.addEventListener("change", () => handleFilterChange(false));
        });

        updateUI();
    };

    // --- メーカー・車種連動 ---
    const mSelect = document.getElementById('manufacturer-select');
    const modelSelect = document.getElementById('model-select');
    const modelContainer = document.getElementById('model-select-container');

    const updateModelList = async (selectedModelId = null) => {
        const mid = mSelect.value;
        if (!mid) {
            modelSelect.innerHTML = '<option value="">すべての車種</option>';
            modelSelect.disabled = true;
            modelContainer?.classList.add('opacity-40');
            return;
        }
        modelSelect.disabled = false;
        modelContainer?.classList.remove('opacity-40');

        try {
            const res = await fetch(`/api/manufacturers/${mid}/models`);
            const models = await res.json();
            let html = '<option value="">すべての車種</option>';
            models.forEach(m => {
                const isSelected = String(m.id) === String(selectedModelId) ? 'selected' : '';
                html += `<option value="${m.id}" ${isSelected}>${m.name}</option>`;
            });
            modelSelect.innerHTML = html;
        } catch (e) { console.error(e); }
    };

    // ✨ メーカー変更時：条件を完全にリセットする
    mSelect?.addEventListener('change', () => {
        if (window.innerWidth >= 1024) {
            handleFilterChange(true);
        } else {
            // スマホ版は車種リストだけ更新（パラメータのリセットは車種決定時に行う）
            resetAllFilters();
            updateModelList(null);
        }
    });

    // ✨ 車種変更時：条件を完全にリセットする
    modelSelect?.addEventListener('change', () => {
        handleFilterChange(true);
    });

    const filterSelectors = ['select[name="prefecture"]', 'input[name="is_new"]', 'input[name="has_repair_history"]'];
    filterSelectors.forEach(selector => {
        filterForm.querySelectorAll(selector).forEach(input => {
            input.addEventListener('change', () => handleFilterChange(false));
        });
    });

    // 初期化
    initDualSlider("slider-price", 5);
    initDualSlider("slider-mileage", 2000);
    initDualSlider("slider-year", 1);

    if (mSelect && mSelect.value && modelSelect && modelSelect.options.length <= 1) {
        updateModelList(modelSelect.dataset.selectedId);
    }
});