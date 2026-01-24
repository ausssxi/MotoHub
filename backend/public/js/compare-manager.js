/**
 * MotoHub Compare Manager
 * 比較対象の車両IDを localStorage で管理します。
 */
const Compare = {
    key: 'motohub_compare_list',
    maxItems: 4,

    getIds() {
        const stored = localStorage.getItem(this.key);
        return stored ? JSON.parse(stored) : [];
    },

    toggle(id) {
        id = parseInt(id);
        let ids = this.getIds();
        const index = ids.indexOf(id);

        if (index > -1) {
            ids.splice(index, 1);
        } else {
            if (ids.length >= this.maxItems) {
                return { success: false, message: `最大${this.maxItems}台までしか比較できません。` };
            }
            ids.push(id);
        }

        localStorage.setItem(this.key, JSON.stringify(ids));
        // カスタムイベントを発火して他のコンポーネントに通知
        document.dispatchEvent(new CustomEvent('compare-changed', { detail: { ids } }));
        return { success: true, action: index > -1 ? 'removed' : 'added', ids };
    },

    clear() {
        localStorage.removeItem(this.key);
        document.dispatchEvent(new CustomEvent('compare-changed', { detail: { ids: [] } }));
    }
};