import { useState, type FormEvent } from "react";
import { Button, Card, CardContent, CardHeader, Input } from "../../ui";
import { updateProfile } from "../../../services/profile.service";
import { useAuth } from "../../../context/AuthContext";
import type { UserProfile } from "../../../types/profile";

export default function ProfileForm({ profile, onSaved }: { profile: UserProfile; onSaved(profile: UserProfile): void }) {
  const { refresh } = useAuth(); const [name,setName]=useState(profile.name); const [email,setEmail]=useState(profile.email); const [saving,setSaving]=useState(false); const [message,setMessage]=useState(""); const [error,setError]=useState("");
  async function submit(e:FormEvent){e.preventDefault();setMessage("");setError("");if(!name.trim()||!email.includes("@")){setError("Enter a valid name and email address.");return;}setSaving(true);try{const saved=await updateProfile({name:name.trim(),email:email.trim()});await refresh();onSaved(saved);setMessage("Profile updated successfully.");}catch{setError("We could not update your profile. Please try again.");}finally{setSaving(false);}}
  return <Card><CardHeader title="Personal information" description="Used for your account identity and service communications."/><CardContent><form onSubmit={submit} className="max-w-xl space-y-4"><Input label="Full name" value={name} onChange={e=>setName(e.target.value)} autoComplete="name"/><Input label="Email address" type="email" value={email} onChange={e=>setEmail(e.target.value)} autoComplete="email"/>{error&&<p role="alert" className="text-sm text-[var(--ds-danger)]">{error}</p>}{message&&<p role="status" className="text-sm text-[var(--ds-success)]">{message}</p>}<Button type="submit" loading={saving}>Save changes</Button></form></CardContent></Card>;
}
