<?php
namespace App\Services\Automation;
class ConditionEvaluator{
 public function evaluate(array $group,array $context):bool{$conditions=$group['conditions']??[];if($conditions===[])return true;$results=array_map(fn($c)=>$this->condition($c,data_get($context,$c['field'])), $conditions);return ($group['logic']??'AND')==='OR'?in_array(true,$results,true):!in_array(false,$results,true);}
 private function condition(array $c,mixed $actual):bool{return match($c['operator']){'>'=>(float)$actual>(float)$c['value'],'<'=>(float)$actual<(float)$c['value'],'>='=>(float)$actual>=(float)$c['value'],'<='=>(float)$actual<=(float)$c['value'],'='=>(string)$actual===(string)$c['value'],'!='=>(string)$actual!==(string)$c['value'],'contains'=>str_contains((string)$actual,(string)$c['value']),'starts_with'=>str_starts_with((string)$actual,(string)$c['value']),'ends_with'=>str_ends_with((string)$actual,(string)$c['value']),'exists'=>$actual!==null,default=>false};}
}
