<?php

namespace App\Services;

use App\Inventory\ResourceInventoryAdapterRegistry;
use App\Models\AdminResourceViewState;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminResourceViewService
{
    public function __construct(private ResourceInventoryAdapterRegistry $inventory, private MeaningfulUpdatePolicyRegistry $meaningful) {}

    public function mark(User $admin,string $type,string $id):array
    {
        abort_unless($admin->isPlatformAdmin(),403);
        $adapter=$this->inventory->get($type);
        $resource=DB::table($adapter->table)->where('id',$id)->when($adapter->softDeletes,fn($q)=>$q->whereNull('deleted_at'))->first(['id']);
        abort_unless($resource,404);
        $latest=$this->latest($type,$id);
        $state=DB::transaction(function()use($admin,$type,$id,$latest){
            $now=now();
            DB::table('admin_resource_view_states')->insertOrIgnore(['admin_user_id'=>$admin->id,'resource_type'=>$type,'resource_id'=>$id,'first_viewed_at'=>$now,'last_viewed_at'=>$now,'last_viewed_meaningful_revision_id'=>$latest?->id]);
            $state=AdminResourceViewState::where(['admin_user_id'=>$admin->id,'resource_type'=>$type,'resource_id'=>$id])->lockForUpdate()->firstOrFail();
            $marker=$state->last_viewed_meaningful_revision_id?ResourceRevision::find($state->last_viewed_meaningful_revision_id):null;
            if($marker&&($marker->resource_type!==$type||(string)$marker->resource_id!==(string)$id))$marker=null;
            $revisionId=$latest&&(!$marker||$latest->revision_number>$marker->revision_number)?$latest->id:$state->last_viewed_meaningful_revision_id;
            $state->update(['last_viewed_at'=>$now,'last_viewed_meaningful_revision_id'=>$revisionId]);return$state->refresh();
        });
        return$this->data($state,$latest);
    }

    public function decorate(User $admin,Collection $items):Collection
    {
        if($items->isEmpty())return$items;
        $idsByType=$items->groupBy('resourceType')->map(fn($rows)=>$rows->pluck('resourceId')->map(fn($id)=>(string)$id)->values());
        $states=AdminResourceViewState::where('admin_user_id',$admin->id)->where(function($q)use($idsByType){foreach($idsByType as$type=>$ids)$q->orWhere(fn($part)=>$part->where('resource_type',$type)->whereIn('resource_id',$ids));})->with('revision:id,resource_type,resource_id,revision_number')->get()->keyBy(fn($s)=>$s->resource_type.':'.$s->resource_id);
        $latest=collect();
        foreach($idsByType as$type=>$ids){foreach($this->latestFor($type,$ids->all())as$revision)$latest->put($type.':'.$revision->resource_id,$revision);}
        return$items->map(function(array$item)use($states,$latest){$key=$item['resourceType'].':'.$item['resourceId'];$state=$states->get($key);$current=$item['revision']??null;$currentRevision=$current?(object)['id'=>$current['id'],'revision_number'=>$current['number']]:$latest->get($key);return[...$item,...$this->data($state,$currentRevision)];});
    }

    private function latest(string$type,string$id):?ResourceRevision{return$this->eligible(ResourceRevision::where(['resource_type'=>$type,'resource_id'=>$id]),$type)->orderByDesc('revision_number')->first(['id','resource_type','resource_id','revision_number']);}
    private function latestFor(string$type,array$ids):Collection
    {
        if($ids===[])return collect();
        $query=$this->eligible(ResourceRevision::where('resource_type',$type)->whereIn('resource_id',$ids),$type)
            ->where('revision_number','=',function($q)use($type){$q->from('resource_revisions as candidate')->selectRaw('MAX(candidate.revision_number)')->where('candidate.resource_type',$type)->whereColumn('candidate.resource_id','resource_revisions.resource_id');$this->eligibility($q,$type,'candidate');});
        return$query->get(['id','resource_type','resource_id','revision_number']);
    }
    private function eligible($query,string$type){$this->eligibility($query,$type,'resource_revisions');return$query;}
    private function eligibility($query,string$type,string$alias):void
    {
        $query->where($alias.'.revision_number','>',1)->whereNotNull($alias.'.parent_revision_id')->whereNotNull($alias.'.created_by')->where(function($q)use($type,$alias){foreach($this->meaningful->meaningfulSections($type)as$section)$q->orWhereJsonContains($alias.'.changed_sections',$section);});
    }
    private function data(?AdminResourceViewState$state,?object$latest):array
    {
        if(!$state)return['viewState'=>'not_viewed','firstViewedAt'=>null,'lastViewedAt'=>null];
        $marker=$state->relationLoaded('revision')?$state->revision:($state->last_viewed_meaningful_revision_id?ResourceRevision::find($state->last_viewed_meaningful_revision_id):null);
        if($marker&&($marker->resource_type!==$state->resource_type||(string)$marker->resource_id!==(string)$state->resource_id))$marker=null;
        $updated=$latest&&(!$marker||(int)$latest->revision_number>(int)$marker->revision_number);
        return['viewState'=>$updated?'updated_since_view':'viewed','firstViewedAt'=>$state->first_viewed_at?->toISOString(),'lastViewedAt'=>$state->last_viewed_at?->toISOString()];
    }
}