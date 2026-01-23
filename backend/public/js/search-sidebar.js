/**
 * MotoHub Sidebar UI Logic
 * 車種やメーカーが変更された際、キーワードだけでなく
 * 価格・距離などのパラメータもリセットして「全件表示」に近い状態から再開させます。
 * また、スマホ版ではあらゆるフィルタ変更時に「適用ボタン」のヒット件数を同期します。
 */

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    const keywordInput = filterForm?.querySelector('input[name="keyword"]');
    const mobileHitCount = document.getElementById('mobile-hit-count');
    let countTimer;

    if (!filterForm) return;

    /**
     * スマートフォン用：条件一致件数の非同期更新
     * フィルタが変更されるたびに、適用ボタン内の「(〇〇台)」を更新します。
     */
    const updateMobileHitCount = () => {
        if (window.innerWidth >= 1024 || !mobileHitCount) return;

        clearTimeout(countTimer);
        countTimer = setTimeout(async () => {
            const formData = new URLSearchParams(new FormData(filterForm));
            
            // 空の値を整理
            const keys = Array.from(formData.keys());
            keys.forEach(key => {
                if (!formData.get(key)) formData.delete(key);
            });

            formData.append('count_only', '1');
            
            try {
                mobileHitCount.innerHTML = '<span class="inline-block animate-spin text-[8px] opacity-50">⌛</span>';
                const res = await fetch(`${filterForm.action}?${formData.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                mobileHitCount.textContent = `(${data.total.toLocaleString()}台)`;
            } catch (e) {
                console.error("Count Fetch Error:", e);
                mobileHitCount.textContent = "";
            }
        }, 400);
    };

    /**
     * すべてのフィルタ条件を初期状態（リセット）にする関数
     */
    const resetAllFilters = () => {
        if (keywordInput) keywordInput.value = "";

        const sliders = filterForm.querySelectorAll('input[type="range"]');
        sliders.forEach(slider => {
            if (slider.classList.contains('range-min')) {
                slider.value = slider.getAttribute('min');
            } else if (slider.classList.contains('range-max')) {
                slider.value = slider.getAttribute('max');
            }
            slider.dispatchEvent(new Event('input'));
        });
    };

    /**
     * フォーム送信直前のクリーンアップ
     */
    const cleanFormBeforeSubmit = () => {
        const inputs = filterForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            const val = input.value;
            const name = input.name;

            if (!val && name !== 'sort') {
                input.disabled = true;
                return;
            }

            if (input.type === 'range') {
                const minAttr = input.getAttribute('min');
                const maxAttr = input.getAttribute('max');
                if (input.classList.contains('range-min') && val === minAttr) input.disabled = true;
                if (input.classList.contains('range-max') && val === maxAttr) input.disabled = true;
            }
        });
    };

    filterForm.addEventListener('submit', (e) => {
        cleanFormBeforeSubmit();
        return true;
    });

    /**
     * フィルタ変更時の処理
     */
    const handleFilterChange = (shouldReset = false) => {
        if (shouldReset) {
            resetAllFilters();
        }

        if (window.innerWidth >= 1024) {
            setTimeout(() => {
                if (typeof filterForm.requestSubmit === 'function') {
                    filterForm.requestSubmit();
                } else {
                    filterForm.submit();
                }
            }, 50);
        } else {
            updateMobileHitCount();
        }
    };

    // --- デュアルレンジスライダーの初期化 ---
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
            input.addEventListener("input", (e) => {
                updateUI(e);
                updateMobileHitCount(); 
            });
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

    mSelect?.addEventListener('change', () => {
        resetAllFilters(); 
        if (window.innerWidth >= 1024) {
            handleFilterChange(false);
        } else {
            updateModelList(null);
            updateMobileHitCount();
        }
    });

    modelSelect?.addEventListener('change', () => {
        handleFilterChange(true);
    });

    const filterSelectors = ['select[name="prefecture"]', 'input[name="is_new"]', 'input[name="has_repair_history"]'];
    filterSelectors.forEach(selector => {
        filterForm.querySelectorAll(selector).forEach(input => {
            input.addEventListener('change', () => {
                handleFilterChange(false);
                updateMobileHitCount();
            });
        });
    });

    initDualSlider("slider-price", 5);
    initDualSlider("slider-mileage", 2000);
    initDualSlider("slider-year", 1);

    if (mSelect && mSelect.value && modelSelect && modelSelect.options.length <= 1) {
        updateModelList(modelSelect.dataset.selectedId);
    }
});