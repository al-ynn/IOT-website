import type {
    UsageLimit
} from "../../types/billing";



interface Props {

    label:string;

    usage:UsageLimit;

}



export default function UsageProgress({

    label,

    usage

}:Props){

    return (

        <div className="space-y-2">

            <div
                className="
                    flex
                    items-center
                    justify-between
                "
            >

                <span
                    className="
                        text-sm
                        text-gray-300
                    "
                >

                    {label}

                </span>


                <span
                    className="
                        text-sm
                        text-gray-400
                    "
                >

                    {usage.current} / {usage.limit}

                </span>

            </div> 



            <div
                className="
                    h-2
                    overflow-hidden
                    rounded-full
                    bg-white/10
                "
            >

                <div
                    className="
                        h-full
                        rounded-full
                        bg-primary
                    "
                    style={{
                        width:
                            `${usage.percentage}%`
                    }}
                />

            </div>



            <p
                className="
                    text-xs
                    text-gray-500
                "
            >

                {usage.reached

                    ? "Limit reached"

                    : `${usage.remaining} remaining`

                }

            </p>

        </div>

    );

}