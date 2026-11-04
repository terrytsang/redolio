<?php

namespace App\Services\Crawler;

use App\DTO\Crawler\SiteSnapshotDto;
use App\Models\AnalysisRun;
use App\Models\EvidenceItem;
use App\Models\SitePage;
use App\Models\SiteSnapshot;
use Illuminate\Support\Facades\DB;

class SnapshotPersister
{
    public function persist(AnalysisRun $run, SiteSnapshotDto $snapshot, ?string $screenshotUrl = null): SiteSnapshot
    {
        return DB::transaction(function () use ($run, $snapshot, $screenshotUrl) {
            $siteSnapshot = SiteSnapshot::create([
                'analysis_run_id' => $run->id,
                'snapshot_hash' => $snapshot->snapshotHash,
                'homepage_url' => $snapshot->homepageUrl,
                'page_count' => count($snapshot->pages),
                'snapshot_json' => $snapshot->toSnapshotJson(),
                'warnings_json' => $snapshot->warnings,
                // A genuine capture from our own browser, or nothing — never a third-party "Generating Preview…" placeholder.
                'screenshot_path' => $screenshotUrl,
            ]);

            foreach ($snapshot->pages as $page) {
                SitePage::create([
                    'site_snapshot_id' => $siteSnapshot->id,
                    'url' => $page->url,
                    'http_status' => $page->httpStatus,
                    'title' => $page->title,
                    'meta_description' => $page->metaDescription,
                    'canonical_url' => $page->canonicalUrl,
                    'headings_json' => $page->headings,
                    'visible_text' => $page->visibleText,
                    'links_json' => array_map(fn ($l) => $l['href'], $page->links),
                    'ctas_json' => $page->ctas,
                    'forms_json' => $page->forms,
                    'image_alt_json' => $page->imageAlt,
                    'fetch_status' => $page->fetchStatus,
                ]);
            }

            foreach ($snapshot->evidence as $item) {
                EvidenceItem::create([
                    'site_snapshot_id' => $siteSnapshot->id,
                    'evidence_key' => $item->evidenceKey,
                    'page_url' => $item->pageUrl,
                    'evidence_type' => $item->evidenceType,
                    'label' => $item->label,
                    'excerpt' => $item->excerpt,
                    'locator_json' => $item->locator,
                    'created_at' => now(),
                ]);
            }

            return $siteSnapshot;
        });
    }
}
