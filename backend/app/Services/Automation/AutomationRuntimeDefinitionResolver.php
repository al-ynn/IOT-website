<?php
namespace App\Services\Automation;
use App\Models\{Automation,ResourcePublicationState,ResourceRevision};
final class AutomationRuntimeDefinitionResolver{public function resolve(Automation $a):array{$state=ResourcePublicationState::where(['resource_type'=>'automation','resource_id'=>$a->id])->with('currentVersion.revision')->first();$version=$state?->currentVersion;$revision=$version?->revision??ResourceRevision::where(['resource_type'=>'automation','resource_id'=>$a->id])->latest('revision_number')->firstOrFail();return ['revision'=>$revision,'publicationVersion'=>$version,'snapshot'=>$revision->snapshot];}}
