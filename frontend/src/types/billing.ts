export type BillingInterval =
    | "monthly"
    | "yearly"
    | "lifetime";


export type SubscriptionStatus =
    | "trialing"
    | "active"
    | "past_due"
    | "cancelled"
    | "expired";


export interface BillingPlan {

    id:string;

    name:string;

    description:string;

    interval:BillingInterval;

    price:number;

    currency:string;

    features:string[];

    deviceLimit:number;

    userLimit:number;

    dashboardLimit:number;

    automationLimit:number;

    active:boolean;

}


export interface Subscription {

    id:string;

    organizationId:string;

    planId:string;

    status:SubscriptionStatus;

    currentPeriodStart:string;

    currentPeriodEnd?:string;

    cancelAtPeriodEnd:boolean;

    createdAt:string;

}


export interface BillingEntitlements {

    planId:string;

    features:string[];

    deviceLimit:number;

    userLimit:number;

    dashboardLimit:number;

    automationLimit:number;

}

export interface EntitlementCheck {

    allowed:boolean;

    reason?:
        | "feature_not_included"
        | "limit_reached"
        | "subscription_inactive";

    limit?:number;

    currentUsage?:number;

}

export interface PaymentRecord {

    id:string;

    organizationId:string;

    transactionId?:string;

    planId:string;

    amount:number;

    currency:string;

    status:
        | "pending"
        | "paid"
        | "failed"
        | "refunded";

    paidAt?:string;

    createdAt:string;

}

export interface BillingUsage {

    devices:number;

    users:number;

    dashboards:number;

    automations:number;

}

export interface UsageLimit {

    current:number;

    limit:number;

    percentage:number;

    remaining:number;

    reached:boolean;

}


export interface AdminBillingOrganization {

    id:string;

    name:string;

    planName:string;

    planId:string|null;

    subscriptionStatus:SubscriptionStatus;

    devices:number;

    users:number;

    dashboards:number;

    createdAt:string;

}


export interface AdminBillingStats {

    totalOrganizations:number;

    activeSubscriptions:number;

    cancelledSubscriptions:number;

    pastDueSubscriptions:number;

    lifetimeSubscriptions:number;

    monthlyRecurringRevenue:number;

}
