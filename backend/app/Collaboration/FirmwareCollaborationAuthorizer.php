<?php
namespace App\Collaboration;
use App\Models\ResourceCollaborator;use App\Models\User;use Illuminate\Database\Eloquent\Model;
final class FirmwareCollaborationAuthorizer implements CollaborationResourceAuthorizer
{
    private function admin(User $user,Model $resource):bool{return $user->isActive()&&$user->isPlatformAdmin()&&(int)$user->organization_id===(int)$resource->getAttribute('organization_id');}
    private function permission(User $user,Model $resource):?string{if(!$user->isActive()||(int)$user->organization_id!==(int)$resource->getAttribute('organization_id'))return null;return ResourceCollaborator::where(['resource_type'=>'firmware','resource_id'=>$resource->getKey(),'user_id'=>$user->id])->value('permission');}
    public function canView(User $user,Model $resource):bool{return $this->admin($user,$resource)||in_array($this->permission($user,$resource),['view','edit'],true);}
    public function canEdit(User $user,Model $resource):bool{return $this->admin($user,$resource)||$this->permission($user,$resource)==='edit';}
    public function canShare(User $user,Model $resource):bool{return $this->canEdit($user,$resource);}
    public function canComment(User $user,Model $resource):bool{return $this->canView($user,$resource);}
    public function canReview(User $user,Model $resource):bool{return $this->admin($user,$resource);}
    public function canAdminister(User $user,Model $resource):bool{return $this->admin($user,$resource);}
}
