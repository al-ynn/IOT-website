import type {
    BillingUsage,
    BillingEntitlements
} from "../../types/billing";

import {
    calculateUsageLimit
} from "../../utils/billing-usage";

import UsageProgress
from "./UsageProgress";

import UsageWarning
from "./UsageWarning";





interface Props {

    usage:BillingUsage;

    entitlements:BillingEntitlements;

}





export default function UsageOverview({

    usage,

    entitlements

}:Props){

    const deviceUsage =
        calculateUsageLimit(
            usage.devices,
            entitlements.deviceLimit
        );


    const userUsage =
        calculateUsageLimit(
            usage.users,
            entitlements.userLimit
        );


    const dashboardUsage =
        calculateUsageLimit(
            usage.dashboards,
            entitlements.dashboardLimit
        );





    return (

        <div
            className="
                rounded-2xl
                border
                border-white/10
                bg-[#0B1628]
                p-6
            "
        >

            <div>

                <h2
                    className="
                        text-xl
                        font-semibold
                        text-white
                    "
                >

                    Usage

                </h2>


                <p
                    className="
                        mt-1
                        text-sm
                        text-gray-400
                    "
                >

                    Monitor your organization's
                    resource usage.

                </p>

            </div>



            <div
                className="
                    mt-6
                    space-y-6
                "
            >

                <UsageProgress

                    label="Devices"

                    usage={deviceUsage}

                />



                <UsageWarning

                    label="Devices"

                    percentage={
                        deviceUsage.percentage
                    }

                />



                <UsageProgress

                    label="Users"

                    usage={userUsage}

                />



                <UsageWarning

                    label="Users"

                    percentage={
                        userUsage.percentage
                    }

                />



                <UsageProgress

                    label="Dashboards"

                    usage={dashboardUsage}

                />



                <UsageWarning

                    label="Dashboards"

                    percentage={
                        dashboardUsage.percentage
                    }

                />

            </div>

        </div>

    );

}

export interface AdminBillingOrganization {

    id:string;

    name:string;

    planName:string;

    planId:string;

    subscriptionStatus:
        | "trialing"
        | "active"
        | "past_due"
        | "cancelled"
        | "expired";

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