<?php

namespace Tests\Unit;

use App\Services\MetaApi\Validation\DesignDirectionRankingValidator;
use PHPUnit\Framework\TestCase;

class DesignDirectionRankingValidatorTest extends TestCase
{
    private DesignDirectionRankingValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DesignDirectionRankingValidator;
    }

    private function eligible(): array
    {
        return ['editorial', 'minimal', 'modern_corporate', 'creative_studio', 'premium_luxury', 'commerce_energetic'];
    }

    public function test_marks_up_to_three_recommended_directions(): void
    {
        $data = [
            'recommendations' => array_map(fn ($key, $i) => [
                'direction_key' => $key, 'rank' => $i + 1, 'score' => 1 - ($i * 0.1), 'rationale' => "why {$key}",
            ], $this->eligible(), array_keys($this->eligible())),
            'recommended_direction_keys' => ['minimal', 'editorial', 'creative_studio'],
        ];

        $out = $this->validator->validate($data, $this->eligible());
        $recommended = array_filter($out, fn ($e) => $e['is_recommended']);

        $this->assertCount(3, $recommended);
        $this->assertEqualsCanonicalizing(
            ['minimal', 'editorial', 'creative_studio'],
            array_column($recommended, 'direction_key'),
        );
    }

    public function test_ignores_ineligible_recommended_keys_and_caps_at_three(): void
    {
        $data = [
            'recommendations' => array_map(fn ($key, $i) => [
                'direction_key' => $key, 'rank' => $i + 1, 'score' => 1, 'rationale' => 'x',
            ], $this->eligible(), array_keys($this->eligible())),
            'recommended_direction_keys' => ['minimal', 'not-a-real-direction', 'editorial', 'creative_studio', 'premium_luxury'],
        ];

        $out = $this->validator->validate($data, $this->eligible());
        $recommended = array_filter($out, fn ($e) => $e['is_recommended']);

        $this->assertCount(3, $recommended);
    }

    public function test_falls_back_to_top_three_ranked_when_no_recommendation_given(): void
    {
        $data = [
            'recommendations' => array_map(fn ($key, $i) => [
                'direction_key' => $key, 'rank' => $i + 1, 'score' => 1, 'rationale' => 'x',
            ], $this->eligible(), array_keys($this->eligible())),
            'recommended_direction_keys' => [],
        ];

        $out = $this->validator->validate($data, $this->eligible());
        $recommended = array_filter($out, fn ($e) => $e['is_recommended']);

        $this->assertCount(3, $recommended);
        $this->assertEqualsCanonicalizing(
            ['editorial', 'minimal', 'modern_corporate'],
            array_column($recommended, 'direction_key'),
        );
    }

    public function test_never_invents_a_direction_outside_the_eligible_set(): void
    {
        $data = [
            'recommendations' => [
                ['direction_key' => 'editorial', 'rank' => 1, 'score' => 1, 'rationale' => 'x'],
                ['direction_key' => 'made-up-direction', 'rank' => 2, 'score' => 1, 'rationale' => 'x'],
            ],
            'recommended_direction_keys' => ['made-up-direction'],
        ];

        $out = $this->validator->validate($data, $this->eligible());
        $keys = array_column($out, 'direction_key');

        $this->assertNotContains('made-up-direction', $keys);
        $this->assertContains('editorial', $keys);
        $this->assertCount(count($this->eligible()), $out);
    }
}
