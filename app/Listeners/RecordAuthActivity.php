<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class RecordAuthActivity
{
    public function __construct(private AuditService $audit) {}

    public function onLogin(Login $event): void
    {
        $this->audit->record('auth.login', $event->user);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user !== null) {
            $this->audit->record('auth.logout', $event->user);
        }
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

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
        ];
    }
}
