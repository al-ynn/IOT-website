<?php

namespace App\Revisions;

final class DeviceRevisionComparator
{
    public function compare(array $before, array $after): array
    {
        return array_values(array_filter([
            $this->metadata($before['metadata'], $after['metadata']),
            $this->dashboard($before['dashboard'], $after['dashboard']),
            $this->parameters($before['parameters'], $after['parameters']),
        ]));
    }

    private function metadata(array $before, array $after): ?array
    {
        $labels = ['name'=>'Name', 'type'=>'Type', 'protocol'=>'Protocol', 'location'=>'Location', 'templateId'=>'Template'];
        $entries = $this->fieldChanges($before, $after, $labels, 'metadata');
        return $entries ? $this->section('metadata', 'Device metadata', $entries) : null;
    }

    private function dashboard(?array $before, ?array $after): ?array
    {
        if ($before === null && $after === null) return null;
        if ($before === null) return $this->section('dashboard', 'Dashboard', [$this->entry('dashboard', $after['name'] ?? 'Dashboard', 'added', null, $this->summary($after))], 'added');
        if ($after === null) return $this->section('dashboard', 'Dashboard', [$this->entry('dashboard', $before['name'] ?? 'Dashboard', 'removed', $this->summary($before), null)], 'removed');

        $entries = $this->fieldChanges($before, $after, ['name'=>'Dashboard name', 'description'=>'Dashboard description'], 'dashboard');
        $old = $this->keyById($before['widgets'] ?? []);
        $new = $this->keyById($after['widgets'] ?? []);
        foreach (array_diff_key($new, $old) as $id => $widget) $entries[] = $this->entry("dashboard_widget:$id", $this->widgetLabel($widget), 'added', null, $this->widgetSummary($widget));
        foreach (array_diff_key($old, $new) as $id => $widget) $entries[] = $this->entry("dashboard_widget:$id", $this->widgetLabel($widget), 'removed', $this->widgetSummary($widget), null);
        foreach (array_intersect_key($old, $new) as $id => $oldWidget) {
            $newWidget = $new[$id];
            $changes = $this->widgetChanges($oldWidget, $newWidget);
            if (! $changes) continue;
            $types = array_unique(array_column($changes, 'changeType'));
            $type = count($types) === 1 ? $types[0] : 'changed';
            $entries[] = $this->entry("dashboard_widget:$id", $this->widgetLabel($newWidget), $type, $this->widgetSummary($oldWidget), $this->widgetSummary($newWidget), ['changes'=>$changes]);
        }
        return $entries ? $this->section('dashboard', 'Dashboard', $entries) : null;
    }

    private function parameters(array $before, array $after): ?array
    {
        $old = $this->keyById($before); $new = $this->keyById($after); $entries = [];
        foreach (array_diff_key($new, $old) as $id => $parameter) $entries[] = $this->entry("parameter:$id", $this->parameterLabel($parameter), 'added', null, $this->parameterSummary($parameter));
        foreach (array_diff_key($old, $new) as $id => $parameter) $entries[] = $this->entry("parameter:$id", $this->parameterLabel($parameter), 'removed', $this->parameterSummary($parameter), null);
        $labels = ['name'=>'Name', 'key'=>'Key', 'dataType'=>'Data type', 'unit'=>'Unit', 'description'=>'Description'];
        foreach (array_intersect_key($old, $new) as $id => $oldParameter) {
            $changes = $this->fieldChanges($oldParameter, $new[$id], $labels, "parameter:$id");
            if ($changes) $entries[] = $this->entry("parameter:$id", $this->parameterLabel($new[$id]), 'changed', $this->parameterSummary($oldParameter), $this->parameterSummary($new[$id]), ['changes'=>array_map(fn ($e) => $e['metadata'], $changes)]);
        }
        return $entries ? $this->section('parameters', 'Parameters', $entries) : null;
    }

    private function widgetChanges(array $before, array $after): array
    {
        $changes = [];
        $layout = $before['layout'] ?? []; $nextLayout = $after['layout'] ?? [];
        if (($layout['x'] ?? null) !== ($nextLayout['x'] ?? null) || ($layout['y'] ?? null) !== ($nextLayout['y'] ?? null)) $changes[] = $this->change('Position', 'moved', $this->position($layout), $this->position($nextLayout));
        if (($layout['w'] ?? null) !== ($nextLayout['w'] ?? null) || ($layout['h'] ?? null) !== ($nextLayout['h'] ?? null)) $changes[] = $this->change('Size', 'changed', $this->size($layout), $this->size($nextLayout));
        foreach (['title'=>'Title', 'type'=>'Widget type'] as $key=>$label) if (($before[$key] ?? null) !== ($after[$key] ?? null)) $changes[] = $this->change($label, 'changed', $before[$key] ?? null, $after[$key] ?? null);
        foreach (['deviceId'=>'Device', 'telemetryKey'=>'Telemetry key', 'timeRange'=>'Time range', 'chartType'=>'Chart style', 'minimum'=>'Minimum', 'maximum'=>'Maximum', 'unit'=>'Unit'] as $key=>$label) if (($before['configuration'][$key] ?? null) !== ($after['configuration'][$key] ?? null)) $changes[] = $this->change($label, 'changed', $before['configuration'][$key] ?? null, $after['configuration'][$key] ?? null);
        return $changes;
    }

    private function fieldChanges(array $before, array $after, array $labels, string $prefix): array
    {
        $entries=[];
        foreach ($labels as $key=>$label) if (($before[$key] ?? null) !== ($after[$key] ?? null)) $entries[]=$this->entry("$prefix:$key", $label, 'changed', $before[$key] ?? null, $after[$key] ?? null, $this->change($label, 'changed', $before[$key] ?? null, $after[$key] ?? null));
        return $entries;
    }

    private function keyById(array $items): array { $result=[]; foreach ($items as $item) if (isset($item['id'])) $result[(string)$item['id']]=$item; return $result; }
    private function section(string $key,string $label,array $entries,string $type='changed'):array{return ['key'=>$key,'label'=>$label,'changeType'=>$type,'entries'=>array_values($entries)];}
    private function entry(string $identity,string $label,string $type,mixed $before,mixed $after,array $metadata=[]):array{return ['identity'=>$identity,'label'=>$label,'changeType'=>$type,'before'=>$before,'after'=>$after,'metadata'=>$metadata];}
    private function change(string $field,string $type,mixed $before,mixed $after):array{return ['field'=>$field,'changeType'=>$type,'before'=>$before,'after'=>$after];}
    private function widgetLabel(array $w):string{return (string)($w['title']??$w['type']??'Widget');}
    private function widgetSummary(array $w):array{return ['title'=>$w['title']??null,'type'=>$w['type']??null,'position'=>$this->position($w['layout']??[]),'size'=>$this->size($w['layout']??[]),'configuration'=>$w['configuration']??[]];}
    private function parameterLabel(array $p):string{return (string)($p['name']??$p['key']??'Parameter');}
    private function parameterSummary(array $p):array{return array_intersect_key($p,array_flip(['name','key','dataType','unit','description']));}
    private function summary(array $v):array{return array_intersect_key($v,array_flip(['name','description']));}
    private function position(array $l):string{return 'Column '.(($l['x']??0)+1).', row '.(($l['y']??0)+1);}
    private function size(array $l):string{return ($l['w']??0).'×'.($l['h']??0);}
}
