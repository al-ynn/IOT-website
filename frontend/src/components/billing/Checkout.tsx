import {

    useEffect,

    useState

} from "react";


import {

    useNavigate,

    useSearchParams

} from "react-router-dom";


import {

    getBillingPlans

} from "../../services/billing.service";
import { activateFreePlan } from "../../services/billing.service";


import {

    createCheckoutSession

} from "../../services/checkout.service";


import type {

    BillingPlan

} from "../../types/billing";


import CheckoutSummary

from "../../components/billing/CheckoutSummary";





export default function Checkout(){

    const navigate =
        useNavigate();


    const [

        searchParams

    ] = useSearchParams();


    const [

        plan,

        setPlan

    ] = useState<BillingPlan|null>(null);


    const [

        loading,

        setLoading

    ] = useState(true);


    const [

        processing,

        setProcessing

    ] = useState(false);


    const [

        error,

        setError

    ] = useState("");





    useEffect(()=>{

        async function load(){

            const planId =
                searchParams.get(
                    "plan"
                );


            if(!planId){

                setError(
                    "No plan was selected."
                );

                setLoading(false);

                return;

            }


            try {

                const plans =
                    await getBillingPlans();


                const selected =
                    plans.find(
                        item =>
                            item.id ===
                            planId
                    );


                if(!selected){

                    setError(
                        "The selected plan could not be found."
                    );

                    return;

                }


                setPlan(selected);

            }
            catch{

                setError(
                    "Unable to load the selected plan."
                );

            }
            finally {

                setLoading(false);

            }

        }


        load();

    },[searchParams]);





    async function proceed(){

        if(!plan){

            return;

        }


        setProcessing(true);

        setError("");


        try {

            /*
             * Free plans do not require payment.
             * The backend should handle activation.
             */

            if(plan.price === 0){
                await activateFreePlan();
                navigate(
                    "/app/billing"
                );

                return;

            }


            const session =
                await createCheckoutSession({

                    planId:plan.id

                });


            /*
             * The backend returns the
             * payment provider checkout URL.
             */

            window.location.href =
                session.checkoutUrl;

        }
        catch{

            setError(
                "Unable to start checkout. Please try again."
            );

            setProcessing(false);

        }

    }





    if(loading){

        return (

            <div className="text-white">

                Loading checkout...

            </div>

        );

    }





    if(!plan){

        return (

            <div className="space-y-5">

                <h1
                    className="
                        text-3xl
                        font-bold
                        text-white
                    "
                >

                    Checkout

                </h1>


                <p className="text-red-400">

                    {error ||
                        "Unable to load checkout."
                    }

                </p>

            </div>

        );

    }





    return (

        <div
            className="
                mx-auto
                max-w-2xl
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

                    Checkout

                </h1>


                <p className="mt-2 text-gray-400">

                    Review your plan before continuing.

                </p>

            </div>



            <CheckoutSummary
                plan={plan}
            />



            {error && (

                <div
                    className="
                        rounded-lg
                        border
                        border-red-500/20
                        bg-red-500/10
                        p-4
                        text-sm
                        text-red-300
                    "
                >

                    {error}

                </div>

            )}



            <div
                className="
                    flex
                    flex-col
                    gap-3
                    sm:flex-row
                    sm:justify-end
                "
            >

                <button

                    onClick={() =>
                        navigate("/pricing")
                    }

                    className="
                        rounded-lg
                        border
                        border-white/10
                        px-5
                        py-3
                        text-gray-300
                    "
                >

                    Back

                </button>


                <button

                    onClick={proceed}

                    disabled={processing}

                    className="
                        rounded-lg
                        bg-primary
                        px-5
                        py-3
                        text-white
                        disabled:cursor-not-allowed
                        disabled:opacity-50
                    "
                >

                    {processing
                        ? "Starting checkout..."
                        : plan.price === 0
                            ? "Continue"
                            : "Continue to Payment"
                    }

                </button>

            </div>

        </div>

    );

}
