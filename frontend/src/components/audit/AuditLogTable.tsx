import type {

    AuditLog

}

from "../../types/audit";





interface Props {

    logs:AuditLog[];

    onSelect:(log:AuditLog)=>void;

}





export default function AuditLogTable({

    logs,

    onSelect

}:Props){



    return (

        <div

            className="
                overflow-x-auto
                rounded-xl
                border
                border-white/10
                bg-[#0B1628]
            "

        >


            <table className="w-full text-left">


                <thead>

                    <tr className="border-b border-white/10">


                        <th className="p-4 text-sm text-gray-400">

                            User

                        </th>


                        <th className="p-4 text-sm text-gray-400">

                            Action

                        </th>


                        <th className="p-4 text-sm text-gray-400">

                            Resource

                        </th>


                        <th className="p-4 text-sm text-gray-400">

                            Date

                        </th>


                    </tr>

                </thead>



                <tbody>


                    {logs.map(log=>(


                        <tr

                            key={log.id}

                            onClick={()=>onSelect(log)}

                            className="
                                cursor-pointer
                                border-b
                                border-white/5
                                hover:bg-white/5
                            "

                        >


                            <td className="p-4 text-white">

                                {log.userName}

                            </td>



                            <td className="p-4 text-gray-300">

                                {log.action}

                            </td>



                            <td className="p-4 text-gray-300">

                                {log.resourceName ||

                                 log.resourceType}

                            </td>



                            <td className="p-4 text-gray-400">

                                {new Date(

                                    log.createdAt

                                ).toLocaleString()}

                            </td>


                        </tr>


                    ))}


                </tbody>


            </table>


        </div>

    );

}