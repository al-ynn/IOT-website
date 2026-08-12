<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $user = User::where('email', $credentials['email'])->first();
        abort_unless($user && Hash::check($credentials['password'], $user->password), 422, 'Invalid credentials.');
        abort_if($user->status==='suspended'||$user->organization?->status==='suspended',403,'Account is suspended.');
        return $this->session($user);
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name'=>'required|string|max:255','email'=>'required|email|unique:users','password'=>'required|string|min:8']);
        [$organization,$user]=DB::transaction(function() use($data){
            $plan=Plan::where('is_default',true)->where('active',true)->firstOrFail();
            $organization=Organization::create(['name'=>$data['name']."'s Organization",'slug'=>Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
            $user=User::create([...$data,'organization_id'=>$organization->id,'role'=>'owner']);
            $organization->subscriptions()->create(['plan_id'=>$plan->id,'status'=>'active','current_period_start'=>now()]);
            return [$organization,$user];
        });
        return response()->json($this->session($user), 201);
    }

    public function me(Request $request) { return $this->user($request->user()); }
    public function logout(Request $request) { $request->user()->currentAccessToken()?->delete(); return response()->noContent(); }

    private function session(User $user): array { return ['token'=>$user->createToken('web')->plainTextToken,'user'=>$this->user($user)]; }
    private function user(User $user): array { return ['id'=>(string)$user->id,'name'=>$user->name,'email'=>$user->email,'organizationId'=>$user->organization_id ? (string)$user->organization_id : null,'role'=>$user->role,'platformRole'=>$user->platform_role,'createdAt'=>$user->created_at?->toISOString()]; }
}
