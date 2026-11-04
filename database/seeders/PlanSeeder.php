<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'plan_key' => 'free',
                'name' => 'Free',
                'tagline' => 'Try REDOLIO on one website.',
                'price_monthly_cents' => 0,
                'site_limit' => 1,
                'analysis_limit' => 2,
                'ai_image_limit' => 2,
                'ai_image_period' => 'total',
                'auto_rescan_frequency_days' => null,
                'features_json' => [
                    '1 website',
                    '2 website analyses',
                    'Manual analysis on demand',
                    'Website DNA, diagnosis & one redesign preview',
                    'All 5 design directions',
                    '2 AI-generated images (Meta Muse Image)',
                ],
                'sort_order' => 1,
                'publishing_enabled' => false,
                'static_export_enabled' => false,
            ],
            [
                'plan_key' => 'starter',
                'name' => 'Starter',
                'tagline' => 'For business owners who want their site to keep improving.',
                'price_monthly_cents' => 2900,
                'site_limit' => 3,
                'analysis_limit' => 6,
                'ai_image_limit' => 5,
                'ai_image_period' => 'month',
                'auto_rescan_frequency_days' => 30,
                'features_json' => [
                    '3 websites watched',
                    '6 active website analyses',
                    'Publish on a REDOLIO web address',
                    'Static website code export',
                    'Watch reports — weekly or monthly rescans of your site',
                    '5 AI-generated images every month',
                    'Full version history',
                    'Next REDO improvement hypotheses',
                ],
                'sort_order' => 2,
                'publishing_enabled' => true,
                'static_export_enabled' => true,
            ],
            [
                'plan_key' => 'agency',
                'name' => 'Agency',
                'tagline' => 'For consultants and small agencies managing client sites.',
                'price_monthly_cents' => 9900,
                'site_limit' => 10,
                'analysis_limit' => 15,
                'ai_image_limit' => 5,
                'ai_image_period' => 'month',
                'auto_rescan_frequency_days' => 7,
                'features_json' => [
                    '10 websites watched',
                    '15 active website analyses',
                    'Publish on a REDOLIO web address',
                    'Static website code export',
                    'Watch reports — weekly or monthly rescans of your sites',
                    '5 AI-generated images every month',
                    'Priority redesign generation',
                    'Client-ready before/after previews',
                ],
                'sort_order' => 3,
                'publishing_enabled' => true,
                'static_export_enabled' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['plan_key' => $plan['plan_key']], $plan);
        }
    }
}
