interface Props {

    allowed:boolean;

    children:React.ReactNode;

    fallback?:React.ReactNode;

}





export default function LimitGate({

    allowed,

    children,

    fallback=null

}:Props){



    if(!allowed){

        return <>{fallback}</>;

    }



    return <>{children}</>;

}