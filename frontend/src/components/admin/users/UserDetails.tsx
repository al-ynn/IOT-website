import {Card,CardContent,CardHeader} from "../../ui";
import type {AdminUser} from "../../../types/admin";

export default function UserDetails({user}:{user:AdminUser|null}) {
  return <Card>
    <CardHeader title="User details" description="Select a user to inspect backend-provided account data."/>
    <CardContent>
      {user ? <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt className="text-xs text-[var(--ds-text-muted)]">Name</dt><dd>{user.name}</dd></div>
        <div><dt className="text-xs text-[var(--ds-text-muted)]">Email</dt><dd className="break-all">{user.email}</dd></div>
        <div><dt className="text-xs text-[var(--ds-text-muted)]">Organization</dt><dd>{user.organizationName||"None"}</dd></div>
        <div><dt className="text-xs text-[var(--ds-text-muted)]">Role</dt><dd>{user.role === "admin" ? "Admin" : "Staff"}</dd></div>
        <div><dt className="text-xs text-[var(--ds-text-muted)]">Created</dt><dd>{new Date(user.createdAt).toLocaleString()}</dd></div>
        <div><dt className="text-xs text-[var(--ds-text-muted)]">Last login</dt><dd>{user.lastLoginAt?new Date(user.lastLoginAt).toLocaleString():"Unavailable"}</dd></div>
      </dl> : <p className="text-sm text-[var(--ds-text-muted)]">No user selected.</p>}
    </CardContent>
  </Card>;
}
