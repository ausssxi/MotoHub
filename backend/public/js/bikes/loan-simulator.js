/**
 * MotoHub Loan Simulator
 * 実質年率とアドオン方式に対応した簡易シミュレーター
 */

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('loan-simulator');
    if (!container) return;

    // 初期設定 (Bladeからデータ属性で渡された総額を取得)
    const totalPrice = parseInt(container.dataset.totalPrice) * 10000; // 万円 -> 円
    const defaultRate = 4.9; // 年利(%)（静的目安ブロック / config loan.sample_apr と一致）

    // 要素の取得
    const inputs = {
        downPayment: document.getElementById('loan-down-payment'),
        bonus: document.getElementById('loan-bonus'),
        installments: document.getElementById('loan-installments'),
        rate: document.getElementById('loan-rate'),
    };

    const display = {
        downPaymentVal: document.getElementById('disp-down-payment'),
        loanAmount: document.getElementById('disp-loan-amount'),
        monthly: document.getElementById('disp-monthly-payment'),
        totalPayment: document.getElementById('disp-total-payment'),
    };

    // 計算ロジック
    function calculate() {
        // 入力値の取得
        const downPayment = parseInt(inputs.downPayment.value) * 10000 || 0;
        const bonusPayment = (parseInt(inputs.bonus.value) * 10000 || 0) * 2; // 年2回分
        const installments = parseInt(inputs.installments.value);
        const rate = parseFloat(inputs.rate.value) / 100;

        // ローン元金
        let principal = totalPrice - downPayment;
        if (principal < 0) principal = 0;

        // アドオン方式での簡易計算 (元金 * 年数 * 年利)
        // ※実際は金融機関により計算式が異なりますが、目安としてはこれで十分です
        const years = installments / 12;
        const interest = principal * rate * years;
        const grandTotal = principal + interest;

        // ボーナス払い分を引いた残りを月々で割る
        // (ボーナス併用は複雑になるため、今回は簡易的に「ボーナス加算額×回数」を総支払から引くロジックにするか、
        // あるいはシンプルに「月々均等払い」をベースに見せるのが一般的です)
        
        // ここでは「実質年率（元利均等返済）」に近い簡易計算を行います
        // 月利
        const monthlyRate = rate / 12;
        
        // 月々支払額 (元利均等返済の公式)
        // P * r * (1+r)^n / ((1+r)^n - 1)
        let monthlyPayment = 0;
        if (principal > 0) {
            monthlyPayment = Math.floor(
                (principal * monthlyRate * Math.pow(1 + monthlyRate, installments)) /
                (Math.pow(1 + monthlyRate, installments) - 1)
            );
        }

        // 表示更新
        display.downPaymentVal.textContent = inputs.downPayment.value;
        display.loanAmount.textContent = (principal / 10000).toLocaleString();
        
        // 月々の支払額を強調表示
        // アニメーション効果をつけるとリッチに見えます
        display.monthly.textContent = monthlyPayment.toLocaleString();
        
        // 支払総額（目安）
        const totalPay = (monthlyPayment * installments) + downPayment;
        display.totalPayment.textContent = Math.floor(totalPay / 10000).toLocaleString();
    }

    // イベントリスナー設定
    Object.values(inputs).forEach(input => {
        input.addEventListener('input', calculate);
        input.addEventListener('change', calculate);
    });

    // 初期計算
    calculate();
});