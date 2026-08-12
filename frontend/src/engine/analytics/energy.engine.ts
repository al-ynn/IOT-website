import type {

EnergyReading

}

from "../../types/energy";







export function calculateConsumption(

readings:EnergyReading[]

){



if(readings.length < 2){

return 0;

}





let total = 0;





for(let i = 1; i < readings.length; i++){



const previous =

new Date(

readings[i-1].timestamp

).getTime();



const current =

new Date(

readings[i].timestamp

).getTime();





const hours =

(

current - previous

)

/

1000

/

60

/

60;





total +=

readings[i].power * hours;


}





return Number(

total.toFixed(2)

);


}







export function calculateEnergyCost(

consumption:number,

rate:number

){



return Number(

(

consumption *

rate

)

.toFixed(2)

);


}





export function findPeakUsage(

readings:EnergyReading[]

){



if(readings.length===0){

return null;

}





return readings.reduce(

(max,current)=>

current.power > max.power

?

current

:

max

);


}