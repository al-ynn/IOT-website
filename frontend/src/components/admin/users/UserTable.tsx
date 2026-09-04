import {Badge,Button,EmptyState} from "../../ui";
import type {AdminUser} from "../../../types/admin";
import {Link} from "react-router-dom";

export default function UserTable({users,currentUserId,onStatusChange,busyId}:{users:AdminUser[];currentUserId?:string;onStatusChange(user:AdminUser):void;busyId?:string}) {
  if (!users.length) return <EmptyState title="No users found" description="No platform users match the selected filters."/>;

  return <>
    <div className="hidden overflow-x-auto md:block">
      <table className="w-full min-w-[800px] text-left text-sm">
        <caption className="sr-only">Platform users</caption>
        <thead className="border-b border-[var(--ds-border)] text-xs text-[var(--ds-text-muted)]">
          <tr>
            <th className="p-3">User</th>
            <th className="p-3">Organization</th>
            <th className="p-3">Role</th>
            <th className="p-3">Status</th>
            <th className="p-3">Created</th>
            <th className="p-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          {users.map(user => <tr key={user.id} className="border-b border-[var(--ds-border-subtle)] last:border-0">
            <td className="p-3"><Link className="text-left font-medium text-[var(--ds-text)] hover:text-[var(--ds-primary)]" to={`/admin/users/${user.id}`}>{user.name}<span className="block text-xs font-normal text-[var(--ds-text-muted)]">{user.email}</span></Link></td>
            <td className="p-3">{user.organizationName||"—"}</td>
            <td className="p-3">{user.role === "admin" ? "Admin" : "Staff"}</td>
            <td className="p-3"><Badge tone={user.status==="active"?"success":"warning"}>{user.status}</Badge></td>
            <td className="p-3">{new Date(user.createdAt).toLocaleDateString()}</td>
            <td className="p-3 text-right"><Button size="compact" variant={user.status==="active"?"danger":"outline"} disabled={user.id===currentUserId} loading={busyId===user.id} onClick={()=>onStatusChange(user)}>{user.status==="active"?"Suspend":"Activate"}</Button></td>
          </tr>)}
        </tbody>
      </table>
    </div>
    <div className="space-y-3 md:hidden">
      {users.map(user => <article key={user.id} className="rounded-lg border border-[var(--ds-border-subtle)] p-4">
        <Link className="font-medium text-[var(--ds-text)]" to={`/admin/users/${user.id}`}>{user.name}</Link>
        <p className="text-xs text-[var(--ds-text-muted)]">{user.email}</p>
        <div className="mt-3 flex items-center justify-between gap-2">
          <Badge tone={user.status==="active"?"success":"warning"}>{user.status}</Badge>
          <span className="text-xs font-medium">{user.role === "admin" ? "Admin" : "Staff"}</span>
          <Button size="compact" variant={user.status==="active"?"danger":"outline"} disabled={user.id===currentUserId} loading={busyId===user.id} onClick={()=>onStatusChange(user)}>{user.status==="active"?"Suspend":"Activate"}</Button>
        </div>
      </article>)}
    </div>
  </>;
}
