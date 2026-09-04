<?php
namespace App\Services;
use App\Models\{Notification,User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
final class NotificationQueryService
{
 public function owned(User$user):Builder{return Notification::query()->where('user_id',$user->id);}
 public function activeUnread(User$user):Builder{return$this->owned($user)->whereNull('dismissed_at')->whereNull('read_at');}
 public function unreadCount(User$user):int{return$this->activeUnread($user)->count();}
 public function unreadPreview(User$user,int$limit=5):Collection{return$this->activeUnread($user)->with(['actor:id,name','organization:id,name'])->latest('created_at')->latest('id')->limit(min(max($limit,1),5))->get();}
 public function recent(User$user,int$limit=5):Collection{return$this->owned($user)->whereNull('dismissed_at')->with(['actor:id,name','organization:id,name'])->latest('created_at')->latest('id')->limit(min(max($limit,1),5))->get();}
 public function findOwned(User$user,string$id):Notification{return$this->owned($user)->findOrFail($id);}
}
