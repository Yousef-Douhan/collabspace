import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Real-time layer (Laravel Reverb). Optional by design: without a configured key (CI builds,
 * a fresh clone) or when the socket can't connect, window.Echo is simply absent / disconnected
 * and every page keeps working through its polling fallback.
 */
const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    try {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
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
