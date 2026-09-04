<?php
namespace App\Revisions;
use App\Models\Dashboard;
final class DashboardRevisionSnapshotBuilder
{
    public const SCHEMA_VERSION = 1;
    public function build(Dashboard $dashboard): array
    {
        $dashboard->loadMissing('widgets');
        $snapshot=['metadata'=>['name'=>$dashboard->name,'description'=>$dashboard->description],'dashboard'=>['id'=>(string)$dashboard->id,'name'=>$dashboard->name,'description'=>$dashboard->description,'widgets'=>$dashboard->widgets->map(fn($w)=>['id'=>(string)$w->id,'type'=>$w->widget_type,'title'=>$w->title,'layout'=>$w->layout,'configuration'=>$w->configuration])->sortBy('id')->values()->all()],'parameters'=>[]];
        return $this->normalize($snapshot);
    }
    private function normalize(array $value): array { $walk=function($item)use(&$walk){if(!is_array($item))return $item;if(array_is_list($item))return array_map($walk,$item);ksort($item);return array_map($walk,$item);};return $walk($value); }
}