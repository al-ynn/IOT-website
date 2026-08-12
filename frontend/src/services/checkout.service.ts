import api from "./api";


import type {

    CheckoutRequest,

    CheckoutSession

} from "../types/checkout";





export async function createCheckoutSession(

    data:CheckoutRequest

){

    const response =

        await api.post<CheckoutSession>(

            "/billing/checkout",

            data

        );


    return response.data;

}