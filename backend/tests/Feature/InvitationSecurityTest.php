<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_is_email_bound_and_single_use(): void
    {
        $org = Organization::create(['name' => 'Invite Org', 'slug' => 'invite-org']);
        $owner = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'status' => 'active']);
        $invitee = User::factory()->create(['email' => 'invitee@example.com', 'organization_id' => null, 'role' => 'staff', 'status' => 'active']);
        $attacker = User::factory()->create(['email' => 'attacker@example.com', 'organization_id' => null, 'role' => 'staff', 'status' => 'active']);
        $created = $this->actingAs($owner)->postJson('/api/organization/invitations', ['email' => 'Invitee@Example.com', 'role' => 'viewer'])->assertCreated()->json();
        $this->assertArrayHasKey('token', $created);
        $this->assertDatabaseMissing('invitations', ['token_hash' => $created['token']]);
        $this->actingAs($attacker)->postJson('/api/invitations/accept', ['token' => $created['token']])->assertForbidden();
        $this->assertNull($attacker->refresh()->organization_id);
        $this->actingAs($invitee)->postJson('/api/invitations/accept', ['token' => $created['token']])->assertOk()->assertJsonPath('organizationId', (string) $org->id);
        $this->assertSame($org->id, $invitee->refresh()->organization_id);
        $this->actingAs($invitee)->postJson('/api/invitations/accept', ['token' => $created['token']])->assertNotFound();
    }

    public function test_expired_invalid_and_disabled_account_invites_fail_safely(): void
    {
        $org = Organization::create(['name' => 'Expiry Org', 'slug' => 'expiry-org']);
        $owner = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'status' => 'active']);
        $invitee = User::factory()->create(['email' => 'expired@example.com', 'organization_id' => null, 'role' => 'staff', 'status' => 'active']);
        $created = $this->actingAs($owner)->postJson('/api/organization/invitations', ['email' => $invitee->email, 'role' => 'viewer'])->assertCreated()->json();
        $org->invitations()->firstOrFail()->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($invitee)->postJson('/api/invitations/accept', ['token' => $created['token']])->assertStatus(410);
        $this->actingAs($invitee)->postJson('/api/invitations/accept', ['token' => 'invalid-token'])->assertNotFound();
        $fresh = $this->actingAs($owner)->postJson('/api/organization/invitations', ['email' => $invitee->email, 'role' => 'viewer'])->assertCreated()->json();
        $invitee->update(['status' => 'suspended']);
        $this->actingAs($invitee)->postJson('/api/invitations/accept', ['token' => $fresh['token']])->assertForbidden();
        $this->assertNull($invitee->refresh()->organization_id);
    }
}
