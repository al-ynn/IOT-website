import type {
    UsageLimit
} from "../types/billing";



export function calculateUsageLimit(

    current:number,

    limit:number

):UsageLimit{

    if(limit <= 0){

        return {

            current,

            limit,

            percentage:100,

            remaining:0,

            reached:true

        };

    }


    const percentage =

        Math.min(

            100,

            Math.round(

                (current / limit) * 100

            )

        );


    return {

        current,

        limit,

        percentage,

        remaining:Math.max(

            0,

            limit - current

        ),

        reached:current >= limit

    };

}