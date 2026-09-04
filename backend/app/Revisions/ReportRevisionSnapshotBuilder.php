<?php
namespace App\Revisions;use App\Models\Report;
final class ReportRevisionSnapshotBuilder{public const SCHEMA_VERSION=1;public function build(Report $r):array{return ['metadata'=>['name'=>$r->name,'description'=>$r->description,'reportType'=>$r->report_type],'configuration'=>array_intersect_key($r->configuration,array_flip(['device_ids','metric_keys','date_range_mode','relative_range','from','to','severity','source']))];}}
