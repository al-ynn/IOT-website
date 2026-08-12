import {

    Link

} from "react-router-dom";





export default function CheckoutCancelled(){

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

                    Checkout Cancelled

                </h1>


                <p
                    className="
                        mt-3
                        text-gray-400
                    "
                >

                    No subscription changes were made.

                </p>


                <Link

                    to="/pricing"

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

                    Return to Plans

                </Link>

            </div>

        </div>

    );

}