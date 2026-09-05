export type SystemSettingType="integer";
export interface SystemSettingDefinition{key:string;category:string;type:SystemSettingType;value:number;default:number;min:number;max:number;unit:string;description:string;is_overridden:boolean;updated_at?:string|null}
export interface SystemSettingCategory{name:string;settings:SystemSettingDefinition[]}
