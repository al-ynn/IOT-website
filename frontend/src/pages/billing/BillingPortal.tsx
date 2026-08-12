import {
    useCallback,
    useEffect,
    useState
} from "react";

import {
    getBillingPlans,
    getEntitlements,
    getPaymentHistory,
    getSubscription,
    getBillingUsage
} from "../../services/billing.service";

import type {
    BillingPlan,
    Subscription,
    BillingEntitlements,
    PaymentRecord,
    BillingUsage
} from "../../types/billing";

import SubscriptionCard
from "../../components/billing/SubscriptionCard";

import BillingSummary
from "../../components/billing/BillingSummary";

import PaymentHistory
from "../../components/billing/PaymentHistory";

import BillingActions
from "../../components/billing/BillingActions";

import UsageOverview
from "../../components/billing/UsageOverview";





export default function BillingPortal(){

    const [
        subscription,
        setSubscription
    ] = useState<Subscription|null>(null);


    const [
        plan,
        setPlan
    ] = useState<BillingPlan|null>(null);


    const [
        entitlements,
        setEntitlements
    ] = useState<BillingEntitlements|null>(null);


    const [
        payments,
        setPayments
    ] = useState<PaymentRecord[]>([]);


    const [
        usage,
        setUsage
    ] = useState<BillingUsage|null>(null);


    const [
        loading,
        setLoading
    ] = useState(true);


    const [
        error,
        setError
    ] = useState("");





    const loadBilling =

        useCallback(async()=>{

            setLoading(true);

            setError("");


            try {

                const [

                    subscriptionData,

                    plans,

                    entitlementData,

                    paymentData,

                    usageData

                ] = await Promise.all([

                    getSubscription(),

                    getBillingPlans(),

                    getEntitlements(),

                    getPaymentHistory(),

                    getBillingUsage()

                ]);


                setSubscription(
                    subscriptionData
                );


                setEntitlements(
                    entitlementData
                );


                setPayments(
                    paymentData
                );


                setUsage(
                    usageData
                );


                const currentPlan =
                    plans.find(

                        item =>
                            item.id ===
                            subscriptionData.planId

                    );


                setPlan(
                    currentPlan ?? null
                );

            }
            catch(error){

                console.error(
                    "Failed to load billing:",
                    error
                );


                setError(

                    "Unable to load your billing information."

                );

            }
            finally {

                setLoading(false);

            }

        },[]);





    useEffect(()=>{

        // eslint-disable-next-line react-hooks/set-state-in-effect
        loadBilling();

    },[loadBilling]);





    if(loading){

        return (

            <div
                className="
                    flex
                    min-h-[40vh]
                    items-center
                    justify-center
                "
            >

                <p className="text-gray-400">

                    Loading billing...

                </p>

            </div>

        );

    }





    if(error){

        return (

            <div
                className="
                    rounded-xl
                    border
                    border-red-500/20
                    bg-red-500/10
                    p-5
                    text-red-300
                "
            >

                {error}

            </div>

        );

    }





    if(!subscription || !plan){

        return (

            <div className="space-y-5">

                <h1
                    className="
                        text-3xl
                        font-bold
                        text-white
                    "
                >

                    Billing

                </h1>


                <p className="text-gray-400">

                    No active subscription was found.

                </p>

            </div>

        );

    }





    return (

        <div
            className="
                mx-auto
                max-w-6xl
                space-y-6
            "
        >

            <div>

                <h1
                    className="
                        text-3xl
                        font-bold
                        text-white
                    "
                >

                    Billing

                </h1>


                <p
                    className="
                        mt-2
                        text-gray-400
                    "
                >

                    Manage your organization's subscription,
                    plan, usage, and payment history.

                </p>

            </div>



            <SubscriptionCard

                subscription={subscription}

                plan={plan}

                onManage={()=>{}}

            />



            <BillingActions

                subscription={subscription}

                plan={plan}

                onUpdated={loadBilling}

            />



            {entitlements && (

                <BillingSummary

                    entitlements={entitlements}

                />

            )}



            {entitlements && usage && (

                <UsageOverview

                    usage={usage}

                    entitlements={entitlements}

                />

            )}



            <PaymentHistory

                payments={payments}

            />

        </div>

    );

}
