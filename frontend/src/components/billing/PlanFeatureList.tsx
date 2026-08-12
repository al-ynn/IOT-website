import {

    BILLING_FEATURE_LABELS

}

from "../../constants/billing-feature-labels";



interface Props {

    features:string[];

}



export default function PlanFeatureList({

    features

}:Props){

    return (

        <ul className="space-y-3">

            {features.map(feature=>(

                <li

                    key={feature}

                    className="
                        flex
                        items-center
                        gap-3
                        text-sm
                        text-gray-300
                    "

                >

                    <span>

                        ✓

                    </span>

                    <span>

                        {
                            BILLING_FEATURE_LABELS[feature]
                            ?? feature
                        }

                    </span>

                </li>

            ))}

        </ul>

    );

}