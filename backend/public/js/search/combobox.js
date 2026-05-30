/**
 * MotoHub 検索付きコンボボックス（自前バニラ実装）
 *
 * ネイティブ <select> の代替。候補が多いメーカー・車種を
 * 「人気順（出品台数の多い順）で上に表示 ＋ 検索で絞り込み」できるようにする。
 *
 * 構造（Blade 側で生成）:
 *   <div class="moto-combobox" data-combobox="manufacturer" data-placeholder="指定なし">
 *     <input type="hidden" name="manufacturer_id" value="">
 *     <button type="button" class="moto-combobox__toggle" aria-haspopup="listbox" aria-expanded="false">
 *       <span class="moto-combobox__label"></span> ...chevron...
 *     </button>
 *     <div class="moto-combobox__panel hidden">
 *       <input type="text" class="moto-combobox__search" ...>  (※ name 属性を付けない＝送信されない)
 *       <ul class="moto-combobox__list" role="listbox"> ...<li role="option" data-value data-label data-search>... </ul>
 *       <div class="moto-combobox__empty hidden">該当なし</div>
 *     </div>
 *   </div>
 *
 * 公開API: window.MotoCombobox.get('manufacturer')
 *   - setOptions([{value,label,search?,count?}])  候補を入れ替える
 *   - setValue(value)                              値を選択状態にする
 *   - getValue()                                   現在値
 *   - clear()                                      未選択（プレースホルダ）に戻す
 *   - enable() / disable()
 *   - onChange(cb)                                 ユーザー選択時に発火
 */
(function () {
    'use strict';

    const registry = {};

    /** ひらがな→カタカナ変換（揺れ吸収用） */
    const hiraToKata = (str) =>
        str.replace(/[\u3041-\u3096]/g, (ch) => String.fromCharCode(ch.charCodeAt(0) + 0x60));

    /** 検索用に正規化：かなをカタカナへ寄せ、英字小文字化、空白除去 */
    const normalize = (str) =>
        hiraToKata(String(str || '').toLowerCase()).replace(/\s+/g, '');

    class Combobox {
        constructor(root) {
            this.root = root;
            this.hidden = root.querySelector('input[type="hidden"]');
            this.toggle = root.querySelector('.moto-combobox__toggle');
            this.labelEl = root.querySelector('.moto-combobox__label');
            this.panel = root.querySelector('.moto-combobox__panel');
            this.search = root.querySelector('.moto-combobox__search');
            this.list = root.querySelector('.moto-combobox__list');
            this.empty = root.querySelector('.moto-combobox__empty');
            this.placeholder = root.dataset.placeholder || '指定なし';
            this.changeCb = null;
            this.activeIndex = -1;

            this.refreshLabelFromValue();
            this.bind();
        }

        bind() {
            this.toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (this.toggle.disabled) return;
                this.isOpen() ? this.close() : this.open();
            });

            this.search.addEventListener('input', () => this.filter());

            this.search.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') { e.preventDefault(); this.move(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); this.move(-1); }
                else if (e.key === 'Enter') { e.preventDefault(); this.chooseActive(); }
                else if (e.key === 'Escape') { e.preventDefault(); this.close(); this.toggle.focus(); }
            });

            // 候補クリック（イベント委譲）
            this.list.addEventListener('click', (e) => {
                const li = e.target.closest('[role="option"]');
                if (li) this.select(li.dataset.value, li.dataset.label, true);
            });

            // 外側クリックで閉じる
            document.addEventListener('click', (e) => {
                if (!this.root.contains(e.target)) this.close();
            });
        }

        isOpen() { return !this.panel.classList.contains('hidden'); }

        open() {
            this.panel.classList.remove('hidden');
            this.toggle.setAttribute('aria-expanded', 'true');
            this.search.value = '';
            this.filter();
            // スマホでズーム/スクロールしないよう、少し遅延してフォーカス
            setTimeout(() => this.search.focus(), 0);
        }

        close() {
            if (!this.isOpen()) return;
            this.panel.classList.add('hidden');
            this.toggle.setAttribute('aria-expanded', 'false');
            this.activeIndex = -1;
        }

        /** 現在表示されている（非hidden）候補要素 */
        visibleOptions() {
            return Array.from(this.list.querySelectorAll('[role="option"]'))
                .filter((li) => !li.classList.contains('hidden'));
        }

        filter() {
            const q = normalize(this.search.value);
            let visible = 0;
            this.list.querySelectorAll('[role="option"]').forEach((li) => {
                const hay = li.dataset._n || (li.dataset._n = normalize((li.dataset.search || '') + (li.dataset.label || '')));
                const match = q === '' || hay.indexOf(q) !== -1;
                li.classList.toggle('hidden', !match);
                if (match) visible++;
            });
            this.empty.classList.toggle('hidden', visible > 0);
            this.activeIndex = -1;
            this.highlight();
        }

        move(dir) {
            const opts = this.visibleOptions();
            if (!opts.length) return;
            this.activeIndex = (this.activeIndex + dir + opts.length) % opts.length;
            this.highlight();
            const el = opts[this.activeIndex];
            if (el) el.scrollIntoView({ block: 'nearest' });
        }

        highlight() {
            const opts = this.visibleOptions();
            this.list.querySelectorAll('[role="option"]').forEach((li) => {
                li.classList.remove('moto-combobox__opt--active');
                li.setAttribute('aria-selected', 'false');
            });
            const el = opts[this.activeIndex];
            if (el) {
                el.classList.add('moto-combobox__opt--active');
                el.setAttribute('aria-selected', 'true');
            }
        }

        chooseActive() {
            const opts = this.visibleOptions();
            const el = opts[this.activeIndex] || opts[0];
            if (el) this.select(el.dataset.value, el.dataset.label, true);
        }

        /** 値を選択して反映。fire=true でユーザー操作扱い（onChange発火） */
        select(value, label, fire) {
            this.hidden.value = value || '';
            this.labelEl.textContent = (value === '' || value == null)
                ? this.placeholder
                : (label || this.placeholder);
            this.labelEl.classList.toggle('moto-combobox__label--empty', !value);
            this.close();
            if (fire && typeof this.changeCb === 'function') this.changeCb(this.hidden.value);
        }

        // --- 公開API ---
        setOptions(items) {
            const frag = document.createDocumentFragment();
            (items || []).forEach((it) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.className = 'moto-combobox__opt';
                li.dataset.value = it.value;
                li.dataset.label = it.label;
                if (it.search) li.dataset.search = it.search;
                // textContent でエスケープ（車種名に < 等が含まれても安全に）
                const name = document.createElement('span');
                name.className = 'moto-combobox__opt-name';
                name.textContent = it.label;
                li.appendChild(name);
                if (it.count != null) {
                    const count = document.createElement('span');
                    count.className = 'moto-combobox__opt-count';
                    count.textContent = it.count;
                    li.appendChild(count);
                }
                frag.appendChild(li);
            });
            this.list.innerHTML = '';
            this.list.appendChild(frag);
            this.empty.classList.add('hidden');
        }

        setValue(value) {
            const li = this.list.querySelector(`[role="option"][data-value="${CSS.escape(String(value))}"]`);
            this.select(value, li ? li.dataset.label : '', false);
        }

        getValue() { return this.hidden.value; }

        clear() { this.select('', '', false); }

        enable() {
            this.toggle.disabled = false;
            this.root.classList.remove('moto-combobox--disabled');
        }

        disable() {
            this.close();
            this.toggle.disabled = true;
            this.root.classList.add('moto-combobox--disabled');
        }

        refreshLabelFromValue() {
            const v = this.hidden.value;
            const li = v ? this.list.querySelector(`[role="option"][data-value="${CSS.escape(String(v))}"]`) : null;
            this.labelEl.textContent = li ? li.dataset.label : this.placeholder;
            this.labelEl.classList.toggle('moto-combobox__label--empty', !v);
        }

        onChange(cb) { this.changeCb = cb; }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-combobox]').forEach((root) => {
            registry[root.dataset.combobox] = new Combobox(root);
        });
    });

    window.MotoCombobox = {
        get: (name) => registry[name] || null,
    };
})();
