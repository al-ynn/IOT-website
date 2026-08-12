import {
    useEffect,
    useState
} from "react";

import {
    useNavigate
} from "react-router-dom";

import type {
    BillingPlan
} from "../../types/billing";

import {
    getBillingPlans,
    getSubscription
} from "../../services/billing.service";

import PricingGrid
from "../../components/billing/PricingGrid";
import { getToken } from "../../services/storage.service";





export default function Pricing(){

    const navigate =
        useNavigate();


    const [
        plans,
        setPlans
    ] = useState<BillingPlan[]>([]);


    const [
        currentPlanId,
        setCurrentPlanId
    ] = useState<string>();


    const [
        loading,
        setLoading
    ] = useState(true);


    const [
        error,
        setError
    ] = useState("");





    useEffect(()=>{

        async function load(){

            try {

                const planData = await getBillingPlans();
                const subscription = getToken() ? await getSubscription() : null;


                setPlans(planData);


                setCurrentPlanId(
                    subscription?.planId
                );

            }
            catch(error){

                console.error(
                    "Failed to load pricing:",
                    error
                );

                setError(
                    "Unable to load pricing plans."
                );

            }
            finally {

                setLoading(false);

            }

        }


        load();

    },[]);





    function selectPlan(
        plan:BillingPlan
    ){

        if(
            plan.id ===
            currentPlanId
        ){

            return;

        }


        navigate(

            `/app/checkout?plan=${encodeURIComponent(
                plan.id
            )}`

        );

    }





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

                    Loading pricing plans...

                </p>

            </div>

        );

    }





    if(error){

        return (

            <div
                className="
                    mx-auto
                    max-w-2xl
                    p-5
                "
            >

                <div
                    className="
                        rounded-xl
                        border
                        border-red-500/20
                        bg-red-500/10
                        p-5
                        text-center
                        text-red-300
                    "
                >

                    {error}

                </div>

            </div>

        );

    }





    return (

        <div
            className="
                mx-auto
                max-w-7xl
                space-y-8
                p-5
            "
        >

            <div className="text-center">

                <h1
                    className="
                        text-4xl
                        font-bold
                        text-white
                    "
                >

                    Choose Your Plan

                </h1>


                <p
                    className="
                        mx-auto
                        mt-3
                        max-w-2xl
                        text-gray-400
                    "
                >

                    Choose the plan that fits your
                    IoT deployment and business needs.

                </p>

            </div>



            {plans.length === 0 ? (

                <div
                    className="
                        rounded-xl
                        border
                        border-white/10
                        bg-[#0B1628]
                        p-8
                        text-center
                    "
                >

                    <p className="text-gray-400">

                        No pricing plans are currently available.

                    </p>

                </div>

            ) : (

                <PricingGrid

                    plans={plans}

                    currentPlanId={currentPlanId}

                    onSelect={selectPlan}

                />

            )}

        </div>

    );

}
