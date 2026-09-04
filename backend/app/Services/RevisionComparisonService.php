<?php

namespace App\Services;

use App\Models\CollaborationThread;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Revisions\DeviceRevisionComparator;
use App\Revisions\DeviceRevisionSnapshotAdapter;

final class RevisionComparisonService
{
    public function __construct(
        private DeviceRevisionSnapshotAdapter $adapter,
        private DeviceRevisionComparator $devices,
        private ResourceRevisionAuthorizationService $authorization,
        private RevisionSnapshotPresenter $presenter,
    ) {}

    public function compareIds(User $user,string $type,int|string $resourceId,int|string $fromId,int|string $toId,?string $focus=null):array
    {
        $from=$this->authorization->revision($user,$type,$resourceId,$fromId);
        $to=$this->authorization->revision($user,$type,$resourceId,$toId);
        return $this->compare($user,$type,$resourceId,$from,$to,$focus);
    }

    public function compare(User $user,string $type,int|string $resourceId,ResourceRevision $from,ResourceRevision $to,?string $focus=null):array
    {
        $resolved=$this->authorization->resource($user,$type,$resourceId);
        $this->authorization->assertBound($type,$resolved->resource->getKey(),$from,$to);
        if($from->revision_number>$to->revision_number)[$from,$to]=[$to,$from];
        if(!in_array($type,['device','dashboard'],true))abort(422,'Comparison unavailable for this resource type.');

        $beforeSnapshot=$this->presenter->present($type,$from->snapshot);
        $afterSnapshot=$this->presenter->present($type,$to->snapshot);
        $before=$type==='device'?$this->adapter->adapt($beforeSnapshot,$from->snapshot_schema_version):$beforeSnapshot;
        $after=$type==='device'?$this->adapter->adapt($afterSnapshot,$to->snapshot_schema_version):$afterSnapshot;
        $sections=$this->devices->compare($before,$after);
        $range=ResourceRevision::query()->where('resource_type',$type)->where('resource_id',$resolved->resource->getKey())->whereBetween('revision_number',[$from->revision_number+1,$to->revision_number])->with('author:id,name')->orderBy('revision_number')->get();

        return [
            'resource'=>['type'=>$type,'id'=>(string)$resolved->resource->getKey(),'label'=>$resolved->resource->name],
            'fromRevision'=>$this->revision($from),
            'toRevision'=>$this->revision($to),
            'sections'=>$sections,
            'pullableChangedSections'=>array_values(array_intersect(array_column($sections,'key'),['dashboard'])),
            'revisions'=>$range->map(fn($revision)=>$this->revision($revision))->values(),
            'summary'=>[
                'changedSections'=>array_values(array_column($sections,'key')),
                'changeCount'=>array_sum(array_map(fn($section)=>count($section['entries']),$sections)),
                'intermediateRevisions'=>$range->map(fn($revision)=>$this->revision($revision))->values(),
                'contributors'=>$range->pluck('author')->filter()->unique('id')->values()->map(fn($author)=>['id'=>(string)$author->id,'name'=>$author->name]),
            ],
            'focus'=>$focus,
        ];
    }

    public function acceptedToLatest(User $user,string $type,int|string $resourceId):array
    {
        $state=app(ResourceRevisionStateService::class)->state($user,$type,$resourceId);
        return $this->compareIds($user,$type,$resourceId,$state['acceptedRevision']['id'],$state['latestRevision']['id']);
    }

    public function previousId(User $user,string $type,int|string $resourceId,int|string $revisionId):array
    {
        return $this->previous($user,$type,$resourceId,$this->authorization->revision($user,$type,$resourceId,$revisionId));
    }

    public function previous(User $user,string $type,int|string $resourceId,ResourceRevision $revision):array
    {
        $resolved=$this->authorization->resource($user,$type,$resourceId);
        $this->authorization->assertBound($type,$resolved->resource->getKey(),$revision);
        $previous=ResourceRevision::query()->where('resource_type',$type)->where('resource_id',$resourceId)->where('revision_number','<',$revision->revision_number)->orderByDesc('revision_number')->firstOrFail();
        return $this->compare($user,$type,$resourceId,$previous,$revision);
    }

    public function threadContext(User $user,CollaborationThread $thread):?array
    {
        if(!$thread->anchor_revision_id)return null;
        try{$anchor=$this->authorization->revision($user,$thread->resource_type,$thread->resource_id,$thread->anchor_revision_id);}catch(\Throwable){return null;}
        $latest=ResourceRevision::query()->where('resource_type',$thread->resource_type)->where('resource_id',$thread->resource_id)->orderByDesc('revision_number')->first();
        if(!$latest||$anchor->id===$latest->id)return ['status'=>'unchanged','message'=>null,'focus'=>null];
        $focus=$this->focus($thread);
        $comparison=$this->compare($user,$thread->resource_type,$thread->resource_id,$anchor,$latest,$focus);
        $entries=collect($comparison['sections'])->flatMap(fn($section)=>$section['entries']);
        $changed=$thread->anchor_type==='resource'?$entries->isNotEmpty():$entries->firstWhere('identity',$focus);
        if(!$changed)return ['status'=>'unchanged','message'=>null,'focus'=>$focus];
        $removed=is_array($changed)&&($changed['changeType']??null)==='removed';
        return ['status'=>$removed?'removed':'changed','message'=>$removed?'Referenced section was removed after this comment was created.':'This section changed after this comment was created.','focus'=>$focus,'fromRevisionId'=>(string)$anchor->id,'toRevisionId'=>(string)$latest->id];
    }

    private function revision(ResourceRevision $revision):array
    {
        $revision->loadMissing('author:id,name');
        return ['id'=>(string)$revision->id,'revisionNumber'=>$revision->revision_number,'createdBy'=>$revision->author?['id'=>(string)$revision->author->id,'name'=>$revision->author->name]:null,'createdAt'=>$revision->created_at->toISOString(),'changedSections'=>$revision->changed_sections,'changeSummary'=>$revision->change_summary,'snapshotSchemaVersion'=>$revision->snapshot_schema_version];
    }

    private function focus(CollaborationThread $thread):?string
    {
        if($thread->anchor_type==='resource')return null;
        return $thread->anchor_key?"{$thread->anchor_type}:{$thread->anchor_key}":null;
    }
}