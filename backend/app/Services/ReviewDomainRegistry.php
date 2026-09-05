<?php

namespace App\Services;

use App\Models\{Automation,Dashboard,DeviceTemplate,FirmwareArtifact,Report,Webhook};
use Illuminate\Validation\ValidationException;

final class ReviewDomainRegistry
{
    private const DOMAINS = [
        'device_template'=>['model'=>DeviceTemplate::class,'kind'=>'publication','label'=>'Template'],
        'dashboard'=>['model'=>Dashboard::class,'kind'=>'publication','label'=>'Dashboard'],
        'automation'=>['model'=>Automation::class,'kind'=>'publication','label'=>'Automation'],
        'report'=>['model'=>Report::class,'kind'=>'publication','label'=>'Report'],
        'webhook'=>['model'=>Webhook::class,'kind'=>'activation','label'=>'Webhook'],
        'firmware'=>['model'=>FirmwareArtifact::class,'kind'=>'release','label'=>'Firmware'],
    ];
    public function types():array{return array_keys(self::DOMAINS);}
    public function definitions():array{return self::DOMAINS;}
    public function definition(string $type):array{if(!isset(self::DOMAINS[$type]))throw ValidationException::withMessages(['resource_type'=>['Unsupported Review Center resource type.']]);return self::DOMAINS[$type];}
    public function actionSegment(string $type):string{return match($type){'device_template'=>'template-publication-submissions','dashboard'=>'dashboard-publication-submissions','automation'=>'automation-publication-submissions','report'=>'report-publication-submissions','webhook'=>'webhook-activation-submissions','firmware'=>'firmware-submissions'};}
    public function deepLink(string $type,int|string $submissionId):string{return "/admin/reviews/{$type}/{$submissionId}";}
    public function publicationTypes():array{return['device_template','dashboard','automation','report'];}
}
