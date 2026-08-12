import {

    useEffect,

    useState

}

from "react";


import {

    getAuditLogs

}

from "../../services/audit.service";


import type {

    AuditLog

}

from "../../types/audit";


import AuditLogTable

from "../../components/audit/AuditLogTable";


import AuditLogFilters

from "../../components/audit/AuditLogFilters";


import AuditLogDetails

from "../../components/audit/AuditLogDetails";





export default function AuditLogs(){



    const [

        logs,

        setLogs

    ] = useState<AuditLog[]>([]);



    const [

        selectedLog,

        setSelectedLog

    ] = useState<AuditLog|null>(null);



    const [

        action,

        setAction

    ] = useState("");



    const [

        resourceType,

        setResourceType

    ] = useState("");





    useEffect(()=>{



        getAuditLogs({

            action:action || undefined,

            resourceType:

                resourceType || undefined

        }).then(setLogs);



    },[action,resourceType]);





    return (

        <div className="space-y-6">


            <div>


                <h1

                    className="
                        text-3xl
                        font-bold
                        text-white
                    "

                >

                    Audit Logs

                </h1>



                <p className="mt-2 text-gray-400">

                    Review activity performed within your organization.

                </p>


            </div>



            <AuditLogFilters

                action={action}

                resourceType={resourceType}

                onActionChange={setAction}

                onResourceTypeChange={setResourceType}

            />



            <AuditLogTable

                logs={logs}

                onSelect={setSelectedLog}

            />



            <AuditLogDetails

                log={selectedLog}

                onClose={()=>

                    setSelectedLog(null)

                }

            />


        </div>

    );

}
