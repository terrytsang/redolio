<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AnalysisRun extends Model
{
    /**
     * Every URL that carries an analysis goes through here, so one rule protects them all: an analysis that belongs to someone can
     * only be opened by its owner or an admin. A visitor's own scan made before signing up has no owner yet and stays reachable by
     * its private link (that is how the sign-up flow claims it). Anyone else gets a "not found", which doesn't reveal it exists.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $run = parent::resolveRouteBinding($value, $field);
        if ($run === null || $run->user_id === null) {
            return $run;
        }
        $user = auth()->user();
        if ($user === null) {
            throw new AuthenticationException('Please sign in to open this analysis.');
        }
        if ($user->id === $run->user_id || $user->isAdmin()) {
            return $run;
        }

        throw new NotFoundHttpException;
    }

    use HasFactory;

    protected $fillable = [
        'user_id', 'public_id', 'submitted_url', 'normalized_url', 'idempotency_key',
        'status', 'current_stage', 'source_mode', 'error_code', 'safe_error_message',
        'is_watch_scan', 'purpose', 'started_at', 'completed_at', 'duration_ms', 'active_deployment_id', 'published_slug', 'notify_email', 'notified_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'notified_at' => 'datetime',
        'is_watch_scan' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalysisRun $run) {
            $run->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function siteSnapshot(): HasOne
    {
        return $this->hasOne(SiteSnapshot::class);
    }

    public function websiteDnaVersions(): HasMany
    {
        return $this->hasMany(WebsiteDna::class);
    }

    public function currentWebsiteDna(): ?WebsiteDna
    {
        return $this->websiteDnaVersions()->orderByDesc('version')->first();
    }

    public function diagnosisReports(): HasMany
    {
        return $this->hasMany(DiagnosisReport::class);
    }

    public function currentDiagnosisReport(): ?DiagnosisReport
    {
        return $this->diagnosisReports()->orderByDesc('version')->first();
    }

    public function designSpecs(): HasMany
    {
        return $this->hasMany(DesignSpec::class);
    }

    public function currentDesignSpec(): ?DesignSpec
    {
        return $this->designSpecs()->orderByDesc('version')->first();
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(AnalysisStageLog::class);
    }

    public function modelCallLogs(): HasMany
    {
        return $this->hasMany(ModelCallLog::class);
    }

    public function nextRedoRecommendations(): HasMany
    {
        return $this->hasMany(NextRedoRecommendation::class);
    }

    public function currentNextRedoRecommendation(): ?NextRedoRecommendation
    {
        return $this->nextRedoRecommendations()->orderByDesc('created_at')->first();
    }

    public function interactionEvents(): HasMany
    {
        return $this->hasMany(InteractionEvent::class);
    }

    public function websiteAssets(): HasMany
    {
        return $this->hasMany(WebsiteAsset::class);
    }

    public function positioningProfiles(): HasMany
    {
        return $this->hasMany(PositioningProfile::class);
    }

    public function currentPositioningProfile(): ?PositioningProfile
    {
        return $this->positioningProfiles()->orderByDesc('version')->first();
    }

    public function heroCopyOptions(): HasMany
    {
        return $this->hasMany(HeroCopyOption::class);
    }

    public function designProfiles(): HasMany
    {
        return $this->hasMany(WebsiteDesignProfile::class);
    }

    public function currentDesignProfile(): ?WebsiteDesignProfile
    {
        return $this->designProfiles()->orderByDesc('created_at')->first();
    }

    public function designDirectionRecommendations(): HasMany
    {
        return $this->hasMany(DesignDirectionRecommendation::class);
    }

    public function designSelections(): HasMany
    {
        return $this->hasMany(DesignSelection::class);
    }

    public function currentDesignSelection(): ?DesignSelection
    {
        return $this->designSelections()->orderByDesc('selected_at')->first();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function approvedDesigns(): HasMany
    {
        return $this->hasMany(ApprovedDesign::class);
    }

    public function currentApprovedDesign(): ?ApprovedDesign
    {
        return $this->approvedDesigns()->orderByDesc('approved_at')->first();
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function activeDeployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class, 'active_deployment_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }
}
