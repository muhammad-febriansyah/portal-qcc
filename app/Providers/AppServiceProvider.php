<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use App\Services\AuditService;
use App\Services\Scoring\Contracts\EvidenceResolver;
use App\Services\Scoring\Contracts\ScoringModelClient;
use App\Services\Scoring\OpenAiScoringClient;
use App\Services\Scoring\ProjectEvidenceResolver;
use App\Services\Teams\Matching\OpenAiTeamMatchingClient;
use App\Services\Teams\Matching\TeamMatchingClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditService::class);
        $this->app->bind(ScoringModelClient::class, OpenAiScoringClient::class);
        $this->app->bind(TeamMatchingClient::class, OpenAiTeamMatchingClient::class);
        $this->app->bind(EvidenceResolver::class, ProjectEvidenceResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::subscribe(RecordAuthActivity::class);

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            if (in_array($response->statusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return $response->render('errors/show', [
                    'status' => $response->statusCode(),
                ])->withSharedData();
            }

            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
