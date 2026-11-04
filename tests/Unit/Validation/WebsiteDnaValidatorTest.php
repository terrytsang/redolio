<?php

namespace Tests\Unit\Validation;

use App\Services\MetaApi\Validation\WebsiteDnaValidator;
use PHPUnit\Framework\TestCase;

class WebsiteDnaValidatorTest extends TestCase
{
    public function test_drops_observed_facts_with_no_valid_evidence(): void
    {
        $validator = new WebsiteDnaValidator;

        $result = $validator->validate([
            'observed_facts' => [
                ['claim' => 'Unsupported claim', 'evidence_ids' => ['nonexistent']],
                ['claim' => 'Supported claim', 'evidence_ids' => ['p0-title']],
            ],
        ], ['p0-title']);

        $this->assertCount(1, $result['observed_facts']);
        $this->assertSame('Supported claim', $result['observed_facts'][0]['claim']);
    }

    public function test_inferences_may_have_no_evidence_but_are_still_labelled(): void
    {
        $validator = new WebsiteDnaValidator;

        $result = $validator->validate([
            'inferences' => [
                ['claim' => 'Speculative inference', 'confidence' => 'low', 'evidence_ids' => []],
            ],
        ], []);

        $this->assertCount(1, $result['inferences']);
        $this->assertSame('Speculative inference', $result['inferences'][0]['claim']);
    }

    public function test_value_proposition_text_is_nulled_without_evidence(): void
    {
        $validator = new WebsiteDnaValidator;

        $result = $validator->validate([
            'value_proposition' => ['text' => 'We are the best', 'evidence_ids' => ['missing']],
        ], ['p0-title']);

        $this->assertNull($result['value_proposition']['text']);
    }

    public function test_unknown_conversion_goal_defaults_to_unknown(): void
    {
        $validator = new WebsiteDnaValidator;

        $result = $validator->validate(['primary_conversion_goal' => 'world_domination'], []);

        $this->assertSame('unknown', $result['primary_conversion_goal']);
    }
}
