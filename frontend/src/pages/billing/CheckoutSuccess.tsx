import {

    Link

} from "react-router-dom";





export default function CheckoutSuccess(){

    return (

        <div
            className="
                flex
                min-h-[60vh]
                items-center
                justify-center
                p-5
            "
        >

            <div
                className="
                    w-full
                    max-w-md
                    rounded-2xl
                    border
                    border-white/10
                    bg-[#0B1628]
                    p-8
                    text-center
                "
            >

                <h1
                    className="
                        text-3xl
                        font-bold
                        text-white
                    "
                >

                    Payment Submitted

                </h1>


                <p
                    className="
                        mt-3
                        text-gray-400
                    "
                >

                    Your payment was submitted successfully.
                    Your subscription will be activated after
                    the payment is verified.

                </p>


                <Link

                    to="/app/subscription"

                    className="
                        mt-6
                        inline-block
                        rounded-lg
                        bg-primary
                        px-5
                        py-3
                        text-white
                    "
                >

                    View Subscription

                </Link>

            </div>

        </div>

    );

}