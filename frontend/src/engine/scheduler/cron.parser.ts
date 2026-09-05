import type {

AutomationSchedule

}

from "../../types/schedule";





export function createCronExpression(

schedule:AutomationSchedule

){



switch(schedule.type){



case "daily":


return `0 ${schedule.time?.split(":")[1]} ${schedule.time?.split(":")[0]} * * *`;





case "weekly":


return `0 0 8 * *`;





case "interval":


return `*/${schedule.intervalMinutes} * * * *`;





default:


return "";



}



}