<?php

namespace App\Services\Crawler;

use App\DTO\Crawler\ExtractedPage;
use App\DTO\Crawler\SiteSnapshotDto;

/**
 * Decides whether what the crawler read is actually the website's content. A bot-protection
 * challenge ("POW", "Just a moment…"), an error page or an empty JavaScript shell is not content:
 * analysing it produces a redesign about nothing. This gate runs BEFORE any model call.
 */
class ContentQualityAssessor
{
    public const MIN_TEXT_CHARS = 250;

    private const CHALLENGE_TITLES = '/^\s*(pow|just a moment|attention required|checking your browser|access denied|forbidden|one moment|please wait|security check|verify(ing)? (that )?you are (a )?human|ddos protection|are you a robot|robot check|bot verification|blocked|service unavailable|error 5\d\d|40[0-9]\b)/i';

    private const CHALLENGE_TEXT = '/(enable javascript and cookies|checking (if the site connection is secure|your browser)|verify you are human|complete the security check|unusual traffic|captcha|ray id|access (to this page )?(has been )?denied|you have been blocked|request blocked)/i';

    /** @return array{ok: bool, reason: ?string, message: string, signals: string[]} */
    public function assess(SiteSnapshotDto|ExtractedPage|null $subject): array
    {
        $home = $subject instanceof SiteSnapshotDto ? ($subject->pages[0] ?? null) : $subject;
        if ($home === null) {
            return $this->result(false, 'empty', 'No page could be read.', ['no_pages']);
        }

        $signals = [];
        $text = trim($home->visibleText);
        $title = (string) $home->title;

        if ($home->httpStatus >= 400) {
            $signals[] = "http_{$home->httpStatus}";
        }
        if (preg_match(self::CHALLENGE_TITLES, $title)) {
            $signals[] = 'challenge_title';
        }
        if (strlen($text) < 900 && preg_match(self::CHALLENGE_TEXT, $text.' '.$title)) {
            $signals[] = 'challenge_text';
        }
        if (in_array('bot_challenge_detected', $home->warnings, true)) {
            $signals[] = 'challenge_markup';
        }
        if ($signals !== []) {
            return $this->result(false, 'blocked', 'The page that came back is an access check or error page, not the website.', $signals);
        }

        if (strlen($text) < self::MIN_TEXT_CHARS && count($home->headings) < 2) {
            return $this->result(false, 'thin', 'Very little readable text was found; the site may render its content with JavaScript or consist mostly of images.', ['thin_content']);
        }

        return $this->result(true, null, 'Readable website content was found.', []);
    }

    /** @param string[] $signals */
    private function result(bool $ok, ?string $reason, string $message, array $signals): array
    {
        return ['ok' => $ok, 'reason' => $reason, 'message' => $message, 'signals' => $signals];
    }
}
