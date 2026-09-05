<?php
namespace App\Services;
use App\Models\User;use Carbon\CarbonImmutable;
final class ReminderTimeResolver{
 public function timezone(User$user):string{$candidate=(string)data_get($user->organization?->settings,'timezone',config('app.timezone','UTC'));return in_array($candidate,timezone_identifiers_list(),true)?$candidate:(string)config('app.timezone','UTC');}
 public function forPreset(User$user,string$preset):?CarbonImmutable{$now=CarbonImmutable::now();return match($preset){'one_hour'=>$now->addHour(),'three_hours'=>$now->addHours(3),'tomorrow'=>$now->setTimezone($this->timezone($user))->addDay()->setTime(9,0)->utc(),'none'=>null};}
}