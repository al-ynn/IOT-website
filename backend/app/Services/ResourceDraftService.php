<?php
namespace App\Services;
use App\Collaboration\{CollaborationResourceReference,CollaborationResourceRegistry};use App\Models\{ResourceDraft,ResourceRevision,User};use App\Revisions\{DeviceRevisionSnapshotAdapter,DraftSnapshotPolicyRegistry};use Illuminate\Validation\ValidationException;
final class ResourceDraftService
{
 public function __construct(private CollaborationResourceRegistry $registry,private DraftSnapshotPolicyRegistry $snapshots,private DeviceRevisionSnapshotAdapter $devices){}
 public function show(User $u,string $type,int|string $id):?array{$this->resolve($u,$type,$id,'view');$d=ResourceDraft::where(['resource_type'=>$type,'resource_id'=>$id,'user_id'=>$u->id])->with('baseRevision')->first();return $d?$this->payload($d):null;}
 public function save(User $u,string $type,int|string $id,int|string $baseId,array $snapshot):array
 {
  $this->resolve($u,$type,$id,'edit');$base=ResourceRevision::find($baseId);
  if(!$base||$base->resource_type!==$type||(string)$base->resource_id!==(string)$id)throw ValidationException::withMessages(['base_revision_id'=>['Base revision must belong to this resource.']]);
  $safe=match($type){'device'=>$this->devices->adapt($snapshot,$base->snapshot_schema_version),'location'=>$this->locationSnapshot($snapshot),default=>$this->snapshots->sanitize($type,$snapshot)};
  $encoded=json_encode($safe);if(strlen($encoded)>262144)throw ValidationException::withMessages(['snapshot'=>['Draft exceeds the 256 KB limit.']]);
  $d=ResourceDraft::updateOrCreate(['resource_type'=>$type,'resource_id'=>$id,'user_id'=>$u->id],['base_revision_id'=>$base->id,'draft_schema_version'=>1,'snapshot'=>$safe]);return $this->payload($d->load('baseRevision'));
 }
 public function delete(User $u,string $type,int|string $id):void{$this->resolve($u,$type,$id,'view');ResourceDraft::where(['resource_type'=>$type,'resource_id'=>$id,'user_id'=>$u->id])->delete();}
 private function resolve(User $u,string $type,int|string $id,string $ability){abort_unless($this->snapshots->supports($type),404);return $this->registry->resolve($u,new CollaborationResourceReference($type,$id),$ability);}
 private function locationSnapshot(array $snapshot):array
 {
  if(array_keys($snapshot)!==['metadata']||!is_array($snapshot['metadata']))throw ValidationException::withMessages(['snapshot'=>['Location drafts may contain metadata only.']]);
  $metadata=$snapshot['metadata'];$unknown=array_diff(array_keys($metadata),['name','description']);if($unknown)throw ValidationException::withMessages(['snapshot'=>['Location draft contains unsupported fields.']]);
  $name=trim((string)($metadata['name']??''));if($name===''||mb_strlen($name)>120)throw ValidationException::withMessages(['snapshot.metadata.name'=>['Name is required and may not exceed 120 characters.']]);
  $description=$metadata['description']??null;if($description!==null&&(!is_string($description)||mb_strlen($description)>1000))throw ValidationException::withMessages(['snapshot.metadata.description'=>['Description may not exceed 1000 characters.']]);
  return ['metadata'=>['name'=>$name,'description'=>$description===null?null:trim($description)]];
 }
 private function payload(ResourceDraft $d):array{$latest=ResourceRevision::where('resource_type',$d->resource_type)->where('resource_id',$d->resource_id)->orderByDesc('revision_number')->first();return ['id'=>(string)$d->id,'resourceType'=>$d->resource_type,'resourceId'=>(string)$d->resource_id,'baseRevisionId'=>(string)$d->base_revision_id,'baseRevisionNumber'=>$d->baseRevision->revision_number,'latestRevisionId'=>(string)$latest?->id,'isBehind'=>$latest&&$latest->id!==$d->base_revision_id,'draftSchemaVersion'=>$d->draft_schema_version,'snapshot'=>$d->snapshot,'updatedAt'=>$d->updated_at->toISOString()];}
}
