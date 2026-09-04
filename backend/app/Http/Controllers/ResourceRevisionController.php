<?php

namespace App\Http\Controllers;

use App\Models\ResourceRevision;
use App\Services\ResourceRevisionAuthorizationService;
use App\Services\RevisionSnapshotPresenter;
use Illuminate\Http\Request;

final class ResourceRevisionController extends Controller
{
    public function __construct(private ResourceRevisionAuthorizationService $authorization,private RevisionSnapshotPresenter $snapshots) {}

    public function index(Request $request,string $type,string $resource)
    {
        $resolved=$this->authorization->resource($request->user(),$type,$resource);
        $perPage=$request->validate(['per_page'=>['nullable','integer','min:1','max:50']])['per_page']??20;
        $revisions=ResourceRevision::query()->where('resource_type',$type)->where('resource_id',$resolved->resource->getKey())->with('author:id,name')->orderByDesc('revision_number')->paginate($perPage);
        $current=$revisions->first()?->revision_number;
        return response()->json($revisions->through(fn($revision)=>$this->metadata($revision,$revision->revision_number===$current)));
    }

    public function show(Request $request,string $type,string $resource,string $revision)
    {
        $revision=$this->authorization->revision($request->user(),$type,$resource,$revision);
        $revision->load('author:id,name');
        $current=!ResourceRevision::query()->where('resource_type',$type)->where('resource_id',$revision->resource_id)->where('revision_number','>',$revision->revision_number)->exists();
        return [...$this->metadata($revision,$current),'snapshotSchemaVersion'=>$revision->snapshot_schema_version,'snapshot'=>$this->snapshots->present($type,$revision->snapshot)];
    }

    private function metadata(ResourceRevision $revision,bool $current):array
    {
        return ['id'=>(string)$revision->id,'revisionNumber'=>$revision->revision_number,'createdBy'=>$revision->author?['id'=>(string)$revision->author->id,'name'=>$revision->author->name]:null,'createdAt'=>$revision->created_at->toISOString(),'changedSections'=>$revision->changed_sections,'changeSummary'=>$revision->change_summary,'isCurrent'=>$current];
    }
}