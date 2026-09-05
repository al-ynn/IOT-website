import usePermission

from "../../hooks/usePermission";





interface Props {


permission:string;


children:React.ReactNode;


}





export default function PermissionGate({

permission,

children

}:Props){



const {

can

}

=

usePermission();





if(!can(permission)){


return null;


}





return children;


}