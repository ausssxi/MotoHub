/**
 * MotoHub 地名検索 (国土地理院API)
 * 駐車場マップ・ショップマップ共通
 *
 * 使い方:
 *   initMapSearch(leafletMap, { inputId, buttonId, onMoveEnd })
 */
function initMapSearch(map, opts = {}) {
    const inputId  = opts.inputId  || 'map-search-input';
    const buttonId = opts.buttonId || 'map-search-btn';
    const zoom     = opts.zoom     || 15;

    const input = document.getElementById(inputId);
    const btn   = document.getElementById(buttonId);
    if (!input) return;

    let toastTimer = null;

    // ── Toast 通知 ──
    function showToast(msg, isError) {
        let el = document.getElementById('map-search-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'map-search-toast';
            el.style.cssText =
                'position:fixed;top:80px;left:50%;transform:translateX(-50%);' +
                'padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;' +
                'z-index:9999;pointer-events:none;opacity:0;transition:opacity .3s ease;' +
                'backdrop-filter:blur(8px);white-space:nowrap;';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.style.background = isError ? 'rgba(239,68,68,.9)' : 'rgba(22,163,74,.9)';
        el.style.color = '#fff';
        el.style.opacity = '1';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.style.opacity = '0'; }, 3000);
    }

    // ── ローディング状態 ──
    function setLoading(on) {
        if (btn) {
            btn.disabled = on;
            btn.querySelector('.search-icon')?.classList.toggle('hidden', on);
            btn.querySelector('.search-spinner')?.classList.toggle('hidden', !on);
        }
        input.disabled = on;
    }

    // ── 国土地理院API ジオコーディング ──
    async function geocode(query) {
        const url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' + encodeURIComponent(query);
        const res = await fetch(url);
        if (!res.ok) throw new Error('API error');
        return res.json();
    }

    // ── クエリをキーワードに分割 ──
    // 「相模原市橋本」→ ["相模原市", "橋本"]
    // 「東京都渋谷区神南」→ ["東京都", "渋谷区", "神南"]
    function splitQuery(query) {
        // 都道府県・市区町村の境界で分割（区切り文字を前のトークンに含める）
        const tokens = query.split(/(..?[都道府県]|.+?[市区町村郡])/).filter(Boolean);
        // さらに「駅」「丁目」等でも分割
        const result = [];
        for (const t of tokens) {
            const sub = t.split(/([^\s]+?[駅丁目])/).filter(Boolean);
            result.push(...sub);
        }
        return result.map(s => s.trim()).filter(Boolean);
    }

    // ── 施設名パターン（地名より優先度を下げる） ──
    const FACILITY_RE = /消防署|消防局|消防本部|市役所|警察署|学校|病院|郵便局|図書館|公民館|交番|出張所|支所|役場|裁判所|税務署|保健所|駐在所|センター|事務所|庁舎/;

    // ── 候補スコアリング ──
    // キーワードマッチ数でスコア算出、施設名は減点
    // 同スコアなら短い（より広域な地名）を優先
    function scoreResults(results, keywords, rawQuery) {
        // クエリが純粋な地名（市区町村名のみ）かどうか判定
        const isPlaceName = /^[^\s]{2,}[都道府県市区町村郡]$/.test(rawQuery);

        let best = results[0];
        let bestScore = -Infinity;
        let bestLen = Infinity;

        for (const r of results) {
            const title = r.properties?.title || '';
            let score = 0;
            for (const kw of keywords) {
                if (title.includes(kw)) score++;
            }
            // 施設名を含む候補は減点（純粋な地名検索なら強い減点）
            if (FACILITY_RE.test(title)) {
                score -= isPlaceName ? 2 : 1;
            }

            if (score > bestScore || (score === bestScore && title.length < bestLen)) {
                best = r;
                bestScore = score;
                bestLen = title.length;
            }
        }
        return { best, bestScore };
    }

    // ── 検索実行（フォールバック付き） ──
    async function doSearch() {
        const rawInput = input.value.trim();
        if (!rawInput) return;

        // スペース区切り対応: 全角・半角スペースで分割
        const spaceParts = rawInput.split(/[\s\u3000]+/).filter(Boolean);
        // APIに投げるクエリはスペースを除去した結合形
        const query = spaceParts.join('');

        // キーワード抽出: スペース分割 or 行政区画分割
        let keywords;
        if (spaceParts.length >= 2) {
            // スペース区切りならそのまま各パートをキーワードに
            keywords = spaceParts;
        } else {
            keywords = splitQuery(query);
            if (keywords.length === 0) keywords.push(query);
        }

        setLoading(true);
        try {
            // 1回目: 結合クエリで検索
            let results = await geocode(query);
            if (!results || results.length === 0) {
                showToast('「' + rawInput + '」は見つかりませんでした', true);
                return;
            }

            let { best, bestScore } = scoreResults(results, keywords, query);

            // 2回目: 全キーワードを含む候補がなければ末尾キーワードで再検索
            if (bestScore < keywords.length && keywords.length >= 2) {
                const tail = keywords[keywords.length - 1];
                const extra = await geocode(tail);
                if (extra && extra.length > 0) {
                    // 重複除去して結合
                    const seen = new Set(results.map(r => r.properties?.title));
                    for (const r of extra) {
                        if (!seen.has(r.properties?.title)) {
                            results.push(r);
                            seen.add(r.properties?.title);
                        }
                    }
                    ({ best, bestScore } = scoreResults(results, keywords, query));
                }
            }

            // 国土地理院APIは [経度, 緯度] の順
            const coords = best.geometry.coordinates;
            const lat = coords[1];
            const lng = coords[0];
            map.setView([lat, lng], zoom);
            showToast(best.properties.title || query, false);
        } catch (e) {
            showToast('検索中にエラーが発生しました', true);
        } finally {
            setLoading(false);
            // モバイル: ソフトキーボードを閉じる
            input.blur();
        }
    }

    // ── イベント ──
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            doSearch();
        }
    });
    if (btn) btn.addEventListener('click', doSearch);
}
