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
    /** Keep the little counters in the column headers right after a move. */
    recount() {
        this.$el.querySelectorAll('[data-column]').forEach((column) => {
            const badge = column.closest('section')?.querySelector('[data-count]');
            if (badge) badge.textContent = column.querySelectorAll('[data-task]').length;
        });
    },
    init() {
        this.$el.querySelectorAll('[data-column]').forEach((column) => {
            Sortable.create(column, {
                group: 'tasks',
                animation: 220,
                easing: 'cubic-bezier(.2, .7, .3, 1)',
                ghostClass: 'is-ghost',
                chosenClass: 'is-chosen',
                dragClass: 'is-dragging',
                forceFallback: true, // lets the dragged card tilt (see .is-dragging in app.css)
                fallbackOnBody: true,
                draggable: '[data-task]',
                onEnd: async (evt) => {
                    const id = evt.item.dataset.task;
                    const status = evt.to.dataset.column;
                    const cards = [...evt.to.querySelectorAll('[data-task]')];
                    try {
                        this.error = null;
                        await window.api(`/tasks/${id}/move`, { method: 'PATCH', body: { status, position: cards.indexOf(evt.item) } });
                        evt.item.classList.remove('drop-pop');
                        void evt.item.offsetWidth; // restart the animation
                        evt.item.classList.add('drop-pop');
                        this.recount();
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

/**
 * <div x-data="chatThread(pollUrl)">: keeps a chat thread live by polling every 4 s.
 * New messages, edits, deletions and read receipts appear without a page reload.
 * Pauses while the tab is hidden or the user is editing one of their messages.
 */
Alpine.data('chatThread', (url) => ({
    html: null,
    timer: null,
    init() {
        this.$nextTick(() => this.scrollToBottom());
        this.timer = setInterval(() => this.refresh(), 4000);
    },
    destroy() {
        clearInterval(this.timer);
    },
    nearBottom() {
        return this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 80;
    },
    scrollToBottom() {
        this.$el.scrollTop = this.$el.scrollHeight;
    },
    async refresh() {
        if (document.hidden || this.$el.contains(document.activeElement)) return;
        try {
            const { html } = await window.api(url);
            if (html === this.html) return;
            const stick = this.html === null || this.nearBottom();
            this.html = html;
            this.$el.innerHTML = html;
            if (stick) this.$nextTick(() => this.scrollToBottom());
        } catch {
            // Network hiccup or session expired: try again on the next tick.
        }
    },
}));

/**
 * Navbar search history (Figma "Recent Searches" overlay). Stored in this browser only;
 * storage can be unavailable (private mode), so every access is guarded.
 */
Alpine.data('recentSearches', () => ({
    key: 'collabspace.recentSearches',
    open: false,
    items: [],
    init() {
        try {
            this.items = JSON.parse(localStorage.getItem(this.key) || '[]');
        } catch {
            this.items = [];
        }
    },
    save() {
        try {
            localStorage.setItem(this.key, JSON.stringify(this.items));
        } catch {
            // ignore: history simply isn't kept
        }
    },
    remember(term) {
        term = (term || '').trim();
        if (term.length < 2) return;
        this.items = [term, ...this.items.filter((t) => t !== term)].slice(0, 6);
        this.save();
    },
    forget(term) {
        this.items = this.items.filter((t) => t !== term);
        this.save();
    },
    clear() {
        this.items = [];
        this.save();
        this.open = false;
    },
}));

/**
 * x-countup: numbers in stat cards count up from 0 when the page opens ("22%" → 0…22%).
 * Keeps any prefix/suffix text; skipped for people who prefer reduced motion.
 */
Alpine.directive('countup', (el) => {
    const match = el.textContent.trim().match(/^(\D*)(\d+(?:\.\d+)?)(.*)$/s);
    if (!match || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const [, prefix, number, suffix] = match;
    const target = parseFloat(number);
    const decimals = number.includes('.') ? number.split('.')[1].length : 0;
    const duration = 900;
    const start = performance.now();

    const tick = (now) => {
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
        if (p < 1) requestAnimationFrame(tick);
    };
    el.textContent = prefix + (0).toFixed(decimals) + suffix;
    requestAnimationFrame(tick);
});

/**
 * AI task planner dialog (see resources/views/tasks/_planner.blade.php):
 * asks the server for suggestions, lets the user edit/untick them, then creates the kept ones.
 */
Alpine.data('taskPlanner', ({ suggestUrl, createUrl, meetingId }) => ({
    visible: false,
    loading: false,
    saving: false,
    error: '',
    tasks: [],
    get kept() {
        return this.tasks.filter((t) => t.keep && t.title.trim()).length;
    },
    open() {
        this.visible = true;
        if (!this.tasks.length) this.suggest();
    },
    async suggest() {
        this.loading = true;
        this.error = '';
        this.tasks = [];
        try {
            const { data } = await window.api(suggestUrl, { method: 'POST' });
            this.tasks = data.map((t) => ({ ...t, description: t.description ?? '', due_date: t.due_date ?? '', keep: true }));
        } catch (e) {
            this.error = e.message;
        }
        this.loading = false;
    },
    async create() {
        this.saving = true;
        this.error = '';
        try {
            const tasks = this.tasks
                .filter((t) => t.keep && t.title.trim())
                .map(({ keep, ...t }) => ({ ...t, due_date: t.due_date || null, description: t.description || null }));
            const res = await window.api(createUrl, { method: 'POST', body: { tasks, meeting_id: meetingId } });
            window.location = res.redirect;
        } catch (e) {
            this.error = e.message;
            this.saving = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
