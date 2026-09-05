<?php
namespace App\Services;
use App\Models\User;
final class CollaborationIdentitySummary{public function fromUser(?User $user):?array{if(!$user)return null;return['id'=>(string)$user->id,'displayName'=>$user->name,'state'=>$user->status==='active'?'active':'inactive'];}}