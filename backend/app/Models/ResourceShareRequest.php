<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceShareRequest extends Model
{
    public const ACTIVE_STATUSES = ['pending_recipient', 'awaiting_admin_approval'];

    protected $fillable = ['resource_type', 'resource_id', 'sender_user_id', 'recipient_user_id', 'requested_permission', 'final_permission', 'status', 'active_key', 'note', 'approved_by', 'rejected_by', 'accepted_at', 'declined_at', 'approved_at', 'rejected_at', 'cancelled_at'];
    protected $casts = ['accepted_at' => 'datetime', 'declined_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
    public function recipient() { return $this->belongsTo(User::class, 'recipient_user_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function rejector() { return $this->belongsTo(User::class, 'rejected_by'); }
}
