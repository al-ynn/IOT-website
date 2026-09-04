<?php
namespace App\Revisions;use App\Models\Webhook;
final class WebhookRevisionSnapshotBuilder{public const SCHEMA_VERSION=1;public function build(Webhook $w):array{return ['configuration'=>['name'=>$w->name,'url'=>$w->url,'event_types'=>array_values($w->event_types??[])]];}}