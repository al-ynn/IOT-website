import {useEffect,useMemo,useState} from "react";
import {Button,Input,Modal,Select} from "../../ui";
import {createAdminUser,getAdminOrganizations} from "../../../services/admin.service";
import type {AdminOrganization,AdminUser} from "../../../types/admin";

export default function CreateAccountModal({open,onClose,onCreated}:{open:boolean;onClose():void;onCreated(user:AdminUser):Promise<void>}) {
  const [name,setName] = useState("");
  const [email,setEmail] = useState("");
  const [password,setPassword] = useState("");
  const [confirm,setConfirm] = useState("");
  const [role,setRole] = useState<"staff"|"admin">("staff");
  const [organizationId,setOrganizationId] = useState("");
  const [organizations,setOrganizations] = useState<AdminOrganization[]>([]);
  const [loadingOrgs,setLoadingOrgs] = useState(false);
  const [saving,setSaving] = useState(false);
  const [error,setError] = useState("");
  const [fieldErrors,setFieldErrors] = useState<Record<string,string>>({});

  useEffect(() => {
    if (!open) return;
    const loadOrganizations = async () => {
      setLoadingOrgs(true);
      try {
        const data = await getAdminOrganizations();
        setOrganizations(data);
        setOrganizationId(data[0]?.id ?? "");
      } catch {
        setError("Organizations could not be loaded.");
      } finally {
        setLoadingOrgs(false);
      }
    };
    void loadOrganizations();
  }, [open]);

  const selectedOrganization = useMemo(() => organizations.find(item => item.id === organizationId) ?? organizations[0] ?? null, [organizations, organizationId]);

  async function submit() {
    if (saving) return;
    const nextErrors: Record<string,string> = {};
    if (!name.trim()) nextErrors.name = "Enter a full name.";
    if (!email.trim()) nextErrors.email = "Enter an email address.";
    if (!password) nextErrors.password = "Enter a password.";
    if (!confirm) nextErrors.confirm = "Confirm the password.";
    if (password && confirm && password !== confirm) nextErrors.confirm = "Passwords do not match.";
    if (Object.keys(nextErrors).length) {
      setFieldErrors(nextErrors);
      return;
    }
    setSaving(true);
    setError("");
    setFieldErrors({});
    try {
      const created = await createAdminUser({
        name: name.trim(),
        email: email.trim(),
        password,
        password_confirmation: confirm,
        role,
        organization_id: organizationId || selectedOrganization?.id || undefined,
      });
      await onCreated(created);
      setPassword("");
      setConfirm("");
      onClose();
    } catch (cause: unknown) {
      const response = cause as {
        response?: {
          status?: number;
          data?: {
            message?: string;
            errors?: Record<string, string[]>;
          };
        };
      };
      const status = response?.response?.status;
      const data = response?.response?.data;
      if (status === 422 && data?.errors) {
        const errors = data.errors as Record<string,string[]>;
        setFieldErrors(Object.fromEntries(Object.entries(errors).map(([key,value]) => [key, value[0]])));
        setError(data.message || "Check the form fields and try again.");
      } else if (status === 403) {
        setError("You do not have permission to create accounts.");
      } else if (status === 429) {
        setError("Operation rate limit reached.");
      } else if (status === 401) {
        setError("Your session has expired.");
      } else {
        setError("Unable to create the account right now.");
      }
    } finally {
      setSaving(false);
    }
  }

  return <Modal
    open={open}
    onClose={onClose}
    title="Create new account"
    description="Provision a Staff or Admin account in the selected organization."
    footer={<>
      <Button variant="ghost" onClick={() => { setName(""); setEmail(""); setPassword(""); setConfirm(""); setRole("staff"); setOrganizationId(""); setError(""); setFieldErrors({}); onClose(); }} disabled={saving}>Cancel</Button>
      <Button loading={saving} onClick={submit}>{saving ? "Creating..." : "Create account"}</Button>
    </>}
  >
    <div className="grid gap-4">
      <Input label="Full name" value={name} onChange={e=>setName(e.target.value)} autoComplete="name" required error={fieldErrors.name}/>
      <Input label="Email" type="email" value={email} onChange={e=>setEmail(e.target.value)} autoComplete="email" required error={fieldErrors.email}/>
      <Input label="Password" type="password" value={password} onChange={e=>setPassword(e.target.value)} autoComplete="new-password" required error={fieldErrors.password}/>
      <Input label="Confirm password" type="password" value={confirm} onChange={e=>setConfirm(e.target.value)} autoComplete="new-password" required error={fieldErrors.confirm}/>
      <Select label="Role" value={role} onChange={e=>setRole(e.target.value as "staff"|"admin")}>
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
      </Select>
      <Select label="Organization" value={organizationId} onChange={e=>setOrganizationId(e.target.value)} disabled={loadingOrgs || organizations.length === 0}>
        {organizations.length ? organizations.map(org => <option key={org.id} value={org.id}>{org.name}</option>) : <option value="">{loadingOrgs ? "Loading organizations..." : "Use current organization"}</option>}
      </Select>
      {error && <p role="alert" className="text-xs text-[var(--ds-danger)]">{error}</p>}
      <p className="text-[11px] text-[var(--ds-text-subtle)]">Passwords are not stored locally. The backend returns sanitized account data only.</p>
    </div>
  </Modal>;
}
