<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReviewAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('review-assignments.manage');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', ReviewAssignment::ACTIVE, ReviewAssignment::REVOKED])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? ReviewAssignment::ACTIVE;
        $perPage = (int) ($filters['per_page'] ?? 10);

        $assignments = ReviewAssignment::query()
            ->with(['project:id,title,batch_id', 'project.batch:id,name', 'reviewer:id,name,email', 'granter:id,name'])
            ->when($status === ReviewAssignment::ACTIVE, fn (Builder $query): Builder => $query->active())
            ->when($status === ReviewAssignment::REVOKED, fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('status', ReviewAssignment::REVOKED)
                ->orWhere(fn (Builder $expired): Builder => $expired->where('status', ReviewAssignment::ACTIVE)->whereNotNull('expires_at')->where('expires_at', '<=', now()))))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('project', fn (Builder $project): Builder => $project->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('reviewer', fn (Builder $reviewer): Builder => $reviewer->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $projects = Project::query()
            ->with('batch:id,name')
            ->whereIn('proposal_status', [
                ProposalStatus::Submitted->value,
                ProposalStatus::UnderReview->value,
                ProposalStatus::Approved->value,
                ProposalStatus::RevisionRequired->value,
            ])
            ->orderBy('title')
            ->get(['id', 'batch_id', 'title', 'proposal_status'])
            ->map(fn (Project $project): array => [
                'value' => (string) $project->id,
                'label' => sprintf('PRP-%04d · %s · %s', $project->id, $project->title, $project->batch->name),
                'status' => $project->proposal_status->value,
            ])->all();

        $reviewers = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('permissions', fn (Builder $permissions): Builder => $permissions->whereIn('name', [
                    'projects.review', 'projects.approve', 'scoring.view', 'scoring.review', 'scoring.approve',
                ]))->orWhereHas('roles.permissions', fn (Builder $permissions): Builder => $permissions->whereIn('name', [
                    'projects.review', 'projects.approve', 'scoring.view', 'scoring.review', 'scoring.approve',
                ]));
            })
            ->with(['permissions', 'roles.permissions'])
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn (User $reviewer): array => [
                'value' => (string) $reviewer->id,
                'label' => $reviewer->name.' · '.$reviewer->email,
                'scopes' => array_keys(array_filter(ReviewAssignment::SCOPES, fn (string $label, string $scope): bool => $this->canReviewScope($reviewer, $scope), ARRAY_FILTER_USE_BOTH)),
            ])
            ->filter(fn (array $reviewer): bool => $reviewer['scopes'] !== [])
            ->values()
            ->all();

        return Inertia::render('admin/reviewer-assignments/index', [
            'assignments' => $assignments->through(fn (ReviewAssignment $assignment): array => [
                'id' => $assignment->id,
                'project' => $assignment->project->title,
                'project_code' => sprintf('PRP-%04d', $assignment->project_id),
                'batch' => $assignment->project->batch->name,
                'reviewer' => $assignment->reviewer->name,
                'reviewer_email' => $assignment->reviewer->email,
                'scope' => $assignment->scope,
                'scope_label' => ReviewAssignment::SCOPES[$assignment->scope] ?? $assignment->scope,
                'status' => $this->isActive($assignment) ? ReviewAssignment::ACTIVE : ReviewAssignment::REVOKED,
                'expires_at' => $assignment->expires_at?->toIso8601String(),
                'granted_by' => $assignment->granter?->name,
                'created_at' => $assignment->created_at?->toIso8601String(),
            ]),
            'projects' => $projects,
            'reviewers' => $reviewers,
            'scopeOptions' => collect(ReviewAssignment::SCOPES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'filters' => ['search' => $search, 'status' => $status, 'per_page' => $perPage],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('review-assignments.manage');

        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'reviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'scope' => ['required', 'string', Rule::in(array_keys(ReviewAssignment::SCOPES))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        $project = Project::query()->whereKey((int) $data['project_id'])->firstOrFail();
        $reviewer = User::query()->with(['permissions', 'roles.permissions'])->whereKey((int) $data['reviewer_id'])->firstOrFail();

        if (! $this->canReviewScope($reviewer, $data['scope'])) {
            throw ValidationException::withMessages(['reviewer_id' => 'Reviewer belum memiliki permission untuk scope ini.']);
        }

        if ($data['scope'] === 'proposal'
            && ! in_array($project->proposal_status, [ProposalStatus::Submitted, ProposalStatus::UnderReview, ProposalStatus::RevisionRequired], true)) {
            throw ValidationException::withMessages(['project_id' => 'Review proposal hanya dapat ditugaskan selama proposal menunggu keputusan.']);
        }

        if (in_array($data['scope'], ['steps', 'biweekly', 'scoring'], true) && $project->proposal_status !== ProposalStatus::Approved) {
            throw ValidationException::withMessages(['project_id' => 'Scope ini hanya dapat ditugaskan pada proyek yang sudah disetujui.']);
        }

        DB::transaction(function () use ($data, $request): void {
            $assignment = ReviewAssignment::query()
                ->where('project_id', $data['project_id'])
                ->where('reviewer_id', $data['reviewer_id'])
                ->where('scope', $data['scope'])
                ->lockForUpdate()
                ->first();

            if ($assignment !== null && $this->isActive($assignment)) {
                throw ValidationException::withMessages(['reviewer_id' => 'Reviewer sudah aktif pada scope dan proyek ini.']);
            }

            $attributes = [
                'granted_by' => $request->user()->id,
                'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
                'status' => ReviewAssignment::ACTIVE,
            ];

            if ($assignment === null) {
                ReviewAssignment::query()->create($attributes + [
                    'project_id' => $data['project_id'],
                    'reviewer_id' => $data['reviewer_id'],
                    'scope' => $data['scope'],
                ]);

                return;
            }

            $assignment->update($attributes);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reviewer berhasil ditugaskan.']);

        return to_route('admin.reviewer-assignments.index');
    }

    public function revoke(Request $request, ReviewAssignment $reviewAssignment): RedirectResponse
    {
        Gate::authorize('review-assignments.manage');

        if ($this->isActive($reviewAssignment)) {
            $reviewAssignment->update(['status' => ReviewAssignment::REVOKED]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan reviewer dicabut.']);

        return back();
    }

    private function canReviewScope(User $user, string $scope): bool
    {
        if (! $user->is_active || ! $user->can('projects.view') || $user->can('projects.viewAny')) {
            return false;
        }

        return match ($scope) {
            'proposal', 'steps' => $user->can('projects.review') || $user->can('projects.approve'),
            'biweekly' => $user->can('projects.review'),
            'scoring' => $user->can('scoring.view') && ($user->can('scoring.review') || $user->can('scoring.approve')),
            default => false,
        };
    }

    private function isActive(ReviewAssignment $assignment): bool
    {
        return $assignment->status === ReviewAssignment::ACTIVE
            && ($assignment->expires_at === null || $assignment->expires_at->isFuture());
    }
}
