interface Props {

    action:string;

    resourceType:string;

    onActionChange:(value:string)=>void;

    onResourceTypeChange:(value:string)=>void;

}





export default function AuditLogFilters({

    action,

    resourceType,

    onActionChange,

    onResourceTypeChange

}:Props){



    return (

        <div

            className="
                grid
                gap-4
                md:grid-cols-2
            "

        >


            <select

                value={action}

                onChange={(e)=>

                    onActionChange(

                        e.target.value

                    )

                }

                className="
                    rounded-lg
                    bg-[#101F36]
                    p-3
                    text-white
                "

            >


                <option value="">

                    All Actions

                </option>


                <option value="created">

                    Created

                </option>


                <option value="updated">

                    Updated

                </option>


                <option value="deleted">

                    Deleted

                </option>


                <option value="executed">

                    Executed

                </option>


                <option value="login">

                    Login

                </option>


                <option value="logout">

                    Logout

                </option>


            </select>



            <select

                value={resourceType}

                onChange={(e)=>

                    onResourceTypeChange(

                        e.target.value

                    )

                }

                className="
                    rounded-lg
                    bg-[#101F36]
                    p-3
                    text-white
                "

            >


                <option value="">

                    All Resources

                </option>


                <option value="device">

                    Devices

                </option>


                <option value="automation">

                    Automations

                </option>


                <option value="user">

                    Users

                </option>


                <option value="organization">

                    Organization

                </option>


                <option value="role">

                    Roles

                </option>


            </select>


        </div>

    );

}