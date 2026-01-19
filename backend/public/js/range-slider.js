/**
 * MotoHub Dual Range Slider Logic
 * スマホでの誤リロードを防ぐための修正版
 */
const initDualSlider = (containerId, minGap = 1) => {
    const container = document.getElementById(containerId);
    const form = document.getElementById('filter-form');
    if (!container || !form) return;

    const rangeInputs = container.querySelectorAll("input");
    const progress = container.querySelector(".slider-progress");
    const labelMin = document.getElementById(`label-min-${containerId.split('-')[1]}`);
    const labelMax = document.getElementById(`label-max-${containerId.split('-')[1]}`);

    const updateUI = (event) => {
        let minVal = parseInt(rangeInputs[0].value);
        let maxVal = parseInt(rangeInputs[1].value);
        const minLimit = parseInt(rangeInputs[0].min);
        const maxLimit = parseInt(rangeInputs[0].max);

        // 最小値と最大値が重ならないように制御
        if (maxVal - minVal < minGap) {
            if (event && event.target.className.includes("range-min")) {
                rangeInputs[0].value = maxVal - minGap;
                minVal = maxVal - minGap;
            } else if (event) {
                rangeInputs[1].value = minVal + minGap;
                maxVal = minVal + minGap;
            }
        }

        /**
         * 単位と「なし」表示のフォーマット
         */
        const formatLabel = (val, type, isMin) => {
            if (isMin && val <= minLimit) return "下限なし";
            if (!isMin && val >= maxLimit) return "上限なし";
            
            const formattedNum = val.toLocaleString();
            
            if (type === 'price') return `${formattedNum}万円`;
            if (type === 'mileage') return `${formattedNum}km`;
            if (type === 'year') return `${val}年`;
            return val;
        };

        const type = containerId.split('-')[1];
        if (labelMin) labelMin.textContent = formatLabel(minVal, type, true);
        if (labelMax) labelMax.textContent = formatLabel(maxVal, type, false);

        // プログレスバーの描画更新
        const minPercent = (minVal - rangeInputs[0].min) / (rangeInputs[0].max - rangeInputs[0].min) * 100;
        const maxPercent = (maxVal - rangeInputs[1].min) / (rangeInputs[1].max - rangeInputs[1].min) * 100;
        
        progress.style.left = minPercent + "%";
        progress.style.width = (maxPercent - minPercent) + "%";
    };

    rangeInputs.forEach(input => {
        input.addEventListener("input", updateUI);
        
        // 即時反映の制御
        input.addEventListener("change", () => {
            // デスクトップ版（Tailwindのlg: 1024px以上）のみ即時リロードを行う
            // スマホ版では「この条件で検索」ボタンを明示的に押すまでリロードさせない
            if (window.innerWidth >= 1024) {
                setTimeout(() => form.submit(), 10);
            }
        });
    });

    // 初期表示実行
    updateUI();
};

document.addEventListener('DOMContentLoaded', () => {
    initDualSlider("slider-price", 5);
    initDualSlider("slider-mileage", 2000);
    initDualSlider("slider-year", 1);
    
    if (window.lucide) window.lucide.createIcons();
});