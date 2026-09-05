<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ResourceRevisionAuthorizationService
{
    public function __construct(private CollaborationResourceRegistry $resources) {}

    public function resource(User $user,string $type,int|string $id)
    {
        $resolved=$this->resources->resolve($user,new CollaborationResourceReference($type,$id),'view');
        abort_unless($resolved->definition->capabilities['revisions']??false,404);
        return $resolved;
    }

    public function revision(User $user,string $type,int|string $id,int|string $revisionId):ResourceRevision
    {
        $resolved=$this->resource($user,$type,$id);
        return ResourceRevision::query()->whereKey($revisionId)->where('resource_type',$type)->where('resource_id',$resolved->resource->getKey())->firstOrFail();
    }

    public function assertBound(string $type,int|string $id,ResourceRevision ...$revisions):void
    {
        foreach($revisions as $revision)if($revision->resource_type!==$type||(string)$revision->resource_id!==(string)$id)throw ValidationException::withMessages(['revisions'=>['Revisions must belong to the same authorized resource.']]);
    }
}