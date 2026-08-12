import api from "./api";


import type {AnalyticsAggregation,AnalyticsInterval,AnalyticsRange,AnalyticsSeries,AnalyticsSummary,DeviceAnalytics}

from "../types/analytics";





export async function getAnalyticsSummary(range:AnalyticsRange="24h",dates?:{from?:string;to?:string}){


const response =

await api.get<AnalyticsSummary>(

"/analytics/summary",{params:{range,...dates}}

);



return response.data;


}





export async function getDeviceMetrics(deviceId:string,range:AnalyticsRange="24h",dates?:{from?:string;to?:string}){


const response =

await api.get<DeviceAnalytics>(

`/analytics/devices/${deviceId}`,{params:{range,...dates}}

);



return response.data;


}





export async function getTelemetryHistory(metric:string,params:{deviceId:string;range:AnalyticsRange;interval:AnalyticsInterval;aggregation:AnalyticsAggregation;from?:string;to?:string}){


const response=await api.get<AnalyticsSeries>(`/analytics/telemetry/${encodeURIComponent(metric)}`,{params});



return response.data;


}
