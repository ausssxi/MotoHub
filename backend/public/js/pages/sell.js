function sellForm() {
    return {
        // メーカー
        makers: window.__sellMakers || [],
        makerSearch: '',
        makerOpen: false,
        makerHighlight: 0,
        selectedMakerId: null,
        selectedMakerName: '',

        // 車種
        models: [],
        modelSearch: '',
        modelOpen: false,
        modelHighlight: 0,
        selectedModelId: null,
        selectedModelName: '',
        loadingModels: false,

        // 計算状態
        calculating: false,

        get filteredMakers() {
            const q = this.makerSearch.trim().toLowerCase();
            if (!q || q === this.selectedMakerName.toLowerCase()) return this.makers;
            this.makerHighlight = 0;
            return this.makers.filter(m => m.name.toLowerCase().includes(q));
        },

        get filteredModels() {
            const q = this.modelSearch.trim().toLowerCase();
            if (!q || q === this.selectedModelName.toLowerCase()) return this.models;
            this.modelHighlight = 0;
            return this.models.filter(m => m.name.toLowerCase().includes(q));
        },

        selectMaker(maker) {
            this.selectedMakerId = maker.id;
            this.selectedMakerName = maker.name;
            this.makerSearch = maker.name;
            this.makerOpen = false;

            // 車種リセット
            this.selectedModelId = null;
            this.selectedModelName = '';
            this.modelSearch = '';
            this.models = [];

            this.fetchModels(maker.id);
        },

        async fetchModels(makerId) {
            this.loadingModels = true;
            try {
                const res = await fetch(`/api/manufacturers/${makerId}/models-light`);
                this.models = await res.json();
            } catch (e) {
                console.error('Failed to load models', e);
                this.models = [];
            } finally {
                this.loadingModels = false;
            }
        },

        selectModel(model) {
            this.selectedModelId = model.id;
            this.selectedModelName = model.name;
            this.modelSearch = model.name;
            this.modelOpen = false;
        },

        async calculate() {
            if (!this.selectedModelId || this.calculating) return;

            this.calculating = true;
            this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });

            const yearSelect = document.getElementById('select-year');
            const mileageInput = document.getElementById('input-mileage');

            const data = {
                bike_model_id: this.selectedModelId,
                year: yearSelect ? yearSelect.value : '',
                mileage: mileageInput ? mileageInput.value : '',
                _token: document.querySelector('meta[name="csrf-token"]').content,
            };

            try {
                const response = await fetch('/api/sell/calculate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (result.status === 'success') {
                    this.showResult(result);
                } else {
                    alert('申し訳ありません。データが不足しているため相場を算出できませんでした。');
                }
            } catch (error) {
                console.error(error);
                alert('エラーが発生しました。もう一度お試しください。');
            } finally {
                this.calculating = false;
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            }
        },

        showResult(result) {
            const resultArea = document.getElementById('result-area');
            const resultTitle = document.getElementById('result-title');
            const resultMin = document.getElementById('result-min');
            const resultMax = document.getElementById('result-max');
            const resultBasisText = document.getElementById('result-basis-text');
            const resultNote = document.getElementById('result-note');
            const resultDetails = document.getElementById('result-details');

            let title = `${result.maker_name} ${result.model_name}`;
            if (result.year) title += ` (${result.year}年式)`;

            resultTitle.textContent = title;
            resultMin.textContent = result.purchase_min;
            resultMax.textContent = result.purchase_max;

            if (result.data_count > 0 && result.base_sold_price > 0) {
                resultBasisText.textContent = `${result.data_count}件の売却実績データから算出`;
            } else {
                resultBasisText.textContent = `市場平均価格 ${result.retail_avg}万円 から推計`;
            }

            if (result.is_fallback) {
                resultNote.classList.remove('hidden');
            } else {
                resultNote.classList.add('hidden');
            }

            if (result.factors && result.data_count > 0 && !result.is_fallback) {
                resultDetails.classList.remove('hidden');

                document.getElementById('detail-count').textContent = `${result.data_count}件`;

                const avgPrice = result.base_sold_price > 0
                    ? `${(result.base_sold_price / 10000).toFixed(1)}万円`
                    : '-';
                document.getElementById('detail-avg').textContent = avgPrice;

                const f = result.factors;

                if (f.avg_days > 0) {
                    document.getElementById('detail-speed').textContent = `${f.avg_days}日（${f.speed_label}）`;
                    document.getElementById('detail-speed-row').classList.remove('hidden');
                } else {
                    document.getElementById('detail-speed-row').classList.add('hidden');
                }

                if (f.mileage_factor !== 1.00) {
                    document.getElementById('detail-mileage').textContent = `x${f.mileage_factor.toFixed(2)}`;
                    document.getElementById('detail-mileage-row').classList.remove('hidden');
                } else {
                    document.getElementById('detail-mileage-row').classList.add('hidden');
                }

                if (f.year_factor !== 1.00) {
                    document.getElementById('detail-year').textContent = `x${f.year_factor.toFixed(2)}`;
                    document.getElementById('detail-year-row').classList.remove('hidden');
                } else {
                    document.getElementById('detail-year-row').classList.add('hidden');
                }

                const confMap = { high: '★★★（高い）', medium: '★★☆（普通）', low: '★☆☆（低い）' };
                document.getElementById('detail-confidence').textContent = confMap[result.confidence] || '-';
            } else {
                resultDetails.classList.add('hidden');
            }

            resultArea.classList.remove('hidden');
            resultArea.classList.add('animate-in', 'fade-in', 'slide-in-from-bottom-8', 'duration-700');

            setTimeout(() => {
                resultArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);

            if (window.lucide) lucide.createIcons();
        },
    };
}
