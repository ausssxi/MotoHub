/**
 * MotoHub Sidebar UI Logic
 * - フィルター選択時にキーワードを確実に消去する版
 */

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    if (!filterForm) return;

    const mobileHitCountEl = document.querySelector('#mobile-hit-count');
    let debounceTimer;

    /**
     * ✨ 修正：キーワード入力欄を全消去する関数
     */
    const clearKeywordInputs = () => {
        console.log(">>> [UI] Clearing keyword inputs...");
        
        // 1. フォーム内の隠しフィールド(hidden)や通常の入力欄をすべて対象にする
        const keywords = document.querySelectorAll('input[name="keyword"]');
        keywords.forEach(input => {
            input.value = '';
        });

        // 2. ナビゲーションバーにある検索窓（PC/スマホ両方）も空にする
        const navInputs = [
            'nav-search-input',
            'search-input',
            'mobile-nav-search-input'
        ];
        navInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    };

    // --- 1. 共通：検索結果件数の更新 ---
    const updateHitCount = async () => {
        if (!mobileHitCountEl) return;
        mobileHitCountEl.style.opacity = '0.5';
        try {
            const formData = new FormData(filterForm);
            const cleanParams = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value !== "" && value !== null) cleanParams.append(key, value);
            }
            const response = await fetch(`/api/bikes/count?${cleanParams.toString()}`);
            const data = await response.json();
            if (data.count !== undefined) {
                mobileHitCountEl.textContent = `(${data.count.toLocaleString()}台)`;
            }
        } catch (e) { console.error(e); }
        finally { mobileHitCountEl.style.opacity = '1'; }
    };

    const debouncedUpdate = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (window.innerWidth < 1024) {
                updateHitCount();
            } else {
                filterForm.submit();
            }
        }, 400);
    };

    /**
     * ✨ 修正：フィルタ変更時の処理（manual引数を追加）
     */
    const handleFilterChange = (isManualSelection = false) => {
        // メーカーや車種を直接選んだ場合は、古い検索ワードを消す
        if (isManualSelection) {
            clearKeywordInputs();
        }

        if (window.innerWidth >= 1024) {
            setTimeout(() => filterForm.submit(), 50);
        } else {
            updateHitCount();
        }
    };

    // --- 2. スライダー初期化 ---
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
                if (isMin && val <= parseInt(rangeInputs[0].min)) return "下限なし";
                if (!isMin && val >= parseInt(rangeInputs[0].max)) return "上限なし";
                if (type === 'price') return `${val.toLocaleString()}万円`;
                if (type === 'mileage') return `${val.toLocaleString()}km`;
                return `${val}年`;
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
                if (window.innerWidth < 1024) debouncedUpdate();
            });
            input.addEventListener("change", () => handleFilterChange(false));
        });
        updateUI();
    };

    // --- 3. メーカー・車種連動 ---
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

    mSelect?.addEventListener('change', async () => {
        if (window.innerWidth < 1024) {
            clearKeywordInputs(); // スマホ版でも手動変更時はキーワードを消す
            await updateModelList(null);
            updateHitCount();
        } else {
            handleFilterChange(true); // PC版：引数をtrueにしてキーワードを消す
        }
    });

    modelSelect?.addEventListener('change', () => handleFilterChange(true));

    // --- 4. その他（地域など） ---
    const otherInputs = filterForm.querySelectorAll('select[name="prefecture"], input[name="is_new"], input[name="has_repair_history"]');
    otherInputs.forEach(input => {
        input.addEventListener('change', () => handleFilterChange(false));
    });

    // 初期化
    initDualSlider("slider-price", 5);
    initDualSlider("slider-mileage", 2000);
    initDualSlider("slider-year", 1);
    if (mSelect && mSelect.value && modelSelect && modelSelect.options.length <= 1) {
        updateModelList(modelSelect.dataset.selectedId);
    }
    if (window.lucide) window.lucide.createIcons();
});