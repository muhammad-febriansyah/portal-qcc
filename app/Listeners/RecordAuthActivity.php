<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;

class RecordAuthActivity
{
    public function __construct(private AuditService $audit) {}

    public function onLogin(Login $event): void
    {
        $this->audit->record('auth.login', $event->user instanceof Model ? $event->user : null);
    }

    public function onLogout(Logout $event): void
    {
        $this->audit->record('auth.logout', $event->user instanceof Model ? $event->user : null);
    }

    public function onFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->audit->record(
            'auth.login_failed',
            after: ['email' => is_string($email) ? $email : null],
            subjectLabel: is_string($email) ? $email : null,
        );
    }

    public function onRegistered(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->record(
            'user.registered',
            $event->user,
            after: ['role' => 'SIMer / Member'],
            subjectLabel: $event->user->name,
        );
    }

    public function onVerified(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->record(
            'user.email_verified',
            $event->user,
            subjectLabel: $event->user->name,
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
            Registered::class => 'onRegistered',
            Verified::class => 'onVerified',
        ];
    }
}
