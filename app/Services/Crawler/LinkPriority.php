<?php

namespace App\Services\Crawler;

/**
 * Which internal links are worth the page budget. The pages that explain a business (about, services, pricing, contact,
 * team, proof) come first; blog posts, tag pages, legal text and login screens come last.
 */
class LinkPriority
{
    private const WANTED = ['about', 'company', 'story', 'team', 'people', 'services', 'service', 'solutions', 'products', 'product', 'pricing', 'plans', 'contact',
        'testimonials', 'reviews', 'case-studies', 'portfolio', 'work', 'faq', 'locations', 'treatments', 'menu', 'features', 'how-it-works', 'clients', 'results'];

    private const UNWANTED = ['blog', 'news', 'post', 'posts', 'article', 'articles', 'tag', 'tags', 'category', 'categories', 'author', 'privacy', 'privacy-policy',
        'terms', 'terms-of-service', 'cookie', 'cookies', 'legal', 'login', 'signin', 'sign-in', 'register', 'signup', 'cart', 'checkout', 'account', 'feed', 'search',
        'careers', 'jobs', 'sitemap', 'wp-admin', 'wp-login', 'wp-content', 'wp-json', 'page'];

    public function score(string $href, string $label = ''): int
    {
        $path = strtolower((string) (parse_url($href, PHP_URL_PATH) ?: '/'));
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $score = 0;

        $tokens = array_merge($segments, preg_split('/[\s\-_]+/', strtolower($label)) ?: []);
        foreach ($tokens as $t) {
            in_array($t, self::WANTED, true) && $score += 10;
            in_array($t, self::UNWANTED, true) && $score -= 10;
        }
        foreach ($segments as $seg) {
            foreach (self::WANTED as $w) {
                if (str_contains($seg, $w) && ! in_array($seg, self::WANTED, true)) { // about-us, our-services, contact-us
                    $score += 6;
                    break;
                }
            }
        }
        if (count($segments) === 1) {
            $score += 2; // top-level pages are usually the main ones
        }
        if (count($segments) > 3) {
            $score -= 3;
        }
        if (preg_match('#/(19|20)\d{2}/\d{1,2}(/|$)#', $path) || preg_match('#\.(pdf|jpe?g|png|gif|webp|zip|docx?|xlsx?|mp4)$#', $path)) {
            $score -= 10;
        }
        if (parse_url($href, PHP_URL_QUERY)) {
            $score -= 3;
        }

        return $score;
    }
}
