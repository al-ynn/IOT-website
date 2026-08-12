import type {

    PaymentRecord

} from "../../types/billing";





interface Props {

    payments:PaymentRecord[];

}





export default function PaymentHistory({

    payments

}:Props){

    return (

        <div

            className="
                rounded-2xl
                border
                border-white/10
                bg-[#0B1628]
                p-6
            "

        >

            <h2

                className="
                    text-xl
                    font-semibold
                    text-white
                "

            >

                Payment History

            </h2>



            {payments.length === 0 ? (

                <p

                    className="
                        mt-5
                        text-gray-400
                    "

                >

                    No payment history yet.

                </p>

            ) : (

                <div

                    className="
                        mt-5
                        overflow-x-auto
                    "

                >

                    <table className="w-full text-left">

                        <thead>

                            <tr

                                className="
                                    border-b
                                    border-white/10
                                "

                            >

                                <th

                                    className="
                                        p-3
                                        text-sm
                                        text-gray-400
                                    "

                                >

                                    Date

                                </th>


                                <th

                                    className="
                                        p-3
                                        text-sm
                                        text-gray-400
                                    "

                                >

                                    Amount

                                </th>


                                <th

                                    className="
                                        p-3
                                        text-sm
                                        text-gray-400
                                    "

                                >

                                    Status

                                </th>


                                <th

                                    className="
                                        p-3
                                        text-sm
                                        text-gray-400
                                    "

                                >

                                    Transaction

                                </th>

                            </tr>

                        </thead>



                        <tbody>

                            {payments.map(payment => (

                                <tr

                                    key={payment.id}

                                    className="
                                        border-b
                                        border-white/5
                                    "

                                >

                                    <td

                                        className="
                                            p-3
                                            text-gray-300
                                        "

                                    >

                                        {new Date(

                                            payment.createdAt

                                        ).toLocaleDateString()}

                                    </td>



                                    <td

                                        className="
                                            p-3
                                            text-white
                                        "

                                    >

                                        {payment.amount}{" "}

                                        {payment.currency}

                                    </td>



                                    <td

                                        className="
                                            p-3
                                            capitalize
                                            text-gray-300
                                        "

                                    >

                                        {payment.status}

                                    </td>



                                    <td

                                        className="
                                            max-w-[180px]
                                            truncate
                                            p-3
                                            text-gray-400
                                        "

                                    >

                                        {payment.transactionId || "—"}

                                    </td>

                                </tr>

                            ))}

                        </tbody>

                    </table>

                </div>

            )}

        </div>

    );

}