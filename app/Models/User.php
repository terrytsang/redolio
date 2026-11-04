<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'company_name', 'role', 'is_active', 'google_id', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(AnalysisRun::class);
    }

    public function siteWatches(): HasMany
    {
        return $this->hasMany(SiteWatch::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(\App\Models\Transaction::class);
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->where('status', 'active')->latest('started_at')->first();
    }

    public function currentPlan(): ?Plan
    {
        return $this->currentSubscription()?->plan;
    }

    /** Website analyses counted against the plan (a scan that failed doesn't use up a place). */
    public function analysesUsed(): int
    {
        return $this->analysisRuns()->where('is_watch_scan', false)->where('status', '!=', 'failed')->count();
    }

    public function analysisLimit(): ?int
    {
        return $this->currentPlan()?->analysis_limit;
    }

    public function aiImageGenerations(): HasMany
    {
        return $this->hasMany(\App\Models\AiImageGeneration::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function hasLeadGenAccess(): bool
    {
        if (config('redo.edition') !== 'saas') {
            return false;
        }

        return in_array($this->currentPlan()?->plan_key, ['starter', 'agency'], true);
    }

    public function oneTimePurchases(): HasMany
    {
        return $this->hasMany(OneTimePurchase::class);
    }

    public function canPublish(): bool
    {
        if (config('redo.edition') === 'selfhosted') {
            return true;
        }

        return (bool) $this->currentPlan()?->publishing_enabled;
    }

    public function canExportStatic(): bool
    {
        if (config('redo.edition') === 'selfhosted') {
            return true;
        }

        return (bool) $this->currentPlan()?->static_export_enabled || $this->availableExportPassCredits() > 0;
    }

    public function availableExportPassCredits(): int
    {
        return (int) $this->oneTimePurchases()
            ->where('purchase_type', 'export_pass')
            ->where('status', 'paid')
            ->sum('exports_remaining');
    }
}
