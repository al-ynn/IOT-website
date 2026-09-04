<?php

namespace App\Collaboration;

use App\Models\ResourceLifecycleState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Revisions\ResourceUpdatePolicyRegistry;

/**
 * Read-only presentation projection. Canonical authorizers and workflow services
 * remain the enforcement authority for every action.
 */
final class ResourceCapabilityRegistry
{
    private const REVIEW_ENABLED = ['device_template', 'automation', 'firmware', 'webhook'];
    private const PUBLICATION_ENABLED = ['device_template', 'dashboard', 'automation', 'report'];

    public function __construct(
        private CollaborationResourceRegistry $resources,
        private CapabilityVocabulary $vocabulary,
        private ResourceUpdatePolicyRegistry $updatePolicies,
    ) {}

    /** @return array<string, bool> */
    public function for(User $user, string $type, Model $resource): array
    {
        $definition = $this->resources->definition($type);
        if (! $resource instanceof $definition->modelClass) {
            return $this->vocabulary->denied();
        }

        $authorizer = $definition->authorizer;
        $active = (ResourceLifecycleState::query()->where([
            'resource_type' => $type,
            'resource_id' => $resource->getKey(),
        ])->value('state') ?? 'active') === 'active';
        $view = $authorizer->canView($user, $resource);
        if (! $view) return $this->vocabulary->denied();

        $edit = $active && $authorizer->canEdit($user, $resource);
        $share = $active && $authorizer->canShare($user, $resource);
        $comment = $active && $authorizer->canComment($user, $resource);
        $admin = $authorizer->canAdminister($user, $resource);
        $review = $active && $authorizer->canReview($user, $resource);
        $revisioned = (bool) ($definition->capabilities['revisions'] ?? false);
        $pullManaged = $this->updatePolicies->isPullManaged($type);
        $reviewEnabled = in_array($type, self::REVIEW_ENABLED, true);

        return array_replace($this->vocabulary->denied(), [
            'canViewPrivateWorkspace' => true,
            'canEdit' => $edit,
            'canComment' => $comment,
            'canReply' => $comment,
            'canResolveComment' => $comment && ($edit || $admin),
            'canViewRevisionHistory' => $revisioned,
            'canCompareRevision' => $revisioned,
            'canCreateDraft' => $revisioned && $edit,
            'canApplyDraft' => $revisioned && $edit,
            'canReviewChanges' => $revisioned,
            'canPullUpdate' => $pullManaged,
            'canIgnoreUpdate' => $pullManaged,
            'canRequestShare' => $share,
            'canManageCollaborators' => $type === 'device' ? $admin : ($type === 'dashboard' ? $share : $admin),
            'canDirectGrant' => $admin,
            'canRevoke' => $type === 'dashboard' ? $share : $admin,
            'canSubmitReview' => $reviewEnabled && $edit,
            'canApproveReview' => $reviewEnabled && $review && $user->isPlatformAdmin(),
            'canPublish' => in_array($type, self::PUBLICATION_ENABLED, true) && $review && $user->isPlatformAdmin(),
            'canDisable' => $active && $admin,
            'canRestore' => ! $active && $admin,
            // Secret, activation, execution, and deployment authority is never
            // inferred from generic Edit. Domain services may expose these later.
            'canReadSecret' => false,
            'canWriteSecret' => false,
            'canRotateCredential' => false,
            'canReconnect' => false,
            'canActivate' => false,
            'canExecute' => false,
            'canDeploy' => false,
        ]);
    }

    public function allows(User $user, string $type, Model $resource, string $capability): bool
    {
        $this->vocabulary->assert($capability);
        return $this->for($user, $type, $resource)[$capability];
    }

    /** @return array<string, array{pullManaged: bool, reviewEnabled: bool, publicationEnabled: bool}> */
    public function metadata(): array
    {
        $result = [];
        foreach (array_keys($this->resources->metadata()) as $type) {
            $result[$type] = [
                'pullManaged' => $this->updatePolicies->isPullManaged($type),
                'presentationPolicy' => $this->updatePolicies->get($type)['mode'],
                'reviewEnabled' => in_array($type, self::REVIEW_ENABLED, true),
                'publicationEnabled' => in_array($type, self::PUBLICATION_ENABLED, true),
            ];
        }
        return $result;
    }
}
