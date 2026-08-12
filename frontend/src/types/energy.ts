export interface EnergyReading {


    deviceId:string;


    power:number;


    voltage?:number;


    current?:number;


    timestamp:string;


}





export interface EnergySummary {


    deviceId:string;


    totalConsumption:number;


    averagePower:number;


    peakPower:number;


    estimatedCost:number;


}





export interface EnergyTrend {


    period:string;


    consumption:number;


}