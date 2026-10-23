<?php

namespace App\Notifications;

use App\Mail\ProjectNotificationMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notice about something that happened on a project, shown in the bell and, when the
 * setting is on, also sent by email. It carries only a title, one sentence and where
 * to go. Never scores or private contacts.
 *
 * It is queued so a slow or failing mail server never holds up the action of the user.
 * Each channel is its own queued job, so a failed email never repeats the bell entry.
 */
class ProjectActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $projectId,
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
        public readonly string $decision,
        public readonly ?string $dueDate = null,
    ) {}

    /**
     * Whether any channel is on, so callers can skip building a notice nobody will get.
     */
    public static function enabled(): bool
    {
        return (bool) config('app.notifications.channels.in_app', true)
            || (bool) config('app.notifications.channels.email', false);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('app.notifications.channels.in_app', true)) {
            $channels[] = 'database';
        }

        if (config('app.notifications.channels.email', false)
            && $notifiable instanceof User
            && $notifiable->is_active
            && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): ProjectNotificationMail
    {
        return (new ProjectNotificationMail(
            $notifiable,
            $this->projectId,
            $this->title,
            $this->message,
            $this->url,
            $this->decision,
            $this->dueDate,
        ))->to($notifiable->email, $notifiable->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->projectId,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'decision' => $this->decision,
            'due_date' => $this->dueDate,
        ];
    }
}
