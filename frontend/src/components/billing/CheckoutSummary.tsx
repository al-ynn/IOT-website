import type {

    BillingPlan

} from "../../types/billing";





interface Props {

    plan:BillingPlan;

}





export default function CheckoutSummary({

    plan

}:Props){

    const isLifetime =
        plan.interval === "lifetime";


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

            <p
                className="
                    text-sm
                    text-gray-400
                "
            >

                Selected Plan

            </p>


            <h2
                className="
                    mt-1
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
                    text-gray-400
                "
            >

                {plan.description}

            </p>



            <div
                className="
                    mt-6
                    border-t
                    border-white/10
                    pt-6
                "
            >

                <div
                    className="
                        flex
                        items-center
                        justify-between
                    "
                >

                    <span className="text-gray-400">

                        Price

                    </span>


                    <span
                        className="
                            text-xl
                            font-semibold
                            text-white
                        "
                    >

                        {plan.price === 0

                            ? "Free"

                            : `${plan.price} ${plan.currency}`

                        }

                    </span>

                </div>


                <div
                    className="
                        mt-3
                        flex
                        items-center
                        justify-between
                    "
                >

                    <span className="text-gray-400">

                        Billing

                    </span>


                    <span className="text-white">

                        {isLifetime ? "One-time" : plan.interval === "yearly" ? "Yearly" : "Monthly"}

                    </span>

                </div>


                <div
                    className="
                        mt-3
                        flex
                        items-center
                        justify-between
                    "
                >

                    <span className="text-gray-400">

                        Device limit

                    </span>


                    <span className="text-white">

                        {plan.deviceLimit}

                    </span>

                </div>

            </div>

        </div>

    );

}
