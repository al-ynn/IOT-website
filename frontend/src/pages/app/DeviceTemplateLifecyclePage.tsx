import {useParams} from "react-router-dom";
import LifecycleBanner from "../../components/lifecycle/LifecycleBanner";
import DeviceTemplateDetail from "./DeviceTemplateDetail";

export default function DeviceTemplateLifecyclePage(){const{id=""}=useParams();return <div className="space-y-5"><LifecycleBanner type="device_template" id={id}/><DeviceTemplateDetail/></div>}
