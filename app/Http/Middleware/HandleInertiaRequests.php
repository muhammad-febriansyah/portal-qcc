<?php

namespace App\Http\Middleware;

use App\Support\NotificationKinds;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => [
                'name' => config('app.name'),
                'logoUrl' => config('app.portal_logo_url'),
                'faviconUrl' => config('app.portal_favicon_url'),
                'keywords' => config('app.portal_keywords'),
                'description' => config('app.portal_description'),
            ],
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames()->all() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->all() ?? [],
            ],
            'notifications' => [
                'unread_count' => $request->user()?->unreadNotifications()->count() ?? 0,
                'recent' => $request->user()?->notifications()->latest()->limit(5)->get()->map(function ($notification): array {
                    $decision = $notification->data['decision'] ?? null;
                    $projectId = $notification->data['project_id'] ?? null;

                    return [
                        'id' => $notification->id,
                        'title' => is_string($notification->data['title'] ?? null) ? $notification->data['title'] : 'Pemberitahuan',
                        'message' => is_string($notification->data['message'] ?? null) ? $notification->data['message'] : '',
                        'url' => route('notifications.open', $notification),
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                        'project_code' => is_numeric($projectId) ? sprintf('PRP-%04d', (int) $projectId) : null,
                        ...NotificationKinds::describe(is_string($decision) ? $decision : null),
                    ];
                })->all() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
