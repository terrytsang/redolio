<?php

namespace Tests\Feature;

use App\Services\Orchestration\GenerateDesignSpecAction;
use ReflectionMethod;
use Tests\TestCase;

/** The business's own evidence-backed positioning statement must survive the redesign. */
class PositioningGuardTest extends TestCase
{
    private function guard(array $validated, array $dna): array
    {
        $m = new ReflectionMethod(GenerateDesignSpecAction::class, 'keepOriginalPositioning');

        return $m->invoke(app(GenerateDesignSpecAction::class), $validated, $dna);
    }

    private function hero(array $over = []): array
    {
        return ['hero' => array_merge(['eyebrow' => null, 'headline' => 'Grow with a website that works', 'subheadline' => 'Clear, confident design.'], $over)];
    }

    private function dna(?string $text): array
    {
        return ['value_proposition' => ['text' => $text, 'evidence_ids' => ['e1'], 'confidence' => 'high']];
    }

    public function test_a_hero_that_dropped_the_original_positioning_gets_it_back_verbatim_as_the_eyebrow(): void
    {
        $out = $this->guard($this->hero(), $this->dna('We Specialize In The Supply Of Raw Materials'));
        $this->assertSame('We Specialize In The Supply Of Raw Materials', $out['hero']['eyebrow']);
        $this->assertSame('Grow with a website that works', $out['hero']['headline'], 'the rest of the hero is untouched');
    }

    public function test_a_hero_that_already_carries_the_positioning_is_left_alone(): void
    {
        $hero = $this->hero(['headline' => 'Raw materials supply you can rely on', 'subheadline' => 'We specialize in aluminium and engineering plastics.']);
        $this->assertNull($this->guard($hero, $this->dna('We Specialize In The Supply Of Raw Materials'))['hero']['eyebrow']);
    }

    public function test_nothing_is_invented_when_there_is_no_usable_positioning_statement(): void
    {
        foreach ([$this->dna(null), $this->dna(''), $this->dna(str_repeat('A very long positioning paragraph. ', 10)), []] as $dna) {
            $this->assertNull($this->guard($this->hero(), $dna)['hero']['eyebrow']);
        }
    }
}
