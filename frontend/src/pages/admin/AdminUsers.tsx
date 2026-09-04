import {useEffect,useMemo,useState} from "react";
import {Plus} from "lucide-react";
import AdminPageHeader from "../../components/admin/layout/AdminPageHeader";
import UserTable from "../../components/admin/users/UserTable";
import UserDetails from "../../components/admin/users/UserDetails";
import CreateAccountModal from "../../components/admin/users/CreateAccountModal";
import {Button,Card,CardContent,ErrorState,Input,LoadingState,Select} from "../../components/ui";
import {getAdminUsers,updateUserStatus} from "../../services/admin.service";
import type {AdminUser} from "../../types/admin";
import {useAuth} from "../../context/AuthContext";

export default function AdminUsers() {
  const {user:current}=useAuth();
  const [users,setUsers]=useState<AdminUser[]>([]);
  const [selected,setSelected]=useState<AdminUser|null>(null);
  const [loading,setLoading]=useState(true);
  const [error,setError]=useState("");
  const [query,setQuery]=useState("");
  const [status,setStatus]=useState("all");
  const [busy,setBusy]=useState("");
  const [open,setOpen]=useState(false);

  async function load() {
    setLoading(true);
    setError("");
    try {
      setUsers(await getAdminUsers());
    } catch {
      setError("Platform users could not be loaded.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, []);

  const filtered = useMemo(() => users.filter(item => {
    const matchesStatus = status === "all" || item.status === status;
    const matchesQuery = `${item.name} ${item.email} ${item.organizationName} ${item.role}`.toLowerCase().includes(query.toLowerCase());
    return matchesStatus && matchesQuery;
  }), [users, query, status]);

  async function toggle(item: AdminUser) {
    if (!window.confirm(`${item.status === "active" ? "Suspend" : "Activate"} ${item.email}?`)) return;
    setBusy(item.id);
    setError("");
    try {
      await updateUserStatus(item.id, item.status === "active" ? "suspended" : "active");
      await load();
    } catch {
      setError("User status could not be updated.");
    } finally {
      setBusy("");
    }
  }

  async function handleCreated(user: AdminUser) {
    await load();
    setSelected(user);
  }

  return <div className="space-y-5">
    <AdminPageHeader
      title="Users"
      description="Inspect, search, activate, and suspend platform accounts."
      action={<Button leadingIcon={<Plus size={15}/>} onClick={()=>setOpen(true)}>Create new account</Button>}
    />
    <Card>
      <CardContent className="grid gap-3 sm:grid-cols-[1fr_180px]">
        <Input aria-label="Search users" placeholder="Search name, email, organization" value={query} onChange={e=>setQuery(e.target.value)}/>
        <Select aria-label="Filter user status" value={status} onChange={e=>setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </Select>
      </CardContent>
    </Card>
    {error&&<Card><ErrorState description={error} retry={()=>void load()}/></Card>}
    {loading?<Card><LoadingState label="Loading users..."/></Card>:<Card><UserTable users={filtered} currentUserId={current?.id} onStatusChange={toggle} busyId={busy}/></Card>}
    <UserDetails user={selected}/>
    <CreateAccountModal open={open} onClose={()=>setOpen(false)} onCreated={handleCreated}/>
    <p className="text-xs text-[var(--ds-text-subtle)]">Search and filtering operate on the complete list returned by Laravel. The backend does not expose admin pagination.</p>
  </div>;
}
