/**
 * MotoHub Sidebar UI Logic (Robust Version)
 * - リアルタイム件数更新とPCオートリロードの完全統合
 */

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    if (!filterForm) return;

    const mobileHitCountEl = document.querySelector('#mobile-hit-count');
    let debounceTimer;

    // --- 1. 共通：検索結果件数を取得してUIを更新する ---
    const updateHitCount = async () => {
        if (!mobileHitCountEl) return;
        
        console.log("Fetching hit count..."); // デバッグ用
        mobileHitCountEl.style.opacity = '0.5';

        try {
            const formData = new FormData(filterForm);
            // 空の値を削除してリクエストを綺麗にする
            const cleanParams = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value !== "" && value !== null) {
                    cleanParams.append(key, value);
                }
            }
            
            // fetch先のURLを組み立て
            const url = `/api/bikes/count?${cleanParams.toString()}`;
            console.log("Request URL:", url);

            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            
            const data = await response.json();
            console.log("Received count:", data.count);

            if (data.count !== undefined) {
                mobileHitCountEl.textContent = `(${data.count.toLocaleString()}台)`;
            }
        } catch (e) {
            console.error("件数の取得に失敗しました:", e);
        } finally {
            mobileHitCountEl.style.opacity = '1';
        }
    };

    // 操作が止まってから実行するためのデバウンス関数
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

    // --- 2. フィルタ変更時のハンドラ ---
    const handleFilterChange = (isSlider = false) => {
        if (window.innerWidth >= 1024) {
            // PC版：即リロード
            console.log("PC detected: Auto-submitting form...");
            setTimeout(() => filterForm.submit(), 50);
        } else {
            // スマホ版：件数更新
            console.log("Mobile detected: Updating hit count...");
            if (isSlider) {
                debouncedUpdate();
            } else {
                updateHitCount();
            }
        }
    };

    // --- 3. デュアルレンジスライダーの初期化 ---
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
                if (window.innerWidth < 1024) debouncedUpdate();
            });
            input.addEventListener("change", () => handleFilterChange(true));
        });

        updateUI();
    };

    // --- 4. メーカー・車種連動（ドリルダウン） ---
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
        } catch (e) {
            console.error("車種リストの取得に失敗しました:", e);
        }
    };

    mSelect?.addEventListener('change', async () => {
        if (window.innerWidth < 1024) {
            await updateModelList(null);
            updateHitCount();
        } else {
            handleFilterChange();
        }
    });

    modelSelect?.addEventListener('change', () => handleFilterChange());

    // --- 5. その他の入力（地域・コンディション・修復歴） ---
    // ここが重要：すべてのチェックボックスやセレクトを監視
    const otherInputs = filterForm.querySelectorAll('select[name="prefecture"], input[name="is_new"], input[name="has_repair_history"]');
    otherInputs.forEach(input => {
        input.addEventListener('change', () => {
            console.log("Other input changed:", input.name, input.value);
            handleFilterChange();
        });
    });

    // --- 6. 初期化実行 ---
    initDualSlider("slider-price", 5);
    initDualSlider("slider-mileage", 2000);
    initDualSlider("slider-year", 1);

    if (mSelect && mSelect.value && modelSelect) {
        if (modelSelect.options.length <= 1) {
            updateModelList(modelSelect.dataset.selectedId);
        }
    }

    if (window.lucide) window.lucide.createIcons();
});