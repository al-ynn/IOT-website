<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminUserRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function organizations(Request $request)
    {
        return Organization::whereKey($request->user()->organization_id)->with(['users' => fn ($q) => $q->where('role', 'owner')])->withCount(['users', 'devices'])->get()->map(fn ($o) => [
            'id' => (string) $o->id,
            'name' => $o->name,
            'slug' => $o->slug,
            'ownerName' => $o->users->first()?->name ?? '',
            'ownerEmail' => $o->users->first()?->email ?? '',
            'memberCount' => $o->users_count,
            'deviceCount' => $o->devices_count,
            'status' => $o->status,
            'createdAt' => $o->created_at->toISOString(),
        ]);
    }

    public function organizationStatus(Request $r, string $organization)
    {
        $d = $r->validate(['status' => 'required|in:active,suspended']);
        $o = Organization::whereKey($r->user()->organization_id)->findOrFail($organization);
        $o->update($d);

        return response()->json(['status' => $o->status]);
    }

    public function users(Request $request)
    {
        return User::where('organization_id', $request->user()->organization_id)->with('organization')->get()->map(fn ($u) => [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'organizationId' => $u->organization_id ? (string) $u->organization_id : null,
            'organizationName' => $u->organization?->name ?? '',
            'role' => $u->isPlatformAdmin() ? 'admin' : 'staff',
            'organizationRole' => $u->role,
            'status' => $u->status,
            'createdAt' => $u->created_at->toISOString(),
            'lastLoginAt' => $u->updated_at?->toISOString(),
        ]);
    }

    public function store(CreateAdminUserRequest $request)
    {
        $data = $request->validated();
        $organizationId = $request->user()->organization_id;

        abort_if(! $organizationId, 422, 'An organization is required for account creation.');

        $organization = Organization::whereKey($request->user()->organization_id)->findOrFail($organizationId);

        $user = DB::transaction(function () use ($data, $organization) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'organization_id' => $organization->id,
                'role' => $data['role'] === 'admin' ? 'staff' : 'staff',
                'platform_role' => $data['role'] === 'admin' ? 'platform_admin' : null,
                'status' => 'active',
            ]);
        });

        return response()->json([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'organizationId' => (string) $user->organization_id,
            'organizationName' => $organization->name,
            'role' => $user->isPlatformAdmin() ? 'admin' : 'staff',
            'status' => $user->status,
            'createdAt' => $user->created_at->toISOString(),
        ], 201);
    }

    public function showUser(Request $request, User $user)
    {
        abort_unless((string) $user->organization_id === (string) $request->user()->organization_id, 404);
        $user->load('organization')->loadCount('deviceAccessAssignments');
        return ['id'=>(string)$user->id,'name'=>$user->name,'email'=>$user->email,'organizationId'=>$user->organization_id?(string)$user->organization_id:null,'organizationName'=>$user->organization?->name??'','role'=>$user->isPlatformAdmin()?'admin':'staff','status'=>$user->status,'assignedDevicesCount'=>$user->device_access_assignments_count,'createdAt'=>$user->created_at->toISOString()];
    }

    public function userStatus(Request $r, string $user)
    {
        $d = $r->validate(['status' => 'required|in:active,suspended']);
        $u = User::where('organization_id', $r->user()->organization_id)->findOrFail($user);
        abort_if($u->id === $r->user()->id && $d['status'] === 'suspended', 422, 'Cannot suspend your own account.');
        $u->update($d);
        if ($u->status === 'suspended') {
            $u->tokens()->delete();
        }

        return response()->json(['status' => $u->status]);
    }
}
