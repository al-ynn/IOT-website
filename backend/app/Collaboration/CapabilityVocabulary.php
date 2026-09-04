<?php

namespace App\Collaboration;

use Illuminate\Validation\ValidationException;

final class CapabilityVocabulary
{
    public const KEYS = [
        'canViewPrivateWorkspace', 'canEdit', 'canComment', 'canReply',
        'canResolveComment', 'canViewRevisionHistory', 'canCompareRevision',
        'canCreateDraft', 'canApplyDraft', 'canReviewChanges', 'canPullUpdate',
        'canIgnoreUpdate', 'canRequestShare', 'canManageCollaborators',
        'canDirectGrant', 'canRevoke', 'canSubmitReview', 'canApproveReview',
        'canPublish', 'canViewPublished', 'canDisable', 'canRestore',
        'canReadSecret', 'canWriteSecret', 'canRotateCredential', 'canReconnect',
        'canActivate', 'canExecute', 'canDeploy',
    ];

    public function assert(string $key): void
    {
        if (! in_array($key, self::KEYS, true)) {
            throw ValidationException::withMessages(['capability' => ['Unsupported capability key.']]);
        }
    }

    /** @return array<string, bool> */
    public function denied(): array
    {
        return array_fill_keys(self::KEYS, false);
    }
}
