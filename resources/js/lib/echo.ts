import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Echo?: Echo<'reverb'>;
        Pusher?: typeof Pusher;
    }
}

let echoInstance: Echo<'reverb'> | null = null;

function parsePort(value: string | undefined, fallback: number): number {
    if (!value) {
        return fallback;
    }

    const parsedValue = Number.parseInt(value, 10);

    if (Number.isNaN(parsedValue)) {
        return fallback;
    }

    return parsedValue;
}

export function initializeEcho(): Echo<'reverb'> | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    const appKey = import.meta.env.VITE_REVERB_APP_KEY;

    if (!appKey) {
        return null;
    }

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: parsePort(import.meta.env.VITE_REVERB_PORT, 80),
        wssPort: parsePort(import.meta.env.VITE_REVERB_PORT, 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echoInstance;

    return echoInstance;
}

export function getEcho(): Echo<'reverb'> | null {
    if (echoInstance) {
        return echoInstance;
    }

    return initializeEcho();
}
