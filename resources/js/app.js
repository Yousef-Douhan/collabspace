import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import Chart from 'chart.js/auto';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

/** JSON request helper for the few AJAX interactions (Kanban drag & drop). */
window.api = async (url, { method = 'GET', body } = {}) => {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || res.statusText);
    return res.json().catch(() => ({}));
};

/** Kanban board: each column is a Sortable list; dropping a card calls PATCH /tasks/{id}/move. */
Alpine.data('kanban', () => ({
    error: null,
    init() {
        this.$el.querySelectorAll('[data-column]').forEach((column) => {
            Sortable.create(column, {
                group: 'tasks',
                animation: 150,
                ghostClass: 'opacity-40',
                draggable: '[data-task]',
                onEnd: async (evt) => {
                    const id = evt.item.dataset.task;
                    const status = evt.to.dataset.column;
                    const cards = [...evt.to.querySelectorAll('[data-task]')];
                    try {
                        this.error = null;
                        await window.api(`/tasks/${id}/move`, { method: 'PATCH', body: { status, position: cards.indexOf(evt.item) } });
                        this.$dispatch('board-changed');
                    } catch (e) {
                        this.error = e.message;
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null); // undo
                    }
                },
            });
        });
    },
}));

/** <canvas x-data="chart(config)">: config is a Chart.js config object rendered from PHP. */
Alpine.data('chart', (config) => ({
    init() {
        // Right-to-left pages: time runs right→left and the value axis sits on the right.
        if (document.documentElement.dir === 'rtl') {
            config.options ??= {};
            config.options.scales ??= {};
            config.options.scales.x = { ...(config.options.scales.x ?? {}), reverse: true };
            config.options.scales.y = { ...(config.options.scales.y ?? {}), position: 'right' };
            config.options.plugins ??= {};
            config.options.plugins.legend = { ...(config.options.plugins.legend ?? {}), rtl: true };
            config.options.plugins.tooltip = { ...(config.options.plugins.tooltip ?? {}), rtl: true };
        }
        new Chart(this.$el, config);
    },
}));

window.Alpine = Alpine;
Alpine.start();
