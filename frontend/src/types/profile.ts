export interface UserProfile {

    id:string;

    name:string;

    email:string;

    createdAt:string;

    role?:string;

    organizationId?:string;

}



export interface PasswordUpdate {

    currentPassword:string;

    newPassword:string;

}



export interface NotificationSettings {

    emailAlerts:boolean;

    deviceAlerts:boolean;

    weeklyReports:boolean;

    billingAlerts:boolean;

}
