/**
 * 道の駅 座標入力（管理）。
 * 座標NULLの駅を一覧から選び、地図クリックで緯度経度を入力・保存する。
 * エンドポイント/CSRF は Blade の data 属性から取得（静的JSに直書きしない）。
 */
document.addEventListener('DOMContentLoaded', function () {
    var app = document.getElementById('rs-coord-app');
    if (!app) return;

    var updateUrlTemplate = app.dataset.updateUrl; // 末尾 /STATION_ID/coordinates
    var csrf = app.dataset.csrf;

    var listEl = document.getElementById('rs-list');
    var prefFilter = document.getElementById('rs-pref-filter');
    var coordDisplay = document.getElementById('rs-coord-display');
    var selectedNameEl = document.getElementById('rs-selected-name');
    var saveBtn = document.getElementById('rs-save');
    var savedEl = document.getElementById('rs-saved');
    var remainingEl = document.getElementById('rs-remaining');
    var errorEl = document.getElementById('rs-error');

    var selectedRow = null;
    var marker = null;

    // 地図（日本全体から開始）。タイル・attribution は既存画面と同じ。
    var map = L.map('rs-map').setView([36.2048, 138.2529], 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    // コンテナの高さ確定がレイアウト後になる場合があるため、確定後にサイズを再計算する
    setTimeout(function () { map.invalidateSize(); }, 0);
    setTimeout(function () { map.invalidateSize(); }, 300);
    window.addEventListener('resize', function () { map.invalidateSize(); });

    function fmt(n) { return Number(n).toFixed(7); }

    function updateSaveState() {
        saveBtn.disabled = !(selectedRow && marker);
    }

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                var p = e.target.getLatLng();
                coordDisplay.textContent = fmt(p.lat) + ', ' + fmt(p.lng);
            });
        }
        coordDisplay.textContent = fmt(lat) + ', ' + fmt(lng);
        updateSaveState();
    }

    function clearMarker() {
        if (marker) { map.removeLayer(marker); marker = null; }
        coordDisplay.textContent = '-';
        updateSaveState();
    }

    // 国土地理院の住所検索（parking/create.js を踏襲）。
    // ・行クリック時に1回だけ呼ぶ ・Nominatim へはフォールバックしない
    // ・空/失敗しても地図は動かさない（エラーで止めない）
    async function geocodeGSI(query) {
        try {
            var q = (query || '').replace(/[\s　]+/g, '');
            if (!q) return null;
            var url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' + encodeURIComponent(q);
            var res = await fetch(url);
            if (!res.ok) return null;
            var data = await res.json();
            if (!Array.isArray(data) || data.length === 0) return null;
            var coords = data[0].geometry.coordinates; // 国土地理院は [経度, 緯度] の順
            return { lat: coords[1], lng: coords[0] };
        } catch (e) {
            return null;
        }
    }

    async function selectRow(row) {
        if (!row) return;
        if (selectedRow) selectedRow.classList.remove('selected');
        selectedRow = row;
        row.classList.add('selected');
        selectedNameEl.textContent = row.dataset.name || '';
        errorEl.classList.add('hidden');
        clearMarker(); // 新しい駅はピン未設置から
        row.scrollIntoView({ block: 'nearest' });
        updateSaveState();

        // 行クリック時に1回だけ GSI で prefecture+city の位置へ寄せる。
        var g = await geocodeGSI((row.dataset.pref || '') + (row.dataset.city || ''));
        if (g) {
            map.setView([g.lat, g.lng], 14);
        }
        // 空/失敗時は現在位置のまま。
    }

    listEl.addEventListener('click', function (e) {
        var row = e.target.closest('.rs-row');
        if (row && listEl.contains(row)) selectRow(row);
    });

    map.on('click', function (e) {
        setMarker(e.latlng.lat, e.latlng.lng);
    });

    // 都道府県フィルタ（クライアント側のみ・サーバ往復なし）
    prefFilter.addEventListener('change', function () {
        var val = prefFilter.value;
        listEl.querySelectorAll('.rs-row').forEach(function (row) {
            row.style.display = (!val || row.dataset.pref === val) ? '' : 'none';
        });
    });

    function nextVisibleRow(fromRow) {
        var rows = Array.prototype.slice.call(listEl.querySelectorAll('.rs-row'));
        var start = fromRow ? rows.indexOf(fromRow) : -1;
        for (var i = start + 1; i < rows.length; i++) {
            if (rows[i] !== fromRow && rows[i].style.display !== 'none') return rows[i];
        }
        for (var j = 0; j < rows.length; j++) {
            if (rows[j] !== fromRow && rows[j].style.display !== 'none') return rows[j];
        }
        return null;
    }

    saveBtn.addEventListener('click', async function () {
        if (!selectedRow || !marker) return;

        var savedRow = selectedRow;
        var id = savedRow.dataset.id;
        var pos = marker.getLatLng();
        var url = updateUrlTemplate.replace('STATION_ID', encodeURIComponent(id));

        saveBtn.disabled = true;
        errorEl.classList.add('hidden');

        try {
            var res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    latitude: Number(pos.lat.toFixed(7)),
                    longitude: Number(pos.lng.toFixed(7))
                })
            });

            if (res.status === 422) {
                var err = await res.json();
                errorEl.textContent = (err && err.message) ? err.message : '入力値が不正です。';
                errorEl.classList.remove('hidden');
                updateSaveState();
                return;
            }
            if (!res.ok) {
                errorEl.textContent = '保存に失敗しました（' + res.status + '）。';
                errorEl.classList.remove('hidden');
                updateSaveState();
                return;
            }

            var data = await res.json();

            // 保存済み欄（押し間違い確認用）へ積む
            var li = document.createElement('li');
            li.textContent = (data.station_code || savedRow.dataset.stationCode || '') + ' ' + (savedRow.dataset.name || '');
            savedEl.insertBefore(li, savedEl.firstChild);

            // 残り件数（サーバ算出値）
            if (typeof data.remaining === 'number') remainingEl.textContent = data.remaining;

            // 次の行を確定してから現在行を除去 → 自動選択
            var next = nextVisibleRow(savedRow);
            savedRow.remove();
            selectedRow = null;
            clearMarker();
            selectedNameEl.textContent = '（未選択）';
            if (next) {
                selectRow(next);
            } else {
                updateSaveState();
            }
        } catch (e) {
            errorEl.textContent = '通信エラーが発生しました。';
            errorEl.classList.remove('hidden');
            updateSaveState();
        }
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
