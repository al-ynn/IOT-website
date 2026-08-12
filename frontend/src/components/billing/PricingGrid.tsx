import type {

    BillingPlan

}

from "../../types/billing";


import PricingCard

from "./PricingCard";





interface Props {

    plans:BillingPlan[];

    currentPlanId?:string;

    onSelect:(plan:BillingPlan)=>void;

}





export default function PricingGrid({

    plans,

    currentPlanId,

    onSelect

}:Props){

    return (

        <div

            className="
                grid
                gap-6
                md:grid-cols-2
                xl:grid-cols-3
            "

        >

            {plans.map(plan=>(

                <PricingCard

                    key={plan.id}

                    plan={plan}

                    currentPlanId={currentPlanId}

                    onSelect={onSelect}

                />

            ))}

        </div>

    );

}