import type { AutomationDefinition,AutomationTrigger } from "./automation";
import type { AutomationAction } from "./action";
import type { ConditionGroup } from "./condition";
export interface AutomationTemplate{id:string;name:string;description:string;category:string;trigger:AutomationTrigger;conditions:ConditionGroup;actions:AutomationAction[];definition:AutomationDefinition;createdAt:string;}
