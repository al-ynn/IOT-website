export interface CheckoutRequest {

    planId:string;

}


export interface CheckoutSession {

    id:string;

    checkoutUrl:string;

    planId:string;

}