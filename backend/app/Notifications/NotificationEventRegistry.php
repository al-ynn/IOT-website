<?php

namespace App\Notifications;

final class NotificationEventRegistry
{
    public const ACTIVE = ['device.created', 'device.event', 'device.access_granted', 'device.access_upgraded', 'device.access_downgraded', 'device.access_revoked', 'legacy.notification', 'comment.created', 'comment.mentioned', 'comment.acknowledged', 'access.requested', 'access.accepted', 'access.declined', 'access.awaiting_approval', 'access.granted', 'access.rejected', 'access.cancelled', 'resource.needs_attention'];
    public const RESERVED = ['resource.shared', 'resource.updated', 'revision.available', 'review.started', 'review.changes_requested', 'review.approved', 'resource.disabled'];

    public static function known(string $eventType): bool { return in_array($eventType, [...self::ACTIVE, ...self::RESERVED], true); }
}
