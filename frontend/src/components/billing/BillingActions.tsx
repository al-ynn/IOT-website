import {

    useState

} from "react";


import {

    useNavigate

} from "react-router-dom";


import {

    cancelSubscription,

    resumeSubscription

} from "../../services/billing.service";


import type {

    Subscription,

    BillingPlan

} from "../../types/billing";

interface Props {

    subscription:Subscription;

    plan:BillingPlan;

    onUpdated:()=>void;

}




export default function BillingActions({

    subscription,

    plan,

    onUpdated

}:Props){

    const navigate =
        useNavigate();


    const [

        processing,

        setProcessing

    ] = useState(false);


    const [

        error,

        setError

    ] = useState("");





    const isLifetime =
        plan.interval === "lifetime";





    async function cancel(){

        const confirmed =
            window.confirm(

                "Are you sure you want to cancel your subscription?"

            );


        if(!confirmed){

            return;

        }


        setProcessing(true);

        setError("");


        try {

            await cancelSubscription();

            onUpdated();

        }
        catch {

            setError(

                "Unable to cancel the subscription."

            );

        }
        finally {

            setProcessing(false);

        }

    }





    async function resume(){

        setProcessing(true);

        setError("");


        try {

            await resumeSubscription();

            onUpdated();

        }
        catch {

            setError(

                "Unable to resume the subscription."

            );

        }
        finally {

            setProcessing(false);

        }

    }





    if(isLifetime){

        return (

            <div

                className="
                    rounded-xl
                    border
                    border-white/10
                    bg-[#0B1628]
                    p-5
                "

            >

                <p className="text-white">

                    Lifetime Access

                </p>


                <p

                    className="
                        mt-1
                        text-sm
                        text-gray-400
                    "

                >

                    Your access does not require
                    recurring billing.

                </p>

            </div>

        );

    }





    return (

        <div

            className="
                rounded-xl
                border
                border-white/10
                bg-[#0B1628]
                p-5
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

                    <h2 className="text-white">

                        Subscription Actions

                    </h2>


                    <p

                        className="
                            mt-1
                            text-sm
                            text-gray-400
                        "

                    >

                        Manage your current subscription.

                    </p>

                </div>



                <div className="flex gap-3">

                    <button

                        onClick={() =>
                            navigate("/pricing")
                        }

                        className="
                            rounded-lg
                            border
                            border-white/10
                            px-4
                            py-2
                            text-sm
                            text-gray-300
                        "

                    >

                        Change Plan

                    </button>



                    {subscription.cancelAtPeriodEnd ? (

                        <button

                            onClick={resume}

                            disabled={processing}

                            className="
                                rounded-lg
                                bg-primary
                                px-4
                                py-2
                                text-sm
                                text-white
                                disabled:opacity-50
                            "

                        >

                            {processing
                                ? "Processing..."
                                : "Resume Subscription"
                            }

                        </button>

                    ) : (

                        <button

                            onClick={cancel}

                            disabled={processing}

                            className="
                                rounded-lg
                                border
                                border-red-500/30
                                px-4
                                py-2
                                text-sm
                                text-red-300
                                disabled:opacity-50
                            "

                        >

                            {processing
                                ? "Processing..."
                                : "Cancel Subscription"
                            }

                        </button>

                    )}

                </div>

            </div>



            {error && (

                <p

                    className="
                        mt-4
                        text-sm
                        text-red-400
                    "

                >

                    {error}

                </p>

            )}

        </div>

    );

}
