export type WidgetCategory =

    | "monitoring"

    | "industrial"

    | "location"

    | "ai"

    | "digital-twin";





export interface WidgetDefinition {


    type:string;


    name:string;


    description:string;


    category:WidgetCategory;


    icon:string;


}