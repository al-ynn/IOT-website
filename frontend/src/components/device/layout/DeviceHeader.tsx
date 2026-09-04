import {Settings} from "lucide-react";
import type {Device} from "../../../types/device";
import {BodyText,Button,PageTitle} from "../../ui";
import DeviceStatus from "../status/DeviceStatus";
import LifecycleBanner from "../../lifecycle/LifecycleBanner";

export default function DeviceHeader({device,canManage}:{device:Device;canManage:boolean;onDelete():void}){
  return <><header className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><div className="flex flex-wrap items-center gap-3"><PageTitle>{device.name}</PageTitle><DeviceStatus status={device.status}/></div><BodyText className="mt-1">{device.type} · {device.protocol}</BodyText></div>{canManage&&<Button variant="outline" disabled title="Device updates are not supported by the current API" leadingIcon={<Settings size={14}/>}>Settings</Button>}</header><LifecycleBanner type="device" id={device.id!}/></>
}
