<?php

namespace App\Notifications;

use Illuminate\Validation\ValidationException;

final class NotificationRuleRegistry
{
    private const RULES = [
        'device.created' => ['category' => 'updates', 'delivery' => 'admin_current_role', 'coalescing' => 'none'],
        'device.event' => ['category' => 'alerts', 'delivery' => 'device_access', 'coalescing' => 'none'],
        'comment.created' => ['category' => 'mentions', 'delivery' => 'current_access', 'coalescing' => 'none'], 'comment.mentioned' => ['category' => 'mentions', 'delivery' => 'current_access', 'coalescing' => 'none'], 'comment.acknowledged' => ['category' => 'mentions', 'delivery' => 'current_access', 'coalescing' => 'none'],
        'access.requested' => ['category' => 'requests', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'access.awaiting_approval' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'access.accepted' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'access.declined' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'access.cancelled' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'access.granted' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'access.rejected' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'],
        'device.access_granted' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'device.access_upgraded' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'device.access_downgraded' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'device.access_revoked' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'],
        'access.revoked' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'], 'access.changed' => ['category' => 'updates', 'delivery' => 'involved_user', 'coalescing' => 'none'],
        'automation.notification' => ['category' => 'updates', 'delivery' => 'organization_current_role', 'coalescing' => 'none'],
        'revision.available' => ['category' => 'updates', 'delivery' => 'current_access', 'coalescing' => 'latest_per_resource'], 'resource.needs_attention' => ['category' => 'requests', 'delivery' => 'current_access', 'coalescing' => 'latest_per_resource'],
        'template.publication.submitted' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'report.publication.submitted' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'automation.publication.submitted' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'firmware.publication.submitted' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'],
        'dashboard.publication.submitted' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'dashboard.publication.published' => ['category' => 'updates', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'dashboard.publication.approved' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'dashboard.publication.changes_requested' => ['category' => 'requests', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'dashboard.publication.rejected' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'],
        'firmware.release_submitted' => ['category' => 'approvals', 'delivery' => 'admin_current_role', 'coalescing' => 'none'], 'firmware.release_approved' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'firmware.release_changes_requested' => ['category' => 'requests', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'firmware.release_rejected' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'],
        'template.publication.approved' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'template.publication.changes_requested' => ['category' => 'requests', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'template.publication.rejected' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'report.publication.approved' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'report.publication.changes_requested' => ['category' => 'requests', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'report.publication.rejected' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'automation.publication.approved' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'automation.publication.changes_requested' => ['category' => 'requests', 'delivery' => 'terminal_fact', 'coalescing' => 'none'], 'automation.publication.rejected' => ['category' => 'approvals', 'delivery' => 'terminal_fact', 'coalescing' => 'none']];

    public function definition(string $key): array
    {
        if (! isset(self::RULES[$key])) {
            throw ValidationException::withMessages(['event' => ['Unsupported Notification event.']]);
        }

return ['key' => $key, ...self::RULES[$key], 'payloadSchemaVersion' => 1, 'recipientPolicy' => 'server_derived'];
    }

    public function known(string $key): bool
    {
        return isset(self::RULES[$key]);
    }

    public function keys(): array
    {
        return array_keys(self::RULES);
    }
}
