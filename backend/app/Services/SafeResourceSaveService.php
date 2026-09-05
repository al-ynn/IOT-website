<?php

namespace App\Services;

use App\Models\User;
use App\Models\ResourceRevision;
use App\Models\ResourceSaveIdempotency;
use App\Models\ResourceDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the transaction and lock order for revisionable configuration saves.
 * Authorization and lifecycle callbacks are deliberately re-run after locking;
 * a controller-side check is only an early rejection, never save authority.
 */
final class SafeResourceSaveService
{
    private const OPERATION_SCOPE = 'resource_configuration_save';

    public function __construct(private RevisionConflictService $conflicts, private ResourceSaveFingerprintService $fingerprints) {}

    public function execute(
        User $actor,
        string $resourceType,
        string $modelClass,
        int|string $resourceId,
        int|string|null $baseRevisionId,
        callable $authorize,
        callable $assertLifecycle,
        callable $mutate,
        callable $recordRevision,
        bool $requireBase = false,
        ?string $idempotencyKey = null,
        array $command = [],
    ): Model {
        if ($idempotencyKey !== null && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Idempotency-Key must be a UUID.']]);
        }

        $keyHash = $idempotencyKey === null ? null : hash('sha256', $idempotencyKey);
        $fingerprint = $keyHash === null ? null : $this->fingerprints->fingerprint($resourceType, $resourceId, $baseRevisionId, $command);

        return DB::transaction(function () use ($actor, $resourceType, $modelClass, $resourceId, $baseRevisionId, $authorize, $assertLifecycle, $mutate, $recordRevision, $requireBase, $keyHash, $fingerprint) {
            /** @var Model $resource */
            $resource = $modelClass::query()->whereKey($resourceId)->lockForUpdate()->firstOrFail();

            $authorize($actor, $resource);

            $ledger = $keyHash === null ? null : ResourceSaveIdempotency::query()->where([
                'user_id' => $actor->id,
                'operation_scope' => self::OPERATION_SCOPE,
                'key_hash' => $keyHash,
            ])->lockForUpdate()->first();

            if ($ledger) {
                if (! hash_equals($ledger->request_fingerprint, (string) $fingerprint)) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'This idempotency key was already used for a different save command.',
                        'code' => 'idempotency_key_reused',
                    ], 409));
                }
                if ($ledger->completed_at === null) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The original save command is still in progress.',
                        'code' => 'idempotency_in_progress',
                    ], 409));
                }
                return $this->withOutcome($resource->refresh(), $ledger, true);
            }

            if ($keyHash !== null) {
                $ledger = ResourceSaveIdempotency::create([
                    'user_id' => $actor->id,
                    'operation_scope' => self::OPERATION_SCOPE,
                    'key_hash' => $keyHash,
                    'request_fingerprint' => $fingerprint,
                    'resource_type' => $resourceType,
                    'resource_id' => (string) $resourceId,
                    'base_revision_id' => $baseRevisionId,
                    'expires_at' => now()->addHours(24),
                ]);
            }

            $assertLifecycle($resource);
            $this->conflicts->assertBase($actor, $resourceType, $resource->getKey(), $baseRevisionId, true, $requireBase);

            $before = $this->conflicts->latest($resourceType, $resource->getKey(), true);
            $mutated = $mutate($resource);
            $resource = $mutated instanceof Model ? $mutated : $resource;
            $recordRevision($resource, $actor);
            $after = $this->conflicts->latest($resourceType, $resource->getKey(), true);
            ResourceDraft::query()->where([
                'resource_type' => $resourceType,
                'resource_id' => $resource->getKey(),
                'user_id' => $actor->id,
            ])->delete();

            if ($ledger) {
                $changed = $after !== null && $after->id !== $before?->id;
                $ledger->update([
                    'changed' => $changed,
                    'committed_revision_id' => $changed ? $after->id : $before?->id,
                    'committed_revision_number' => $changed ? $after->revision_number : $before?->revision_number,
                    'completed_at' => now(),
                ]);
                return $this->withOutcome($resource->refresh(), $ledger->refresh(), false);
            }

            return $resource->refresh();
        });
    }

    private function withOutcome(Model $resource, ResourceSaveIdempotency $ledger, bool $replayed): Model
    {
        $resource->setAttribute('save_idempotency_outcome', [
            'replayed' => $replayed,
            'changed' => $ledger->changed,
            'committedRevisionId' => $ledger->committed_revision_id === null ? null : (string) $ledger->committed_revision_id,
            'committedRevisionNumber' => $ledger->committed_revision_number,
            'completedAt' => $ledger->completed_at?->toISOString(),
        ]);
        return $resource;
    }
}
