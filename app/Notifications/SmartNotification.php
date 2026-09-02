<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Typed smart notification.
 *
 * Savo's smart notifications are persisted directly through the app's smart
 * notification service (they are only read in-app, never emailed or pushed),
 * so this class exists purely as the canonical `type` marker stored on the
 * notifications row. Keeping it an actual Notification keeps the framework's
 * notification infrastructure fully compatible.
 */
class SmartNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => []];
    }
}