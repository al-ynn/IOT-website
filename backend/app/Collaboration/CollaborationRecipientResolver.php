<?php

namespace App\Collaboration;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CollaborationRecipientResolver
{
    public function resolve(User $actor, int|string $recipientId): User
    {
        $recipient = User::query()->whereKey($recipientId)->where('status', 'active')->first();

        if (! $recipient || $recipient->isPlatformAdmin() || ! $actor->organization_id || $recipient->organization_id !== $actor->organization_id) {
            throw ValidationException::withMessages(['recipient_id' => ['Recipient is not eligible for this collaboration.']]);
        }

        return $recipient;
    }
}
