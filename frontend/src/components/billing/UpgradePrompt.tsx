import {

    Link

}

from "react-router-dom";





interface Props {

    title?:string;

    description?:string;

}





export default function UpgradePrompt({

    title="Premium Feature",

    description=
        "Upgrade your plan to unlock this feature."

}:Props){



    return (

        <div

            className="
                rounded-xl
                border
                border-white/10
                bg-[#0B1628]
                p-6
                text-center
            "

        >


            <h3

                className="
                    text-lg
                    font-semibold
                    text-white
                "

            >

                {title}

            </h3>



            <p

                className="
                    mt-2
                    text-sm
                    text-gray-400
                "

            >

                {description}

            </p>



            <Link

                to="/pricing"

                className="
                    mt-5
                    inline-block
                    rounded-lg
                    bg-primary
                    px-5
                    py-3
                    text-white
                "

            >

                View Plans

            </Link>


        </div>

    );

}