<?php

namespace App\Services\Images;

use App\Models\User;
use Illuminate\Support\Carbon;

/** How many AI images a person may still make: Free gets 2 in total, Starter and Agency get 5 every calendar month. */
class AiImageQuota
{
    /** @return array{limit: ?int, used: int, remaining: ?int, period: string, resets_at: ?string, plan: string, can_upgrade: bool} */
    public function for(User $user): array
    {
        $plan = $user->currentPlan();
        $limit = config('redo.edition') === 'selfhosted' ? null : ($plan ? $plan->ai_image_limit : 2);
        $period = $plan?->ai_image_period ?: 'total';
        $q = $user->aiImageGenerations();
        $period === 'month' && $q->where('created_at', '>=', now()->startOfMonth());
        $used = $q->count();

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'period' => $period,
            'resets_at' => $period === 'month' ? now()->startOfMonth()->addMonth()->toDateString() : null,
            'plan' => $plan?->name ?? 'Free',
            'can_upgrade' => ($plan?->plan_key ?? 'free') === 'free',
        ];
    }
}
