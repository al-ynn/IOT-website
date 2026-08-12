import {

    useEffect,

    useState

} from "react";


import {

    useNavigate

} from "react-router-dom";


import {

    getBillingPlans,

    getSubscription

} from "../../services/billing.service";


import {

    useBilling

} from "../../hooks/useBilling";


import type {

    BillingPlan,

    Subscription as SubscriptionType

} from "../../types/billing";


import SubscriptionCard
from "../../components/billing/SubscriptionCard";


import BillingSummary
from "../../components/billing/BillingSummary";





export default function Subscription(){

    const navigate =
        useNavigate();


    const {

        entitlements

    } = useBilling();


    const [

        subscription,

        setSubscription

    ] = useState<SubscriptionType|null>(null);


    const [

        plan,

        setPlan

    ] = useState<BillingPlan|null>(null);


    const [

        loading,

        setLoading

    ] = useState(true);





    useEffect(()=>{

        async function load(){

            try {

                const [

                    subscriptionData,

                    plans

                ] = await Promise.all([

                    getSubscription(),

                    getBillingPlans()

                ]);


                setSubscription(
                    subscriptionData
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
            finally {

                setLoading(false);

            }

        }


        load();

    },[]);





    if(loading){

        return (

            <p className="text-white">

                Loading billing information...

            </p>

        );

    }





    if(!subscription || !plan){

        return (

            <div className="space-y-4">

                <h1
                    className="
                        text-3xl
                        font-bold
                        text-white
                    "
                >

                    Subscription

                </h1>


                <p className="text-gray-400">

                    No active subscription was found.

                </p>


                <button

                    onClick={() =>
                        navigate("/pricing")
                    }

                    className="
                        rounded-lg
                        bg-primary
                        px-5
                        py-3
                        text-white
                    "
                >

                    View Plans

                </button>

            </div>

        );

    }





    function manageSubscription(){

        navigate("/app/billing");

    }





    return (

        <div
            className="
                mx-auto
                max-w-5xl
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

                    Subscription

                </h1>


                <p className="mt-2 text-gray-400">

                    Manage your organization's billing plan.

                </p>

            </div>



            <SubscriptionCard

                subscription={subscription}

                plan={plan}

                onManage={
                    manageSubscription
                }

            />



            {entitlements && (

                <BillingSummary

                    entitlements={
                        entitlements
                    }

                />

            )}

        </div>

    );

}
