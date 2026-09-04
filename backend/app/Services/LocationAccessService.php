<?php
namespace App\Services;
use App\Collaboration\LocationResourceAuthorizer;use App\Models\{Location,User};
final class LocationAccessService
{
 public function __construct(private LocationResourceAuthorizer $collaboration){}
 public function canReference(User $u,Location $l):bool{return $u->status==='active'&&(int)$u->organization_id===(int)$l->organization_id;}
 public function canView(User $u,Location $l):bool{return $this->collaboration->canView($u,$l);}
 public function canAdminister(User $u,Location $l):bool{return $u->isPlatformAdmin()&&(int)$u->organization_id===(int)$l->organization_id;}
 public function findReferenceable(User $u,int|string $id):Location{$l=Location::findOrFail($id);abort_unless($this->canReference($u,$l),404);return $l;}
 public function findViewable(User $u,int|string $id):Location{$l=Location::findOrFail($id);abort_unless($this->canView($u,$l),404);return $l;}
 public function findAdmin(User $u,int|string $id):Location{abort_unless($u->isPlatformAdmin(),403);return Location::where('organization_id',$u->organization_id)->findOrFail($id);}
}
