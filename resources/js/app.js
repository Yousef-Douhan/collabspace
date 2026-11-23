import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import Chart from 'chart.js/auto';
import './echo';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

/** JSON request helper for the few AJAX interactions (Kanban drag & drop). */
window.api = async (url, { method = 'GET', body } = {}) => {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            // Lets the server skip broadcasting the change back to this very tab.
            ...(window.Echo?.socketId() ? { 'X-Socket-ID': window.Echo.socketId() } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || res.statusText);
    return res.json().catch(() => ({}));
};

/** Kanban board: each column is a Sortable list; dropping a card calls PATCH /tasks/{id}/move. */
Alpine.data('kanban', ({ projects = [], labels = {} } = {}) => ({
    error: null,
    notice: null,
    live: false,
    dragging: false,
    /** Keep the little counters in the column headers right after a move. */
    recount() {
        this.$el.querySelectorAll('[data-column]').forEach((column) => {
            const badge = column.closest('section')?.querySelector('[data-count]');
            if (badge) badge.textContent = column.querySelectorAll('[data-task]').length;
        });
    },
    /** Subscribe to every visible project's board channel (Reverb). */
    listen() {
        if (!window.Echo) return;
        window.onRealtimeState((online) => (this.live = online));
        projects.forEach((id) => {
            window.Echo.private(`project.${id}`).listen('.task.updated', (e) => this.applyRemote(e));
        });
    },
    /** Someone else changed the board: slide the card over, or offer a refresh. */
    applyRemote(e) {
        const card = this.$el.querySelector(`[data-task="${e.task_id}"]`);
        const column = this.$el.querySelector(`[data-column="${e.status}"]`);

        if (e.action === 'moved' && card && column && !this.dragging) {
            const others = [...column.querySelectorAll('[data-task]')].filter((c) => c !== card);
            column.insertBefore(card, others[e.position] ?? null);
            card.classList.remove('remote-flash');
            void card.offsetWidth;
            card.classList.add('remote-flash');
            this.recount();
            this.$dispatch('board-changed');
            this.toast((labels.moved || ':name moved a task').replace(':name', e.actor ?? ''));
            return;
        }
        if (e.action === 'deleted' && card) {
            card.remove();
            this.recount();
            this.toast((labels.deleted || ':name deleted a task').replace(':name', e.actor ?? ''));
            return;
        }
        // Created / edited (or a card hidden by the current filters): let the user refresh.
        this.notice = (labels.changed || ':name updated the board').replace(':name', e.actor ?? '');
    },
    toast(text) {
        this.$dispatch('realtime-toast', text);
    },
    init() {
        this.listen();
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
                onStart: () => (this.dragging = true),
                onEnd: async (evt) => {
                    this.dragging = false;
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

/* ───────── Theme (dark / light) ─────────
   The inline <head> script sets html.dark before paint; this keeps the choice, follows the
   device setting until the user picks one, recolours charts, and prints in light mode. */
const isDark = () => document.documentElement.classList.contains('dark');

/** Axis, grid and legend colours for the current theme. */
function themeChart(chart) {
    const dark = isDark();
    const tick = dark ? '#8F9CB4' : '#64748B';
    const grid = dark ? 'rgba(143,156,180,.14)' : '#EEF2F7';
    Object.values(chart.options.scales ?? {}).forEach((scale) => {
        scale.ticks = { ...(scale.ticks ?? {}), color: tick };
        scale.grid = { ...(scale.grid ?? {}), color: grid };
        scale.border = { ...(scale.border ?? {}), color: grid };
    });
    const legend = chart.options.plugins?.legend;
    if (legend) legend.labels = { ...(legend.labels ?? {}), color: tick };
    chart.update('none');
}

function setTheme(dark, { save = true, animate = true } = {}) {
    const root = document.documentElement;
    if (animate) {
        root.classList.add('theme-anim');
        setTimeout(() => root.classList.remove('theme-anim'), 350);
    }
    root.classList.toggle('dark', dark);
    if (save) {
        try {
            localStorage.setItem('cs-theme', dark ? 'dark' : 'light');
        } catch {
            // storage blocked: the theme still applies to this page
        }
    }
    Object.values(Chart.instances).forEach(themeChart);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
}

Alpine.data('themeToggle', () => ({
    toggle() {
        setTheme(!isDark());
    },
}));

// Follow the device setting while the user hasn't chosen.
window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', (e) => {
    let saved = null;
    try {
        saved = localStorage.getItem('cs-theme');
    } catch {}
    if (!saved) setTheme(e.matches, { save: false });
});

// Reports → Print / Save PDF always comes out light.
let darkBeforePrint = false;
window.addEventListener('beforeprint', () => {
    darkBeforePrint = isDark();
    if (darkBeforePrint) setTheme(false, { save: false, animate: false });
});
window.addEventListener('afterprint', () => {
    if (darkBeforePrint) setTheme(true, { save: false, animate: false });
});

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
        themeChart(new Chart(this.$el, config));
    },
}));

/**
 * <div x-data="chatThread({ url, channel, me })">: keeps a chat thread live.
 * With Reverb connected, a "conversation.updated" event triggers an instant refresh and
 * "typing" whispers show who is writing; polling (every 4 s, or 30 s as a safety net while
 * the socket is up) covers the cases where WebSockets are unavailable.
 * Pauses while the tab is hidden or the user is editing one of their messages.
 */
Alpine.data('chatThread', ({ url, channel = null, me = null }) => ({
    html: null,
    timer: null,
    live: false,
    typing: {},
    channelRef: null,
    init() {
        this.$nextTick(() => this.scrollToBottom());
        this.schedule(4000);

        if (window.Echo && channel) {
            this.channelRef = window.Echo.private(channel)
                .listen('.conversation.updated', () => this.refresh(true))
                .listenForWhisper('typing', ({ id, name }) => this.showTyping(id, name));
            window.onRealtimeState((online) => {
                this.live = online;
                this.schedule(online ? 30000 : 4000);
            });
        }
        // The message box (outside this element) tells us when the user types.
        window.addEventListener('chat-typing', () => this.whisperTyping());
    },
    destroy() {
        clearInterval(this.timer);
        if (window.Echo && channel) window.Echo.leave(channel);
    },
    schedule(ms) {
        clearInterval(this.timer);
        this.timer = setInterval(() => this.refresh(), ms);
    },
    get typingText() {
        const names = Object.values(this.typing).map((t) => t.name);
        return names.length ? names.join(', ') : '';
    },
    showTyping(id, name) {
        clearTimeout(this.typing[id]?.timeout);
        this.typing = { ...this.typing, [id]: { name, timeout: setTimeout(() => this.clearTyping(id), 3000) } };
        this.$nextTick(() => this.nearBottom() && this.scrollToBottom());
    },
    clearTyping(id) {
        const { [id]: _, ...rest } = this.typing;
        this.typing = rest;
    },
    lastWhisper: 0,
    whisperTyping() {
        if (!this.channelRef || !me || Date.now() - this.lastWhisper < 1500) return;
        this.lastWhisper = Date.now();
        this.channelRef.whisper('typing', me);
    },
    nearBottom() {
        return this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 80;
    },
    scrollToBottom() {
        this.$el.scrollTop = this.$el.scrollHeight;
    },
    async refresh(pushed = false) {
        if (!pushed && (document.hidden || this.$el.contains(document.activeElement))) return;
        if (pushed && this.$refs.messages?.contains(document.activeElement)) return; // don't wipe an open edit box
        try {
            const { html } = await window.api(url);
            if (html === this.html) return;
            const stick = this.html === null || this.nearBottom();
            this.html = html;
            this.$refs.messages.innerHTML = html;
            this.typing = {};
            if (stick) this.$nextTick(() => this.scrollToBottom());
        } catch {
            // Network hiccup or session expired: try again on the next tick.
        }
    },
}));

/**
 * <div x-data="liveDashboard({ projects, label })">: the dashboard follows the team in real time.
 * On any "task.updated" event (Reverb) it re-fetches the page once (debounced), swaps the
 * [data-live] regions whose text changed, counts changed numbers from their old to new value
 * and flashes those cards. Without a socket it simply stays a normal page.
 */
Alpine.data('liveDashboard', ({ projects = [], label = '' } = {}) => ({
    timer: null,
    init() {
        if (!window.Echo) return;
        projects.forEach((id) => {
            window.Echo.private(`project.${id}`).listen('.task.updated', (e) => {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.refresh(e), 600);
            });
        });
    },
    async refresh(e) {
        let doc;
        try {
            const res = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
            if (!res.ok) return;
            doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        } catch {
            return;
        }
        const text = (el) => el.textContent.replace(/\s+/g, ' ').trim();
        let changed = false;

        this.$el.querySelectorAll('[data-live]').forEach((region) => {
            const fresh = doc.querySelector(`[data-live="${region.dataset.live}"]`);
            if (!fresh || text(fresh) === text(region)) return;
            changed = true;

            const before = [...region.querySelectorAll('[data-stat]')].map((el) => el.textContent.trim());
            fresh.querySelectorAll('[x-countup]').forEach((el) => el.removeAttribute('x-countup'));
            region.innerHTML = fresh.innerHTML;

            region.querySelectorAll('[data-stat]').forEach((el, i) => {
                const after = el.textContent.trim();
                if (before[i] === undefined || before[i] === after) return;
                this.countFrom(el, before[i], after);
                const card = el.closest('.card');
                card?.classList.remove('remote-flash');
                void card?.offsetWidth;
                card?.classList.add('remote-flash');
            });
        });

        if (changed && e?.actor) this.$dispatch('realtime-toast', label.replace(':name', e.actor));
    },
    /** "3" → "4", "22%" → "33%": animate the number, keep the prefix/suffix. */
    countFrom(el, from, to) {
        const parse = (s) => s.match(/^(\D*)(\d+(?:\.\d+)?)(.*)$/s);
        const a = parse(from);
        const b = parse(to);
        if (!a || !b || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const start = performance.now();
        const x0 = parseFloat(a[2]);
        const x1 = parseFloat(b[2]);
        const tick = (now) => {
            const p = Math.min(1, (now - start) / 700);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = b[1] + Math.round(x0 + (x1 - x0) * eased) + b[3];
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
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
