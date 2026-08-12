<?php

namespace App\Events;


use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;



class TelemetryUpdated implements ShouldBroadcast
{

    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;



    public array $telemetry;



    public function __construct(array $telemetry)
    {
        $this->telemetry = $telemetry;
    }



    public function broadcastOn()
    {
        return new Channel('telemetry');
    }



    public function broadcastAs()
    {
        return 'telemetry.updated';
    }

}