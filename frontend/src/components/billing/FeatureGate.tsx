import { useBilling }

from "../../hooks/useBilling";





interface Props {

    feature:string;

    children:React.ReactNode;

    fallback?:React.ReactNode;

}





export default function FeatureGate({

    feature,

    children,

    fallback=null

}:Props){



    const {

        checkFeature,

        loading

    } = useBilling();





    if(loading){

        return null;

    }





    const result =

        checkFeature(feature);





    if(!result.allowed){

        return <>{fallback}</>;

    }





    return <>{children}</>;

}
