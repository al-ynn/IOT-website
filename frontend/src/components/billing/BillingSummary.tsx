import type { BillingEntitlements } from "../../types/billing";


export default function BillingSummary({

entitlements

}:{

entitlements:BillingEntitlements;

}){


return (

<div className="rounded-2xl border border-white/10 bg-[#0B1628] p-6">

<h2 className="text-xl font-semibold text-white">

Plan Limits

</h2>

<div className="mt-5 grid gap-4 sm:grid-cols-3">

<p className="text-gray-300">Devices: {entitlements.deviceLimit}</p>

<p className="text-gray-300">Users: {entitlements.userLimit}</p>

<p className="text-gray-300">Dashboards: {entitlements.dashboardLimit}</p>

</div>

</div>

);


}
