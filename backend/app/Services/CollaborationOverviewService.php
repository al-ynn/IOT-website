<?php

namespace App\Services;

use App\Http\Resources\NotificationResource;
use App\Models\{Notification,User};

final class CollaborationOverviewService
{
    public function __construct(
        private MyWorkService $myWork,
        private SharedWithMeService $shared,
        private ResourceRevisionStateService $changes,
        private CollaborationActivityService $activity,
        private NotificationQueryService $notifications,
    ) {}

    public function forUser(User $user): array
    {
        abort_unless($user->status === 'active' && $user->organization_id, 403);
        $notificationCount = $this->notifications->unreadCount($user);
        $notificationPreview = $this->notifications->unreadPreview($user)
            ->map(fn (Notification $item) => (new NotificationResource($item))->resolve(request()))
            ->all();

        return [
            'context' => 'app_personal',
            'sections' => [
                'myWork' => $this->section('my_work', 'My Work', $this->myWork->count($user), $this->myWork->preview($user), '/app/my-work'),
                'sharedWithMe' => $this->section('shared_with_me', 'Shared With Me', $this->shared->count($user), $this->shared->preview($user), '/app/shared-with-me'),
                'changes' => $this->section('changes', 'Updates Waiting', $this->changes->changesCount($user), $this->changes->changesPreview($user), '/app/changes'),
                'notifications' => $this->section('notifications', 'Unread Notifications', $notificationCount, $notificationPreview, '/app/notifications'),
                'activity' => ['key'=>'activity','title'=>'Recent Collaboration Activity','status'=>'available','count'=>null,'preview'=>$this->activity->previewPersonal($user, 10),'destination'=>'/app/activity'],
                'search' => ['key'=>'search','title'=>'Search Resources','status'=>'available','count'=>null,'preview'=>[],'destination'=>'/app/search'],
            ],
            'adminShortcuts' => $user->isPlatformAdmin() ? [
                ['label'=>'Review Center','destination'=>'/admin/reviews'],['label'=>'Resources','destination'=>'/admin/resources'],
                ['label'=>'Recently Created','destination'=>'/admin/recently-created'],['label'=>'Recently Updated','destination'=>'/admin/recently-updated'],
                ['label'=>'Disabled Resources','destination'=>'/admin/disabled-resources'],
            ] : [],
        ];
    }

    private function section(string $key,string $title,int $count,array $preview,string $destination):array
    {
        return compact('key','title','count','preview','destination')+['status'=>'available'];
    }
}
