<?php

namespace App\Console\Commands;

use App\Services\DesignDirection\DesignTrendScoutService;
use App\Services\MetaApi\Exceptions\MetaApiException;
use Illuminate\Console\Command;

/**
 * Self-evolving design taste: scout current web design trends via the
 * Meta API's live web_search tool and store generalized pattern entries.
 * Run manually with `php artisan design:scout-trends`, or let the
 * scheduler (bootstrap/app.php) run it weekly. Scheduling only fires if
 * the host's system cron calls `php artisan schedule:run` every minute —
 * standard Laravel requirement, not automatic on its own.
 */
class ScoutDesignTrends extends Command
{
    protected $signature = 'design:scout-trends';

    protected $description = 'Scout current web design trends via the Meta API web_search tool and store generalized patterns.';

    public function handle(DesignTrendScoutService $scout): int
    {
        $this->info('Scouting design trends via Meta API web_search...');

        try {
            $result = $scout->scout();
        } catch (MetaApiException $e) {
            $this->error("Scouting failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Stored {$result['pattern_count']} patterns (batch {$result['batch_id']}).");
        $this->info('Sources actually reachable this run: '.(implode(', ', $result['sources_reachable']) ?: 'none'));

        return self::SUCCESS;
    }
}
