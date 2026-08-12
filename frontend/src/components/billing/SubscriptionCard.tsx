import type {

    BillingPlan,

    Subscription

} from "../../types/billing";


import SubscriptionStatus
from "./SubscriptionStatus";



interface Props {

    subscription:Subscription;

    plan:BillingPlan;

    onManage:()=>void;

}



export default function SubscriptionCard({

    subscription,

    plan,

    onManage

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

            <div
                className="
                    flex
                    flex-col
                    gap-4
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                "
            >

                <div>

                    <p
                        className="
                            text-sm
                            text-gray-400
                        "
                    >

                        Current Plan

                    </p>


                    <h2
                        className="
                            mt-1
                            text-3xl
                            font-bold
                            text-white
                        "
                    >

                        {plan.name}

                    </h2>

                </div>


                <SubscriptionStatus
                    status={
                        subscription.status
                    }
                />

            </div>



            <div
                className="
                    mt-8
                    grid
                    gap-5
                    sm:grid-cols-2
                "
            >

                <div>

                    <p className="text-sm text-gray-500">

                        Billing

                    </p>

                    <p className="mt-1 text-white">

                        {isLifetime
                            ? "One-time purchase"
                            : `${plan.price} ${plan.currency}${plan.interval === "yearly" ? " / year" : " / month"}`
                        }

                    </p>

                </div>



                <div>

                    <p className="text-sm text-gray-500">

                        Devices

                    </p>

                    <p className="mt-1 text-white">

                        Up to {plan.deviceLimit}

                    </p>

                </div>



                {!isLifetime && (

                    <div>

                        <p className="text-sm text-gray-500">

                            Current Period

                        </p>

                        <p className="mt-1 text-white">

                            {new Date(
                                subscription.currentPeriodStart
                            ).toLocaleDateString()}

                            {" — "}

                            {subscription.currentPeriodEnd
                                ? new Date(
                                    subscription.currentPeriodEnd
                                ).toLocaleDateString()
                                : "—"
                            }

                        </p>

                    </div>

                )}



                {!isLifetime && <div>

                    <p className="text-sm text-gray-500">

                        Cancellation

                    </p>

                    <p className="mt-1 text-white">

                        {subscription.cancelAtPeriodEnd
                            ? "Ends at current period"
                            : "Not scheduled"
                        }

                    </p>

                </div>}

            </div>



            {!isLifetime && (

                <button

                    onClick={onManage}

                    className="
                        mt-8
                        rounded-lg
                        bg-primary
                        px-5
                        py-3
                        text-white
                    "
                >

                    Manage Subscription

                </button>

            )}

        </div>

    );

}
