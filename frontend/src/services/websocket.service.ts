import Echo from "laravel-echo";

import Pusher from "pusher-js";


const reverbKey = import.meta.env.VITE_REVERB_APP_KEY?.trim();

if (reverbKey) {
    (window as typeof window & { Pusher: typeof Pusher }).Pusher = Pusher;
}

export const echo = reverbKey
    ? new Echo({
        broadcaster:"reverb",
        key:reverbKey,
        wsHost:import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort:Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort:Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS:import.meta.env.VITE_REVERB_SCHEME === "https",
        enabledTransports:["ws","wss"]
    })
    : null;
