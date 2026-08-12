interface Props {

    percentage:number;

    label:string;

}



export default function UsageWarning({

    percentage,

    label

}:Props){

    if(percentage < 80){

        return null;

    }


    const critical =
        percentage >= 100;


    return (

        <div
            className="
                rounded-lg
                border
                border-white/10
                bg-[#101F36]
                p-4
            "
        >

            <p
                className="
                    text-sm
                    text-gray-300
                "
            >

                {critical

                    ? `${label} limit reached.`

                    : `${label} usage is at ${percentage}%.`
                }

            </p>


            <p
                className="
                    mt-1
                    text-xs
                    text-gray-500
                "
            >

                {critical

                    ? "Upgrade your plan to increase your limit."

                    : "Consider upgrading before reaching your limit."
                }

            </p>

        </div>

    );

}