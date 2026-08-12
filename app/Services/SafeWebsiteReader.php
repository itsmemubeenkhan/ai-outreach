<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SafeWebsiteReader
{
    public function read(?string $website): array
    {
        if (! $website) throw new RuntimeException('No website is saved for this lead.');
        $url = preg_match('#^https?://#i', $website) ? $website : 'https://'.$website;
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) throw new RuntimeException('The saved website URL is invalid.');

        $host = strtolower($parts['host']);
        $ips = gethostbynamel($host) ?: [];
        if (! $ips) throw new RuntimeException('The website domain could not be resolved.');
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new RuntimeException('This website address is not allowed.');
        }

        $port = ($parts['scheme'] === 'https') ? 443 : 80;
        $response = Http::timeout(10)->connectTimeout(4)->withUserAgent('AI-Outreach-CRM/1.0')->withOptions([
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ips[0]}"]],
        ])->get($url);
        if (! $response->successful()) throw new RuntimeException('Website returned HTTP '.$response->status().'.');
        $html = substr($response->body(), 0, 750000);
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//script|//style|//noscript|//svg') as $node) $node->parentNode?->removeChild($node);
        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent));
        $description = trim((string) ($xpath->query('//meta[@name="description"]')->item(0)?->getAttribute('content')));
        $headings = [];
        foreach ($xpath->query('//h1|//h2|//h3') as $node) {
            $value = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($value !== '') $headings[] = $value;
            if (count($headings) >= 20) break;
        }
        $text = trim(preg_replace('/\s+/', ' ', $dom->textContent));

        return ['url' => $url, 'title' => $title, 'description' => $description, 'headings' => array_values(array_unique($headings)), 'text' => mb_substr($text, 0, 12000)];
    }
}
