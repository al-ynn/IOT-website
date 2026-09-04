<?php
namespace App\Notifications;
use Illuminate\Validation\ValidationException;
final class NotificationActionRegistry{
 private const ACTIONS=[
  'open'=>['kind'=>'navigate','label'=>'Open','confirmation'=>false],
  'open_resource'=>['kind'=>'navigate','label'=>'Open resource','confirmation'=>false],
  'open_comment'=>['kind'=>'navigate','label'=>'Open comment','confirmation'=>false],
  'open_share_request'=>['kind'=>'navigate','label'=>'Open request','confirmation'=>false],
  'review_changes'=>['kind'=>'navigate','label'=>'Review changes','confirmation'=>false],
  'pull_update'=>['kind'=>'navigate','label'=>'Pull or ignore update','confirmation'=>false],
  'open_review'=>['kind'=>'navigate','label'=>'Open review','confirmation'=>false],
  'open_published_version'=>['kind'=>'navigate','label'=>'Open published version','confirmation'=>false],
 ];
 public function definition(string$key):array{if(!isset(self::ACTIONS[$key]))throw ValidationException::withMessages(['action'=>['Unsupported Notification action.']]);return['key'=>$key,...self::ACTIONS[$key]];}
 public function navigation(string$key,string$href,?string$label=null):array{$d=$this->definition($key);return['key'=>$key,'kind'=>$d['kind'],'label'=>$label??$d['label'],'href'=>$href,'requiresConfirmation'=>$d['confirmation']];}
 public function keys():array{return array_keys(self::ACTIONS);}
}