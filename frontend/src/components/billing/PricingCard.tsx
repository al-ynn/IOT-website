import type {

    BillingPlan

}

from "../../types/billing";


import PlanFeatureList

from "./PlanFeatureList";





interface Props {

    plan:BillingPlan;

    currentPlanId?:string;

    onSelect:(plan:BillingPlan)=>void;

}





export default function PricingCard({

    plan,

    currentPlanId,

    onSelect

}:Props){

    const isCurrent =

        currentPlanId === plan.id;



    return (

        <div

            className={`
                flex
                h-full
                flex-col
                rounded-2xl
                border
                p-6
                ${
                    isCurrent
                        ? "border-primary"
                        : "border-white/10"
                }
                bg-[#0B1628]
            `}

        >


            <div>

                <h2

                    className="
                        text-2xl
                        font-bold
                        text-white
                    "

                >

                    {plan.name}

                </h2>



                <p

                    className="
                        mt-2
                        min-h-12
                        text-sm
                        text-gray-400
                    "

                >

                    {plan.description}

                </p>

            </div>



            <div className="mt-6">

                <span

                    className="
                        text-4xl
                        font-bold
                        text-white
                    "

                >

                    {plan.price === 0

                        ? "Free"

                        : `$${plan.price}`}

                </span>



                {plan.price > 0 && (

                    <span className="ml-2 text-gray-400">

                        {plan.interval === "lifetime" ? "one time" : plan.interval === "yearly" ? " / year" : " / month"}

                    </span>

                )}

            </div>



            <div

                className="
                    mt-6
                    text-sm
                    text-gray-400
                "

            >

                Up to {plan.deviceLimit} devices

            </div>



            <div className="mt-6">

                <PlanFeatureList

                    features={plan.features}

                />

            </div>



            <button

                disabled={isCurrent}

                onClick={()=>onSelect(plan)}

                className="
                    mt-auto
                    pt-8
                    w-full
                    rounded-lg
                    bg-primary
                    px-5
                    py-3
                    text-white
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "

            >

                {isCurrent

                    ? "Current Plan"

                    : plan.id === "free"

                        ? "Get Started"

                        : "Choose Plan"}

            </button>


        </div>

    );

}
