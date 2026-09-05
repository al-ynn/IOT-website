import type {

TelemetryPoint,

TelemetryAggregate

}

from "../../types/telemetry";







export function aggregateTelemetry(

data:TelemetryPoint[]

):TelemetryAggregate | null{



if(data.length===0){

return null;

}





const values =

data.map(

item=>item.value

);





const total =

values.reduce(

(sum,value)=>

sum + value,

0

);





return {



deviceId:

data[0].deviceId,



metric:

data[0].metric,



period:

"custom",



average:

total / values.length,



minimum:

Math.min(...values),



maximum:

Math.max(...values),



count:

values.length


};


}

export function groupByHour(

data:TelemetryPoint[]

){



const groups:

Record<string,TelemetryPoint[]> = {};





data.forEach(point=>{


const hour =

new Date(point.timestamp)

.toISOString()

.slice(0,13);





if(!groups[hour]){


groups[hour]=[];

}





groups[hour].push(point);


});





return groups;


}