<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
{
    public function toArray(Request $r): array
    {
        $p = parse_url($this->url);
        $display = isset($p['scheme'],$p['host']) ? $p['scheme'].'://'.$p['host'].($p['path'] ?? '') : 'Invalid URL';

        return ['id' => (string) $this->id, 'name' => $this->name, 'url' => $display, 'enabled' => $this->enabled, 'eventTypes' => $this->event_types, 'secretPrefix' => $this->secret_prefix, 'createdBy' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null, 'lastDeliveryAt' => $this->last_delivery_at?->toISOString(), 'createdAt' => $this->created_at?->toISOString(), 'updatedAt' => $this->updated_at?->toISOString()];
    }
}
