import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Real-time layer (Laravel Reverb). Optional by design: without a key, or when the socket
 * can't connect, window.Echo is absent / disconnected and every page keeps working through
 * its polling fallback.
 *
 * Settings come from <meta name="reverb"> (rendered by the server at runtime, so the same
 * build works locally, in Docker and on Render); VITE_REVERB_* is the fallback.
 */
const meta = (() => {
    try {
        return JSON.parse(document.querySelector('meta[name="reverb"]')?.content || 'null');
    } catch {
        return null;
    }
})();

const settings = meta ?? {
    key: import.meta.env.VITE_REVERB_APP_KEY,
    host: import.meta.env.VITE_REVERB_HOST,
    port: import.meta.env.VITE_REVERB_PORT,
    scheme: import.meta.env.VITE_REVERB_SCHEME,
};

if (settings.key) {
    try {
        const tls = (settings.scheme ?? 'https') === 'https';
        const port = Number(settings.port) || (tls ? 443 : 80);
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: settings.key,
            wsHost: settings.host || window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: tls,
            enabledTransports: ['ws', 'wss'],
        });
    } catch (e) {
        console.warn('[realtime] disabled:', e.message);
        window.Echo = undefined;
    }
}

/** True once the WebSocket is actually connected. */
window.realtimeOnline = () => window.Echo?.connector?.pusher?.connection?.state === 'connected';

/** Calls fn(isOnline) now and on every connection change (connected / unavailable / …). */
window.onRealtimeState = (fn) => {
    fn(window.realtimeOnline());
    window.Echo?.connector?.pusher?.connection?.bind('state_change', () => fn(window.realtimeOnline()));
};
