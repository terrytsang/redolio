<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DesignIntelligenceController;
use App\Http\Controllers\Admin\ScanController as AdminScanController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\Api\DesignCatalogueController;
use App\Http\Controllers\Api\HeroController;
use App\Http\Controllers\Api\PageBlueprintController;
use App\Http\Controllers\Api\ProjectDesignController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlueprintPreviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DesignDirectionController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\DirectionGalleryController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExportPassController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LaunchController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NextRedoController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanSelectionController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\RevealController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SitePublicController;
use App\Http\Controllers\SiteWatchController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UpgradeController;
use App\Http\Controllers\WebsiteDnaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

// Information pages (the homepage footer links)
Route::name('pages.')->group(function () {
    Route::get('/about', [\App\Http\Controllers\PageController::class, 'about'])->name('about');
    Route::get('/built-at-meta-hackathon', [\App\Http\Controllers\PageController::class, 'hackathon'])->name('hackathon');
    Route::get('/changelog', [\App\Http\Controllers\PageController::class, 'changelog'])->name('changelog');
    Route::get('/roadmap', [\App\Http\Controllers\PageController::class, 'roadmap'])->name('roadmap');
    Route::get('/docs', [\App\Http\Controllers\PageController::class, 'docs'])->name('docs');
    Route::get('/security', [\App\Http\Controllers\PageController::class, 'security'])->name('security');
    Route::get('/evidence-policy', [\App\Http\Controllers\PageController::class, 'evidence'])->name('evidence');
    Route::get('/privacy', [\App\Http\Controllers\PageController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [\App\Http\Controllers\PageController::class, 'terms'])->name('terms');
    Route::get('/contact', [\App\Http\Controllers\PageController::class, 'contact'])->name('contact');
    Route::get('/status', [\App\Http\Controllers\PageController::class, 'status'])->name('status');
    Route::get('/status.json', [\App\Http\Controllers\PageController::class, 'statusJson'])->name('status.json');
});

Route::prefix('setup')->name('setup.')->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('index');
    Route::post('/api-key', [SetupController::class, 'saveApiKey'])->name('api-key');
    Route::post('/admin', [SetupController::class, 'createAdmin'])->name('admin');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/design-categories', [DesignCatalogueController::class, 'categories'])->name('design-categories');
    Route::get('/design-directions', [DesignCatalogueController::class, 'directions'])->name('design-directions');
    Route::get('/design-directions/{id}', [DesignCatalogueController::class, 'direction'])->name('design-directions.show');
    Route::get('/page-blueprints', [PageBlueprintController::class, 'index'])->name('page-blueprints');
    Route::get('/page-blueprints/{id}', [PageBlueprintController::class, 'show'])->name('page-blueprints.show');
});

Route::post('/api/projects/{analysisRun:public_id}/design-recommendations', [ProjectDesignController::class, 'recommendations'])
    ->middleware('throttle:20,1')->name('api.projects.design-recommendations');
Route::post('/api/projects/{analysisRun:public_id}/design-selection', [ProjectDesignController::class, 'selection'])
    ->middleware('throttle:20,1')->name('api.projects.design-selection');

// v1.6 Positioning Intelligence & Hero Engine (signed-in owners; model/browser steps are queued and polled).
Route::prefix('/api/projects/{analysisRun:public_id}')->middleware('auth')->name('api.hero.')->group(function () {
    $c = HeroController::class;
    Route::post('/positioning/analyse', [$c, 'analysePositioning'])->middleware('throttle:6,1')->name('positioning.analyse');
    Route::get('/positioning', [$c, 'positioning'])->name('positioning');
    Route::patch('/positioning', [$c, 'correct'])->middleware('throttle:60,1')->name('positioning.correct');
    Route::post('/hero/diagnose', [$c, 'diagnose'])->middleware('throttle:30,1')->name('diagnose');
    Route::post('/hero/generate-options', [$c, 'generateOptions'])->middleware('throttle:6,1')->name('generate');
    Route::get('/hero/options', [$c, 'options'])->name('options');
    Route::get('/hero/jobs/{generation}', [$c, 'job'])->name('job');
    Route::post('/hero/options/{option}/approve', [$c, 'approve'])->middleware('throttle:30,1')->name('approve');
    Route::post('/hero/options/{option}/reject', [$c, 'reject'])->middleware('throttle:30,1')->name('reject');
    Route::get('/hero/versions', [$c, 'versions'])->name('versions');
    Route::post('/hero/validate-render', [$c, 'validateRender'])->middleware('throttle:6,1')->name('validate-render');
    Route::get('/hero/render-status', [$c, 'renderStatus'])->name('render-status');
    Route::post('/hero/accept-layout-exception', [$c, 'acceptLayoutException'])->middleware('throttle:20,1')->name('accept-exception');
});

Route::get('/blueprints/{blueprint}/preview', [BlueprintPreviewController::class, 'show'])->name('blueprints.preview');

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');

Route::get('/directions', [DirectionGalleryController::class, 'index'])->name('directions.index');
Route::post('/directions/{direction:direction_key}/remix', [DirectionGalleryController::class, 'remix'])
    ->middleware('throttle:20,1')
    ->name('directions.remix');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::post('/auth/google/token', [GoogleAuthController::class, 'token'])->middleware('throttle:20,1')->name('auth.google.token');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
    Route::get('/account/plan', [PlanController::class, 'show'])->name('plan.show');

    Route::post('/site-watches', [SiteWatchController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('site-watches.store');
    Route::patch('/site-watches/{siteWatch}', [SiteWatchController::class, 'update'])->middleware('throttle:20,1')->name('site-watches.update');
    Route::post('/site-watches/{siteWatch}/scan', [SiteWatchController::class, 'scanNow'])->middleware('throttle:6,1')->name('site-watches.scan');
    Route::post('/site-watches/{siteWatch}/toggle', [SiteWatchController::class, 'toggle'])->name('site-watches.toggle');
    Route::delete('/site-watches/{siteWatch}', [SiteWatchController::class, 'destroy'])->name('site-watches.destroy');

    Route::post('/plans/{plan}/select', [PlanSelectionController::class, 'store'])->middleware('throttle:10,1')->name('plans.select');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:10,1')->name('billing.portal');
    Route::get('/analyses/{analysisRun:public_id}/upgrade', [UpgradeController::class, 'show'])->name('analyses.upgrade');
    Route::post('/export-pass/purchase', [ExportPassController::class, 'store'])->middleware('throttle:10,1')->name('export-pass.purchase');
    Route::get('/export-pass/success', [ExportPassController::class, 'success'])->name('export-pass.success');

    Route::delete('/analyses/{analysisRun:public_id}', [AnalysisController::class, 'destroy'])->middleware('throttle:20,1')->name('analyses.destroy');
    Route::post('/analyses/{analysisRun:public_id}/make-editable', [DesignDirectionController::class, 'makeEditable'])
        ->middleware('throttle:10,1')
        ->name('analyses.make-editable');
    Route::get('/analyses/{analysisRun:public_id}/express', [DesignDirectionController::class, 'express'])->middleware('throttle:20,1')->name('analyses.express');
    Route::get('/analyses/{analysisRun:public_id}/launch', [LaunchController::class, 'show'])->name('analyses.launch');
    Route::post('/analyses/{analysisRun:public_id}/approve-design', [LaunchController::class, 'approve'])->name('analyses.approve-design');

    // In-place editing (free for any signed-in owner; publishing and export stay on paid plans).
    Route::get('/analyses/{analysisRun:public_id}/edit', [EditorController::class, 'show'])->name('analyses.edit');
    Route::get('/analyses/{analysisRun:public_id}/edit/frame', [EditorController::class, 'frame'])->name('analyses.edit.frame');
    Route::post('/analyses/{analysisRun:public_id}/edit', [EditorController::class, 'apply'])->middleware('throttle:120,1')->name('analyses.edit.apply');
    Route::post('/analyses/{analysisRun:public_id}/edit/save', [EditorController::class, 'save'])->middleware('throttle:60,1')->name('analyses.edit.save');
    Route::post('/analyses/{analysisRun:public_id}/edit/restore/{version}', [EditorController::class, 'restore'])->whereNumber('version')->middleware('throttle:30,1')->name('analyses.edit.restore');
    Route::get('/analyses/{analysisRun:public_id}/edit/unsplash', [EditorController::class, 'unsplashSearch'])->middleware('throttle:40,1')->name('analyses.edit.unsplash');
    Route::get('/analyses/{analysisRun:public_id}/edit/ai-image', [EditorController::class, 'aiImageStatus'])->name('analyses.edit.ai-image');
    Route::post('/analyses/{analysisRun:public_id}/edit/ai-image', [EditorController::class, 'aiImageGenerate'])->middleware('throttle:6,1')->name('analyses.edit.ai-image.generate');
    Route::post('/analyses/{analysisRun:public_id}/edit/unsplash', [EditorController::class, 'unsplashUse'])->middleware('throttle:20,1')->name('analyses.edit.unsplash.use');
    Route::post('/analyses/{analysisRun:public_id}/edit/upload', [EditorController::class, 'upload'])->middleware('throttle:20,1')->name('analyses.edit.upload');

    Route::get('/analyses/{analysisRun:public_id}/publish', [PublishController::class, 'show'])->name('analyses.publish');
    Route::post('/analyses/{analysisRun:public_id}/publish', [PublishController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('analyses.publish.store');
    Route::post('/analyses/{analysisRun:public_id}/deployments/{deployment}/rollback', [PublishController::class, 'rollback'])
        ->middleware('throttle:10,1')
        ->name('analyses.deployments.rollback');

    Route::post('/analyses/{analysisRun:public_id}/exports', [ExportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('analyses.exports.store');
});

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/exports/{token}/ready', [ExportController::class, 'ready'])->name('exports.ready');
Route::get('/exports/{token}/download', [ExportController::class, 'download'])->name('exports.download');

Route::get('/sites/{slug}/{path?}', [SitePublicController::class, 'show'])
    ->where('path', '.*')
    ->name('sites.show');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');

    Route::get('/scans', [AdminScanController::class, 'index'])->name('scans.index');
    Route::get('/scans/{analysisRun:public_id}', [AdminScanController::class, 'show'])->name('scans.show');

    Route::get('/design-directions', [DesignIntelligenceController::class, 'index'])->name('design-directions.index');
    Route::get('/design-directions/create', [DesignIntelligenceController::class, 'create'])->name('design-directions.create');
    Route::post('/design-directions', [DesignIntelligenceController::class, 'store'])->name('design-directions.store');
    Route::post('/design-directions/generate', [DesignIntelligenceController::class, 'generateFromUrl'])
        ->middleware('throttle:10,1')
        ->name('design-directions.generate');
    Route::post('/design-directions/import-design-md', [DesignIntelligenceController::class, 'importDesignMd'])
        ->middleware('throttle:10,1')
        ->name('design-directions.import-design-md');
    Route::get('/design-directions/generating/{generation}', [DesignIntelligenceController::class, 'generating'])->name('design-directions.generating');
    Route::get('/design-directions/{designDirection}/edit', [DesignIntelligenceController::class, 'edit'])->name('design-directions.edit');
    Route::put('/design-directions/{designDirection}', [DesignIntelligenceController::class, 'update'])->name('design-directions.update');
    Route::post('/design-directions/{designDirection}/toggle-selectable', [DesignIntelligenceController::class, 'toggleSelectable'])->name('design-directions.toggle-selectable');
});

Route::post('/analyses', [AnalysisController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('analyses.store');

Route::get('/analyses/{analysisRun:public_id}', [AnalysisController::class, 'show'])->name('analyses.show');
Route::get('/analyses/{analysisRun:public_id}/progress', [AnalysisController::class, 'progress'])->name('analyses.progress');
Route::post('/analyses/{analysisRun:public_id}/notify', [AnalysisController::class, 'notify'])->middleware('throttle:6,1')->name('analyses.notify');
Route::post('/analyses/{analysisRun:public_id}/retry', [AnalysisController::class, 'retry'])
    ->middleware('throttle:10,1')
    ->name('analyses.retry');
Route::post('/analyses/{analysisRun:public_id}/redo', [AnalysisController::class, 'redo'])
    ->middleware('throttle:10,1')
    ->name('analyses.redo');

Route::get('/analyses/{analysisRun:public_id}/website-dna', [WebsiteDnaController::class, 'show'])->name('analyses.website-dna');
Route::patch('/analyses/{analysisRun:public_id}/website-dna', [WebsiteDnaController::class, 'update'])->name('analyses.website-dna.update');

Route::get('/analyses/{analysisRun:public_id}/diagnosis', [DiagnosisController::class, 'show'])->name('analyses.diagnosis');

Route::get('/analyses/{analysisRun:public_id}/design-directions', [DesignDirectionController::class, 'show'])->name('analyses.design-directions');
Route::get('/analyses/{analysisRun:public_id}/design-directions/layout', [DesignDirectionController::class, 'blueprints'])->name('analyses.design-directions.layout');
Route::post('/analyses/{analysisRun:public_id}/design-directions', [DesignDirectionController::class, 'select'])
    ->middleware('throttle:10,1')
    ->name('analyses.design-directions.select');
Route::get('/analyses/{analysisRun:public_id}/design-directions/generating/{designSelection}', [DesignDirectionController::class, 'generating'])
    ->name('analyses.design-directions.generating');

Route::get('/analyses/{analysisRun:public_id}/reveal', [RevealController::class, 'show'])->name('analyses.reveal');
Route::get('/analyses/{analysisRun:public_id}/preview', [PreviewController::class, 'current'])->name('analyses.preview');
Route::get('/analyses/{analysisRun:public_id}/preview/{version}', [PreviewController::class, 'version'])->name('analyses.preview.version');
Route::post('/analyses/{analysisRun:public_id}/preview/switch-direction', [PreviewController::class, 'switchDirection'])
    ->middleware('throttle:10,1')
    ->name('analyses.preview.switch-direction');
Route::post('/analyses/{analysisRun:public_id}/preview/{version}/visual-qa', [PreviewController::class, 'runVisualQa'])
    ->middleware('throttle:10,1')
    ->name('analyses.preview.visual-qa.run');
Route::get('/analyses/{analysisRun:public_id}/visual-qa/{report}', [PreviewController::class, 'visualQaStatus'])
    ->name('analyses.preview.visual-qa.status');
Route::post('/analyses/{analysisRun:public_id}/visual-qa/{report}/refine', [PreviewController::class, 'refineFromVisualQa'])
    ->middleware('throttle:10,1')
    ->name('analyses.preview.visual-qa.refine');

Route::post('/analyses/{analysisRun:public_id}/leads', [LeadController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('analyses.leads.store');

Route::get('/analyses/{analysisRun:public_id}/evidence/{evidenceId}', [EvidenceController::class, 'show'])->name('analyses.evidence');

Route::get('/analyses/{analysisRun:public_id}/next-redo', [NextRedoController::class, 'show'])->name('analyses.next-redo');
Route::post('/analyses/{analysisRun:public_id}/next-redo', [NextRedoController::class, 'generate'])
    ->middleware('throttle:10,1')
    ->name('analyses.next-redo.generate');

Route::get('/demo/{demoFixture:fixture_key}', [DemoController::class, 'show'])->name('demo.show');

Route::post('/events', [EventController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('events.store');
