export type NotificationChannel =

    | "in_app"

    | "email"

    | "push"

    | "webhook";





export type NotificationSeverity =

    | "info"

    | "warning"

    | "critical";





export interface Notification {


    id:string;


    title:string;


    message:string;


    channel:NotificationChannel;


    severity:NotificationSeverity;


    read:boolean;


    createdAt:string;


}

export type NotificationCategory = "device" | "automation" | "billing" | "organization" | "system";
