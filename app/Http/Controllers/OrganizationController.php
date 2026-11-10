<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\AssignOrganizationHead;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Organization::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'sort' => ['nullable', 'in:code,name,is_active'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $organizations = Organization::query()
            ->select(['id', 'code', 'name', 'head_id', 'is_active'])
            ->with('head:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations->through(fn (Organization $organization): array => [
                'id' => $organization->id,
                'code' => $organization->code,
                'name' => $organization->name,
                'is_active' => $organization->is_active,
                'head_id' => $organization->head_id,
                'head_name' => $organization->head?->name,
            ]),
            'headOptions' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
                ->all(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = Organization::query()->create($request->validated());
        $this->prepareHead($organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisasi berhasil ditambahkan.']);

        return to_route('admin.organizations.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());
        $this->prepareHead($organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisasi berhasil diperbarui.']);

        return to_route('admin.organizations.index');
    }

    /**
     * A head gets the Mentor permissions right away, and proposals still without a mentor get
     * the head as mentor and reviewer.
     */
    private function prepareHead(Organization $organization): void
    {
        $head = $organization->head()->first();

        if ($head === null) {
            return;
        }

        AssignOrganizationHead::grantMentorRole($head);
        app(AssignOrganizationHead::class)->backfill($organization);
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        $organization->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisasi berhasil dihapus.']);

        return to_route('admin.organizations.index');
    }
}
