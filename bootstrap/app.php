<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RecordPageView;
use App\Http\Middleware\RequireSetupWizard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->web(prepend: [
            RequireSetupWizard::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            RecordPageView::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Self-evolving design taste. Only actually fires if the host's
        // system cron runs `php artisan schedule:run` every minute —
        // standard Laravel requirement; see FR-010/operational notes on
        // Opalstack cron availability being unverified.
        $schedule->command('design:scout-trends')->weekly()->sundays()->at('03:00');

        // Self-evolving websites: rescans any enabled site_watch whose
        // next_scan_at has passed. Runs hourly so daily/weekly cadences
        // set per-plan (see PlanSeeder) land close to on time.
        $schedule->command('redo:run-site-watches')->hourly();

        // Retention: expired QA screenshots, abandoned temp builds, expired exports (docs/v1.5-runbook.md).
        $schedule->command('redo:prune')->dailyAt('03:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
