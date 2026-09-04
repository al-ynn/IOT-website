<?php
namespace App\Services;
use App\Collaboration\ReportCollaborationAuthorizer;use App\Models\{Report,ReportRun,ResourcePublicationVersion,User};
final class ReportAccessService{
 public function __construct(private ReportCollaborationAuthorizer $reports,private ReportConfigurationService $configuration){}
 public function canView(User $u,Report $r):bool{return $this->reports->canView($u,$r);}public function canEdit(User $u,Report $r):bool{return $this->reports->canEdit($u,$r);}
 public function canRun(User $u,Report $r):bool{if(!$this->canView($u,$r)||app(ResourceLifecycleService::class)->state('report',$r->id)!=='active')return false;try{$this->configuration->assertCurrentAccess($u,$r->configuration);return true;}catch(\Throwable){return false;}}
 public function canViewPublished(User $u,ResourcePublicationVersion $v):bool{$r=Report::find($v->resource_id);return $r&&$v->resource_type==='report'&&(int)$r->organization_id===(int)$u->organization_id&&app(ResourceLifecycleService::class)->state('report',$r->id)==='active';}
 public function canRunPublished(User $u,ResourcePublicationVersion $v):bool{if(!$this->canViewPublished($u,$v))return false;try{$this->configuration->assertCurrentAccess($u,$v->revision->snapshot['configuration']??[]);return true;}catch(\Throwable){return false;}}
 public function assertRun(User $u,Report $r):void{abort_unless($this->canView($u,$r),404);app(ResourceLifecycleService::class)->assertActive('report',$r->id,'Disabled or Archived Reports cannot run.');$this->configuration->assertCurrentAccess($u,$r->configuration);}
 public function canViewRun(User $u,ReportRun $run):bool{if($u->isPlatformAdmin())return true;if((int)$run->requested_by!==(int)$u->id||!$run->report)return false;if($run->publication_version_id){$v=$run->publicationVersion;return $v&&$this->canViewPublished($u,$v);}return $this->canView($u,$run->report);}
 public function assertViewRun(User $u,ReportRun $run):void{abort_unless($this->canViewRun($u,$run),404);$this->configuration->assertCurrentAccess($u,$run->resolved_configuration);}
}
