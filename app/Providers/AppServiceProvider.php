<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use App\Services\AuditService;
use App\Services\Scoring\Contracts\EvidenceResolver;
use App\Services\Scoring\Contracts\ScoringModelClient;
use App\Services\Scoring\NullEvidenceResolver;
use App\Services\Scoring\OpenAiScoringClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditService::class);
        $this->app->bind(ScoringModelClient::class, OpenAiScoringClient::class);
        $this->app->bind(EvidenceResolver::class, NullEvidenceResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::subscribe(RecordAuthActivity::class);
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
