export function calculateHealthScore(

data:{

uptime:number;

errorRate:number;

responseTime:number;

}

){



let score = 100;





if(data.uptime < 95){


score -= 20;


}





if(data.errorRate > 5){


score -= 20;


}





if(data.responseTime > 500){


score -= 15;


}





return Math.max(

score,

0

);


}





export function calculateUptime(

online:number,

total:number

){



if(total===0){

return 0;

}





return (

online / total

)

*

100;


}
