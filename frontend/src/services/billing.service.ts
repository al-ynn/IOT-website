import api from "./api";


import type {

    BillingPlan,

    Subscription,

    BillingEntitlements,

    PaymentRecord

} from "../types/billing";

import type {

    BillingUsage

} from "../types/billing";

import type {

    AdminBillingOrganization,

    AdminBillingStats

} from "../types/billing";

export async function getBillingPlans(){

    const response =

        await api.get<BillingPlan[]>(

            "/billing/plans"

        );


    return response.data;

}



export async function getSubscription(){

    const response =

        await api.get<Subscription>(

            "/billing/subscription"

        );


    return response.data;

}



export async function getEntitlements(){

    const response =

        await api.get<BillingEntitlements>(

            "/billing/entitlements"

        );


    return response.data;

}



export async function getPaymentHistory(){

    const response =

        await api.get<PaymentRecord[]>(

            "/billing/payments"

        );


    return response.data;

}


export async function cancelSubscription(){

    const response =

        await api.post(

            "/billing/subscription/cancel"

        );


    return response.data;

}


export async function resumeSubscription(){

    const response =

        await api.post(

            "/billing/subscription/resume"

        );


    return response.data;

}

export async function getBillingUsage(){

    const response =

        await api.get<BillingUsage>(

            "/billing/usage"

        );

    return response.data;

}

export async function getAdminBillingOrganizations(){

    const response =

        await api.get<AdminBillingOrganization[]>(

            "/admin/billing/organizations"

        );

    return response.data;

}

export async function getAdminBillingStats(){

    const response =

        await api.get<AdminBillingStats>(

            "/admin/billing/stats"

        );

    return response.data;

}

export async function activateFreePlan(){
    const response=await api.post("/billing/subscription/free");
    return response.data;
}
