<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class NotificationOutboxEvent extends Model{protected $fillable=['event_type','fact_identity','aggregate_type','aggregate_id','payload','attempts','available_at','processed_at','failed_at','last_error_code'];protected $casts=['payload'=>'array','available_at'=>'datetime','processed_at'=>'datetime','failed_at'=>'datetime'];}
