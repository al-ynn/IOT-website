import type {
    SubscriptionStatus as SubscriptionStatusType
} from "../../types/billing";



interface Props {

    status:SubscriptionStatusType;

}



export default function SubscriptionStatus({

    status

}:Props){

    const labels:Record<
        SubscriptionStatusType,
        string
    > = {

        trialing:"Trial",

        active:"Active",

        past_due:"Past Due",

        cancelled:"Cancelled",

        expired:"Expired"

    };


    return (

        <span
            className="
                inline-flex
                rounded-full
                border
                border-white/10
                px-3
                py-1
                text-sm
                text-gray-300
            "
        >

            {labels[status]}

        </span>

    );

}