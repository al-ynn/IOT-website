import {useEffect,useMemo,useState} from "react";
import {Button,Input,Modal,Select} from "../../ui";
import type {AdminDeviceOption,AdminStaffOption,DeviceAccessAssignment,DeviceAccessLevel} from "../../../types/admin";
import {createDeviceAccessAssignment,getAdminDeviceOptions,getAdminStaffOptions,updateDeviceAccessAssignment} from "../../../services/admin.service";

type Props = {
  open: boolean;
  onClose(): void;
  onSaved(assignment: DeviceAccessAssignment): Promise<void>;
  assignment?: DeviceAccessAssignment | null;
  fixedDevice?:{id:string;name:string;organizationId:string};
  fixedUser?:{id:string;name:string;organizationId:string};
};

export default function DeviceAccessModal({open,onClose,onSaved,assignment,fixedDevice,fixedUser}:Props){
  const editing = !!assignment;
  const [staffId,setStaffId] = useState("");
  const [deviceId,setDeviceId] = useState("");
  const [accessLevel,setAccessLevel] = useState<DeviceAccessLevel>("viewer");
  const [devices,setDevices] = useState<AdminDeviceOption[]>([]);
  const [users,setUsers] = useState<AdminStaffOption[]>([]);
  const [loadingDevices,setLoadingDevices] = useState(false);
  const [saving,setSaving] = useState(false);
  const [error,setError] = useState("");
  const [fieldErrors,setFieldErrors] = useState<Record<string,string>>({});
  const [optionSearch,setOptionSearch]=useState("");

  useEffect(() => {
    if (!open) return;
    const timer = window.setTimeout(() => {
      setError("");
      setFieldErrors({});
      setSaving(false);
      setLoadingDevices(true);
      Promise.all([getAdminDeviceOptions({organization_id:fixedUser?.organizationId,user_id:fixedUser?.id,search:optionSearch||undefined}),getAdminStaffOptions({organization_id:fixedDevice?.organizationId,device_id:fixedDevice?.id,search:optionSearch||undefined})]).then(([nextDevices,nextUsers])=>{setDevices(nextDevices);setUsers(nextUsers);}).catch(() => setError("Assignment options could not be loaded.")).finally(() => setLoadingDevices(false));
    }, 250);
    return () => window.clearTimeout(timer);
  }, [open,fixedDevice?.id,fixedDevice?.organizationId,fixedUser?.id,fixedUser?.organizationId,optionSearch]);

  useEffect(() => {
    if (!open) return;
    const timer = window.setTimeout(() => {
      if (assignment) {
        setStaffId(assignment.user.id);
        setDeviceId(assignment.device.id);
        setAccessLevel(assignment.accessLevel);
        return;
      }
      setStaffId(fixedUser?.id??"");
      setDeviceId(fixedDevice?.id??"");
      setAccessLevel("viewer");
    }, 0);
    return () => window.clearTimeout(timer);
  }, [assignment,open,fixedDevice?.id,fixedUser?.id]);

  const staffOptions = useMemo(() => users, [users]);

  async function submit() {
    if (saving) return;
    if (editing && assignment && accessLevel !== assignment.accessLevel) {
      const message = accessLevel === "viewer"
        ? "Downgrade to Viewer? Staff will retain read access but lose protected edit and control actions."
        : "Upgrade to Full Access? This grants Device mutation rights but does not make Staff an Admin.";
      if (!window.confirm(message)) return;
    }
    setSaving(true);
    setError("");
    setFieldErrors({});
    try {
      const saved = editing && assignment
        ? await updateDeviceAccessAssignment(assignment.id, {access_level: accessLevel})
        : await createDeviceAccessAssignment({device_id: deviceId, user_id: staffId, access_level: accessLevel});
      await onSaved(saved);
      onClose();
    } catch (cause: unknown) {
      const response = cause as {response?: {status?: number; data?: {message?: string; errors?: Record<string, string[]>}}};
      const status = response.response?.status;
      const data = response.response?.data;
      if (status === 422 && data?.errors) {
        const next: Record<string,string> = {};
        Object.entries(data.errors).forEach(([key, value]) => { next[key] = value[0]; });
        setFieldErrors(next);
        setError(data.message || "Check the form fields and try again.");
      } else if (status === 403) {
        setError("You do not have permission to manage device access.");
      } else if (status === 401) {
        setError("Your session has expired.");
      } else {
        setError("Unable to save device access right now.");
      }
    } finally {
      setSaving(false);
    }
  }

  return <Modal
    open={open}
    onClose={onClose}
    title={editing ? "Change access" : fixedDevice ? "Assign Staff" : "Assign Device"}
    description={editing ? "Upgrade or downgrade this canonical Staff Device assignment." : "Grant Viewer or Full Access to a Staff member."}
    footer={<>
      <Button variant="ghost" onClick={onClose} disabled={saving}>Cancel</Button>
      <Button loading={saving} onClick={submit}>{editing ? (accessLevel==="full_access"?"Upgrade Access":"Downgrade Access") : "Grant Access"}</Button>
    </>}
  >
    <div className="grid gap-4">
      {!editing&&<Input label="Search eligible options" placeholder={fixedDevice?"Search Staff":"Search Devices"} value={optionSearch} onChange={e=>setOptionSearch(e.target.value)}/>} 
      {!editing && !fixedUser && <Select label="Staff" value={staffId} onChange={e => setStaffId(e.target.value)} error={fieldErrors.user_id}>
        <option value="">Select staff member</option>
        {staffOptions.map(user => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}
      </Select>}
      {!editing && !fixedDevice && <Select label="Device" value={deviceId} onChange={e => setDeviceId(e.target.value)} error={fieldErrors.device_id} disabled={loadingDevices || devices.length === 0}>
        <option value="">{loadingDevices ? "Loading devices..." : "Select device"}</option>
        {devices.map(device => <option key={device.id} value={device.id}>{device.name} · {device.organizationName}</option>)}
      </Select>}
      <Select label="Access Level" value={accessLevel} onChange={e => setAccessLevel(e.target.value as DeviceAccessLevel)} required>
        <option value="viewer">Viewer</option>
        <option value="full_access">Full Access</option>
      </Select>
      {error && <p role="alert" className="text-xs text-[var(--ds-danger)]">{error}</p>}
      <p className="text-[11px] text-[var(--ds-text-subtle)]">Organization validation is enforced by the server.</p>
    </div>
  </Modal>;
}
