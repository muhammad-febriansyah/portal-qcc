<?php

namespace App\Http\Controllers;

use App\Support\NotificationKinds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['all', 'unread'], true) ? $status : 'all';
        $kind = $request->string('kind')->toString();
        $availableKinds = collect(NotificationKinds::groups())->pluck('value')->all();
        $kind = in_array($kind, $availableKinds, true) ? $kind : 'all';

        $query = $request->user()->notifications()->getQuery();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        }

        if ($kind !== 'all') {
            NotificationKinds::applyGroupFilter($query, $kind);
        }

        $notifications = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => self::format($notification, includeUrl: true));

        return Inertia::render('notifications/index', [
            'items' => $notifications,
            'filters' => ['status' => $status, 'kind' => $kind],
            'groups' => NotificationKinds::groups(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        $target = $this->safeTarget($item->data['url'] ?? null, $item->data['project_id'] ?? null);

        return redirect()->to($target);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back(303);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back(303);
    }

    /**
     * @return array<string, mixed>
     */
    private static function format(DatabaseNotification $notification, bool $includeUrl): array
    {
        $decision = $notification->data['decision'] ?? null;
        $description = NotificationKinds::describe(is_string($decision) ? $decision : null);
        $projectId = $notification->data['project_id'] ?? null;

        return [
            'id' => $notification->id,
            'title' => is_string($notification->data['title'] ?? null) ? $notification->data['title'] : 'Pemberitahuan',
            'message' => is_string($notification->data['message'] ?? null) ? $notification->data['message'] : '',
            'url' => $includeUrl ? route('notifications.open', $notification) : null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'project_code' => is_numeric($projectId) ? sprintf('PRP-%04d', (int) $projectId) : null,
            ...$description,
        ];
    }

    private function safeTarget(mixed $target, mixed $projectId): string
    {
        if (is_string($target) && str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            return $target;
        }

        $parsedTarget = is_string($target) ? parse_url($target) : false;
        $applicationUrl = parse_url((string) config('app.url'));
        $scheme = is_array($parsedTarget) ? strtolower($parsedTarget['scheme'] ?? '') : '';
        $targetHost = is_array($parsedTarget) ? strtolower($parsedTarget['host'] ?? '') : '';
        $applicationHost = is_array($applicationUrl) ? strtolower($applicationUrl['host'] ?? '') : '';

        if (in_array($scheme, ['http', 'https'], true)
            && $targetHost !== ''
            && $targetHost === $applicationHost) {
            $path = $parsedTarget['path'] ?? '/';

            if (str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
                return $path
                    .(isset($parsedTarget['query']) ? '?'.$parsedTarget['query'] : '')
                    .(isset($parsedTarget['fragment']) ? '#'.$parsedTarget['fragment'] : '');
            }
        }

        return is_numeric($projectId)
            ? route('proposals.show', (int) $projectId)
            : route('dashboard');
    }
}
