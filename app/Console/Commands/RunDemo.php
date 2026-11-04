<?php

namespace App\Console\Commands;

use App\Jobs\RunVisualQaJob;
use App\Models\DemoFixture;
use App\Models\VisualQaReport;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\Fixtures\FixtureMaterializer;
use App\Services\Recommendation\BlueprintRecommender;
use App\Services\Recommendation\DirectionScorer;
use App\Services\Recommendation\PersistDesignSelection;
use App\Services\Recommendation\ProjectSignals;
use App\Services\VisualQa\BrowserService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DemoFixturesSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Repeatable end-to-end demo from a clean project, with no live model calls:
 * demo fixture → recommended direction + layout → mapped DesignSpec 1.5 → real-browser QA
 * (with bounded refinement) → before/after reveal. Safe to run any number of times.
 */
class RunDemo extends Command
{
    protected $signature = 'redo:demo {fixture=anchor-consulting : Demo fixture key} {--direction= : Force a direction id} {--skip-qa : Do not run browser QA}';

    protected $description = 'Run the repeatable v1.5 demo (fixture → blueprint → QA → before/after)';

    public function handle(FixtureMaterializer $materializer, DirectionScorer $scorer, DesignCatalogue $catalogue, BlueprintRecommender $recommender, PersistDesignSelection $persist, DesignSpecV15 $v15, BrowserService $browser): int
    {
        $this->components->info('1/6  Preparing catalogue, plans and demo data');
        $this->callSilent('db:seed', ['--class' => DesignDirectionSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => CatalogueSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => DemoFixturesSeeder::class, '--force' => true]);

        $fixture = DemoFixture::where('fixture_key', $this->argument('fixture'))->first();
        if (! $fixture) {
            $this->error('Unknown fixture. Available: '.DemoFixture::pluck('fixture_key')->implode(', '));

            return self::FAILURE;
        }

        $canBrowse = $browser->isAvailable();
        if ($canBrowse && ! Storage::disk('public')->exists("fixture-originals/{$fixture->fixture_key}.png") && $browser->id() === 'local') {
            $this->components->info('     rendering the illustrative original site');
            Artisan::call('fixtures:capture-originals');
        }

        $this->components->info('2/6  Creating a fresh project from the fixture');
        $run = $materializer->materialize($fixture);
        $spec = $run->currentDesignSpec();

        $this->components->info('3/6  Choosing a design direction and layout (deterministic scoring)');
        $signals = ProjectSignals::fromRun($run);
        $scored = collect($catalogue->directions())->map(fn ($d) => ['d' => $d, 'score' => $scorer->score($d, $signals)])->sortByDesc(fn ($x) => $x['score']['total'])->values();
        $pick = $this->option('direction') ? $scored->first(fn ($x) => $x['d']['id'] === $this->option('direction')) : $scored->first();
        if (! $pick) {
            $this->error('Unknown direction.');

            return self::FAILURE;
        }
        $directionId = $pick['d']['id'];
        $this->line("     {$pick['d']['name']}: ".implode(' ', array_slice($pick['score']['reasons'], 0, 2)));

        // The chosen pair must have passed preview QA to be selectable — make sure it has.
        $blueprintId = $pick['d']['showcase_blueprint_id'];
        if ($canBrowse) {
            Artisan::call('blueprints:capture', ['blueprint' => $blueprintId, '--direction' => $directionId]);
        }
        $recs = $recommender->recommend($directionId, $signals);
        if (! $recs) {
            $this->warn('     No ready layout for this direction (browser preview QA has not passed); continuing with the standard structure is not part of this demo.');

            return self::FAILURE;
        }
        $layout = $recs[0]['blueprint'];
        $this->line("     Layout: {$layout['name']}".($recs[0]['missing_content'] ? ' ('.count($recs[0]['missing_content']).' section(s) will be left out — no source content)' : ''));

        $this->components->info('4/6  Mapping real content into the layout and validating DesignSpec 1.5');
        $selection = $persist->handle($run, ['direction_key' => $directionId, 'blueprint_id' => $layout['id'], 'category_id' => $pick['d']['primary_category_ids'][0]], false);
        $result = $v15->attach($spec, $selection);
        if (! $result['attached']) {
            $this->error('DesignSpec 1.5 failed validation: '.implode('; ', $result['errors']));

            return self::FAILURE;
        }
        $this->line('     valid'.($result['partial'] ? ' (partial: some required content was not found on the source site)' : ''));

        if ($this->option('skip-qa') || ! $canBrowse) {
            $this->components->warn('5/6  Skipped browser QA'.($canBrowse ? '' : ' (no browser available — set REDO_BROWSER_PROVIDER=browserless or install Playwright)'));
        } else {
            $this->components->info('5/6  Real-browser QA at desktop, tablet and mobile (with bounded refinement)');
            config(['queue.default' => 'sync']);
            $report = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'pending', 'created_at' => now()]);
            RunVisualQaJob::dispatchSync($report->id);
            foreach (VisualQaReport::whereIn('design_spec_id', $run->designSpecs()->pluck('id'))->orderBy('id')->get() as $r) {
                $f = collect($r->report_json['findings'] ?? []);
                $this->line(sprintf('     v%d: %s — %d/%d checks passed%s', $r->designSpec->version, $r->status, $f->where('status', 'passed')->count(), $f->count(), isset($r->report_json['refined_to_version']) ? ' → refined to v'.$r->report_json['refined_to_version'] : ''));
            }
        }

        $this->components->info('6/6  Ready');
        $this->newLine();
        $this->line('  Before & after : '.route('analyses.reveal', $run->public_id));
        $this->line('  Interactive    : '.route('analyses.preview', $run->public_id));
        $this->line('  Publish / HTML : sign in with a Starter or Agency plan, then open the Launch page.');

        return self::SUCCESS;
    }
}
