export type ConditionOperator =


    | ">"


    | "<"


    | "="


    | ">="


    | "<="
    | "!="
    | "contains"
    | "starts_with"
    | "ends_with"
    | "exists";



export type ConditionLogic =


    | "AND"


    | "OR";





export interface RuleCondition {


    field:string;


    operator:ConditionOperator;


    value:string | number | boolean | null;


}





export interface ConditionGroup {


    logic:ConditionLogic;


    conditions:RuleCondition[];


}
