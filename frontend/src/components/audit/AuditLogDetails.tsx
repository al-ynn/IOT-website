import type {

    AuditLog

}

from "../../types/audit";





interface Props {

    log:AuditLog|null;

    onClose:()=>void;

}





export default function AuditLogDetails({

    log,

    onClose

}:Props){



    if(!log){

        return null;

    }



    return (

        <div

            className="
                rounded-xl
                border
                border-white/10
                bg-[#101F36]
                p-5
            "

        >


            <div className="
                flex
                items-center
                justify-between
            ">


                <h2 className="text-xl text-white">

                    Audit Details

                </h2>



                <button

                    onClick={onClose}

                    className="text-gray-400"

                >

                    Close

                </button>


            </div>



            <div className="mt-5 space-y-3">


                <p className="text-gray-300">

                    <span className="text-gray-500">

                        User:

                    </span>{" "}

                    {log.userName}

                </p>



                <p className="text-gray-300">

                    <span className="text-gray-500">

                        Action:

                    </span>{" "}

                    {log.action}

                </p>



                <p className="text-gray-300">

                    <span className="text-gray-500">

                        Resource:

                    </span>{" "}

                    {log.resourceName ||

                     log.resourceType}

                </p>



                <p className="text-gray-300">

                    <span className="text-gray-500">

                        Description:

                    </span>{" "}

                    {log.description}

                </p>



                <p className="text-gray-400">

                    {new Date(

                        log.createdAt

                    ).toLocaleString()}

                </p>


            </div>


        </div>

    );

}