export type AnalyticsRange="1h"|"6h"|"12h"|"24h"|"7d"|"30d"|"custom";
export type AnalyticsInterval="minute"|"hour"|"day";
export type AnalyticsAggregation="average"|"minimum"|"maximum"|"sum"|"count";

export interface AnalyticsMetricSummary {metric:string;unit:string|null;count:number;average:number;minimum:number;maximum:number;latest:number|null;latestTimestamp:string|null;}
export interface AnalyticsSummary {totalDevices:number;onlineDevices:number;offlineDevices:number;telemetryRecords:number;metrics:AnalyticsMetricSummary[];from:string;to:string;}
export interface AnalyticsDevice {id:string;name:string;status:string;}
export interface DeviceAnalytics {device:AnalyticsDevice;telemetryRecords:number;metrics:AnalyticsMetricSummary[];from:string;to:string;}
export interface AnalyticsPoint {timestamp:string;value:number;}
export interface AnalyticsStatistics {count:number;average:number|null;minimum:number|null;maximum:number|null;sum:number|null;latest:number|null;latestTimestamp:string|null;}
export interface AnalyticsSeries {deviceId:string;metric:string;unit:string|null;interval:AnalyticsInterval;aggregation:AnalyticsAggregation;from:string;to:string;statistics:AnalyticsStatistics;points:AnalyticsPoint[];}
