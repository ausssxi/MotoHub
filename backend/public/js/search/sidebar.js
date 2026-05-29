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
     * 範囲系プリセット（UI専用ラジオ → 送信用hidden input）のマッピング
     * value="{min}_{max}" のラジオ選択を、対応する hidden input に反映する
     * ※ 排気量のみ単一選択ラジオ。価格・走行距離・年式は複数選択ボタン（rangeButtonGroups）に移行済み。
     */
    const rangeRadioMap = {
        displacement_class: ['min-displacement-hidden', 'max-displacement-hidden'],
    };

    /**
     * 複数選択プリセットボタン群（価格・走行距離・年式）の hidden input マッピング。
     * 選択中の各バンドを min/max のエンベロープ（最小min〜最大max）に結合して送信する。
     */
    const rangeButtonGroups = {
        price: ['min-price-hidden', 'max-price-hidden'],
        mileage: ['min-mileage-hidden', 'max-mileage-hidden'],
        year: ['min-year-hidden', 'max-year-hidden'],
    };

    // ボタンの選択状態（見た目 + aria-pressed）を切り替える
    const setRangeBtnState = (btn, active) => {
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        if (active) {
            btn.classList.remove('text-gray-500');
            btn.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        } else {
            btn.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
            btn.classList.add('text-gray-500');
        }
    };

    const isRangeBtnActive = (btn) => btn.getAttribute('aria-pressed') === 'true';

    // グループ内の選択中バンドを min/max エンベロープへ結合し hidden input に反映する
    const applyGroupToHidden = (group) => {
        const [minId, maxId] = rangeButtonGroups[group];
        const minEl = document.getElementById(minId);
        const maxEl = document.getElementById(maxId);
        const buttons = filterForm.querySelectorAll(`.range-btn[data-group="${group}"]`);

        const activeBands = Array.from(buttons).filter(b => !b.dataset.all && isRangeBtnActive(b));

        let overMin = '';
        let overMax = '';
        if (activeBands.length > 0) {
            // min側: 一つでも下限なし（空）があれば 0（=空）扱い。それ以外は最小値。
            const mins = activeBands.map(b => b.dataset.min);
            overMin = mins.some(v => v === '') ? '' : String(Math.min(...mins.map(Number)));
            // max側: 一つでも上限なし（空）があれば無制限（=空）。それ以外は最大値。
            const maxes = activeBands.map(b => b.dataset.max);
            overMax = maxes.some(v => v === '') ? '' : String(Math.max(...maxes.map(Number)));
        }
        if (minEl) minEl.value = overMin;
        if (maxEl) maxEl.value = overMax;
    };

    // グループを「すべて」状態（全バンド解除＝hidden空）に戻す
    const resetRangeGroup = (group) => {
        filterForm.querySelectorAll(`.range-btn[data-group="${group}"]`).forEach(b => {
            setRangeBtnState(b, !!b.dataset.all);
        });
        applyGroupToHidden(group);
    };

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

        // 排気量ラジオの hidden input を「すべて」に戻す
        Object.values(rangeRadioMap).forEach(([minId, maxId]) => {
            const minEl = document.getElementById(minId);
            const maxEl = document.getElementById(maxId);
            if (minEl) minEl.value = '';
            if (maxEl) maxEl.value = '';
        });
        // value="_"（すべて）のラジオ（排気量）を選択状態に戻す
        filterForm.querySelectorAll('input[name$="_class"]').forEach(radio => {
            radio.checked = (radio.value === '_');
        });
        // 複数選択ボタン群（価格・走行距離・年式）を「すべて」に戻す
        Object.keys(rangeButtonGroups).forEach(group => resetRangeGroup(group));
    };

    /**
     * フォーム送信直前のクリーンアップ
     */
    const cleanFormBeforeSubmit = () => {
        // *_class はUI専用ラジオ（hidden inputで値を送信）なので除外
        filterForm.querySelectorAll('input[name$="_class"]').forEach(r => r.disabled = true);

        const inputs = filterForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            const val = input.value;
            const name = input.name;

            if (!val && name !== 'sort') {
                input.disabled = true;
                return;
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

    // --- 排気量プリセット（単一選択ラジオ） ---
    Object.entries(rangeRadioMap).forEach(([name, [minId, maxId]]) => {
        filterForm.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                const [min, max] = radio.value.split('_');
                const minEl = document.getElementById(minId);
                const maxEl = document.getElementById(maxId);
                if (minEl) minEl.value = min;
                if (maxEl) maxEl.value = max;
                handleFilterChange(false);
            });
        });
    });

    // --- 価格・走行距離・年式プリセット（複数選択ボタン） ---
    filterForm.querySelectorAll('.range-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.dataset.group;
            const groupBtns = filterForm.querySelectorAll(`.range-btn[data-group="${group}"]`);

            if (btn.dataset.all) {
                // 「すべて」を押したら他を解除して「すべて」だけ ON
                groupBtns.forEach(b => setRangeBtnState(b, !!b.dataset.all));
            } else {
                // 個別バンドをトグル。押した時点で「すべて」は解除。
                setRangeBtnState(btn, !isRangeBtnActive(btn));
                groupBtns.forEach(b => { if (b.dataset.all) setRangeBtnState(b, false); });

                // 個別バンドが一つも選ばれていなければ「すべて」を ON に戻す
                const anyBand = Array.from(groupBtns).some(b => !b.dataset.all && isRangeBtnActive(b));
                if (!anyBand) {
                    groupBtns.forEach(b => { if (b.dataset.all) setRangeBtnState(b, true); });
                }
            }

            applyGroupToHidden(group);
            handleFilterChange(false);
        });
    });

    // --- カテゴリボタン ---
    const categoryHidden = document.getElementById('category-id-hidden');
    filterForm.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const catId = btn.dataset.categoryId;
            const isActive = categoryHidden && categoryHidden.value === catId;
            if (categoryHidden) categoryHidden.value = isActive ? '' : catId;

            filterForm.querySelectorAll('.category-btn').forEach(b => {
                b.classList.remove('bg-blue-50', 'text-blue-600', 'border-blue-200', 'shadow-sm');
                b.classList.add('bg-white', 'text-gray-600', 'border-gray-100');
            });
            if (!isActive) {
                btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-100');
                btn.classList.add('bg-blue-50', 'text-blue-600', 'border-blue-200', 'shadow-sm');
            }
            handleFilterChange(false);
        });
    });

    // --- タグ複数選択 ---
    const tagsContainer = document.getElementById('tags-hidden-container');
    filterForm.querySelectorAll('.tag-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tag = btn.dataset.tag;
            const existing = tagsContainer ? tagsContainer.querySelector(`input[value="${tag}"]`) : null;

            if (existing) {
                existing.remove();
                btn.classList.remove('bg-blue-50', 'text-blue-600', 'border-blue-200', 'shadow-sm');
                btn.classList.add('bg-white', 'text-gray-600', 'border-gray-100');
            } else if (tagsContainer) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tags[]';
                input.value = tag;
                tagsContainer.appendChild(input);
                btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-100');
                btn.classList.add('bg-blue-50', 'text-blue-600', 'border-blue-200', 'shadow-sm');
            }
            handleFilterChange(false);
        });
    });

    if (mSelect && mSelect.value && modelSelect && modelSelect.options.length <= 1) {
        updateModelList(modelSelect.dataset.selectedId);
    }
});