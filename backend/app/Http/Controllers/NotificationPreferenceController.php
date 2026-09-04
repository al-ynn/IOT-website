<?php
namespace App\Http\Controllers;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
final class NotificationPreferenceController extends Controller
{
    public function __construct(private NotificationPreferenceService $preferences) {}
    public function show(Request $request): array { return $this->preferences->payload($request->user()); }
    public function update(Request $request): array { $data = $request->validate(['categories' => ['sometimes','array'], 'categories.*' => ['boolean'], 'rules' => ['sometimes','array'], 'rules.*' => ['boolean'], 'user_id' => ['prohibited'], 'organization_id' => ['prohibited'], 'channels' => ['prohibited']]); return $this->preferences->replace($request->user(), $data); }
    public function reset(Request $request): array { $request->validate(['user_id' => ['prohibited'], 'organization_id' => ['prohibited']]); return $this->preferences->reset($request->user()); }
}
