<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
final class ResourceSectionRegistry{
 private const DEFINITIONS=[
  'device'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview'],['key'=>'dashboard','label'=>'Dashboard','tab'=>'dashboard'],['key'=>'parameters','label'=>'Parameters','tab'=>'parameters']],
  'device_template'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview'],['key'=>'parameters','label'=>'Parameters','tab'=>'parameters'],['key'=>'dashboard','label'=>'Dashboard','tab'=>'dashboard']],
  'dashboard'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview'],['key'=>'layout','label'=>'Layout','tab'=>'builder'],['key'=>'widgets','label'=>'Widgets','tab'=>'builder'],['key'=>'sources','label'=>'Sources','tab'=>'builder']],
  'automation'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview'],['key'=>'trigger','label'=>'Trigger','tab'=>'definition'],['key'=>'conditions','label'=>'Conditions','tab'=>'definition'],['key'=>'actions','label'=>'Actions','tab'=>'definition'],['key'=>'schedule','label'=>'Schedule','tab'=>'schedule']],
  'report'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview'],['key'=>'configuration','label'=>'Configuration','tab'=>'definition']],
  'firmware'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview'],['key'=>'artifact','label'=>'Artifact','tab'=>'artifact']],
  'location'=>[['key'=>'metadata','label'=>'Metadata','tab'=>'overview']],
  'webhook'=>[['key'=>'configuration','label'=>'Configuration','tab'=>'configuration']],
 ];
 private const ALIASES=['device'=>['device_dashboard'=>'dashboard'],'dashboard'=>['configuration'=>'widgets']];
 public function definitions(string$type):array{if(!isset(self::DEFINITIONS[$type]))throw ValidationException::withMessages(['resource_type'=>['Section Activity is not supported for this resource type.']]);return self::DEFINITIONS[$type];}
 public function normalize(string$type,array$keys):array{$defs=$this->definitions($type);$allowed=array_column($defs,'key');$aliases=self::ALIASES[$type]??[];$found=[];foreach($keys as$key){if(!is_string($key))continue;$key=$aliases[$key]??$key;if(in_array($key,$allowed,true))$found[$key]=true;}return array_values(array_filter($allowed,fn($key)=>isset($found[$key])));}
 public function validate(string$type,string$key):string{$normalized=$this->normalize($type,[$key]);if(count($normalized)!==1)throw ValidationException::withMessages(['section'=>['Unsupported section for this resource type.']]);return$normalized[0];}
 public function section(string$type,string$key):array{$key=$this->validate($type,$key);return collect($this->definitions($type))->firstWhere('key',$key);}
 public function metadata():array{return array_map(fn($defs)=>array_map(fn($d)=>['key'=>$d['key'],'label'=>$d['label']],$defs),self::DEFINITIONS);}
 public function mapAnchor(string$type,string$anchorType):array{return match(true){$anchorType==='resource'=>[],$type==='dashboard'&&$anchorType==='dashboard_widget'=>['widgets'],$type==='device'&&$anchorType==='dashboard_widget'=>['dashboard'],$type==='device'&&$anchorType==='parameter'=>['parameters'],$type==='location'&&in_array($anchorType,['metadata','name','description'],true)=>['metadata'],default=>[]};}
 public function decorate(string$type,array$keys):array{return array_map(fn($key)=>['key'=>$key,'label'=>$this->section($type,$key)['label'],'deepLinkTab'=>$this->section($type,$key)['tab']],$this->normalize($type,$keys));}
}
