/**
 * IOT-PLATFORM
 * Global Frontend Configuration
 */


export const APP_CONFIG = {

    name: "IOT-PLATFORM",

    shortName: "IOT",

    description:
        "Enterprise IoT platform for device management, telemetry, automation, analytics and AI intelligence.",


    version:
        "1.0.0",


    environment:
        import.meta.env.MODE,


    company: {

        name:
            "IOT-PLATFORM",


        supportEmail:
            "support@iot-platform.com"

    }

};





export const API_CONFIG = {


    baseURL:

        import.meta.env.VITE_API_URL
        ??
        "/api",


    timeout:

        30000,


    headers: {

        Accept:
            "application/json",

        "Content-Type":
            "application/json"

    }


};





export const SOCKET_CONFIG = {


    enabled:
        true,


    host:

        import.meta.env.VITE_REVERB_HOST
        ??
        "localhost",


    port:

        Number(
            import.meta.env.VITE_REVERB_PORT
            ??
            8080
        ),


    scheme:

        import.meta.env.VITE_REVERB_SCHEME
        ??
        "http"


};





export const FEATURES = {


    authentication:
        true,


    organizations:
        true,


    devices:
        true,


    assets:
        true,


    telemetry:
        true,


    automation:
        true,


    alarms:
        true,


    aiAssistant:
        true,


    digitalTwin:
        true,


    marketplace:
        false


};





export const ROUTES = {


    public: {


        home:
            "/",


        platform:
            "/platform",


        solutions:
            "/solutions",


        developers:
            "/developers",


        pricing:
            "/pricing"


    },



    auth: {


        login:
            "/login",


        register:
            "/register"


    },



    app: {


        dashboard:
            "/app",


        devices:
            "/app/devices",


        telemetry:
            "/app/telemetry",


        automation:
            "/app/automation",


        alarms:
            "/app/alarms"


    }


};





export const IOT_CONFIG = {


    telemetry: {


        refreshInterval:
            5000,


        maxDataPoints:
            1000


    },


    device: {


        heartbeat:
            30000,


        offlineThreshold:
            120000


    }


};





export default {

    APP_CONFIG,

    API_CONFIG,

    SOCKET_CONFIG,

    FEATURES,

    ROUTES,

    IOT_CONFIG

};
