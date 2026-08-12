import api from "./api";


import type {

    UserProfile,

    PasswordUpdate,

    NotificationSettings

}

from "../types/profile";





export async function getProfile(){



    const response =

        await api.get<UserProfile>(

            "/profile"

        );



    return response.data;

}





export async function updateProfile(

    data:Partial<UserProfile>

){



    const response =

        await api.put<UserProfile>(

            "/profile",

            data

        );



    return response.data;

}





export async function updatePassword(

    data:PasswordUpdate

){



    const response =

        await api.put(

            "/profile/password",

            data

        );



    return response.data;

}





export async function getNotificationSettings(){



    const response =

        await api.get<NotificationSettings>(

            "/profile/notifications"

        );



    return response.data;

}





export async function updateNotificationSettings(

    data:NotificationSettings

){



    const response =

        await api.put<NotificationSettings>(

            "/profile/notifications",

            data

        );



    return response.data;

}