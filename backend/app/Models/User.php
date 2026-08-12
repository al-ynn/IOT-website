<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'organization_id', 'role', 'platform_role', 'notification_settings', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platform_role === 'platform_admin';
    }

    public function hasOrganizationPermission(string $permission): bool
    {
        $map=['owner'=>['*'],'admin'=>['device.view','device.manage','dashboard.view','dashboard.manage','billing.view','billing.manage','organization.manage','members.manage','audit.view','automation.view','automation.create','automation.update','automation.delete','automation.execute','automation.manage','analytics.view'],'engineer'=>['device.view','device.manage','dashboard.view','dashboard.manage','automation.view','automation.create','automation.update','automation.delete','automation.execute','automation.manage','analytics.view'],'operator'=>['device.view','dashboard.view','automation.view','automation.execute','analytics.view'],'viewer'=>['device.view','dashboard.view','automation.view','analytics.view']];
        $granted=$map[$this->role]??[];
        return in_array('*',$granted,true)||in_array($permission,$granted,true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_settings' => 'array',
        ];
    }
}
