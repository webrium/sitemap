<?php

namespace Webrium\Sitemap;

use XMLWriter;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

class Sitemap
{
    private XMLWriter $xmlWriter;
    private array $urls = [];
    private string $baseUrl;

    public const FREQ_ALWAYS  = 'always';
    public const FREQ_HOURLY  = 'hourly';
    public const FREQ_DAILY   = 'daily';
    public const FREQ_WEEKLY  = 'weekly';
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_YEARLY  = 'yearly';
    public const FREQ_NEVER   = 'never';

    public const MAX_URLS_PER_SITEMAP = 50000;
    public const MAX_FILESIZE_BYTES   = 52428800; // 50MB

    private const MIN_PRIORITY = 0.0;
    private const MAX_PRIORITY = 1.0;

    private const VALID_FREQS = [
        self::FREQ_ALWAYS, self::FREQ_HOURLY, self::FREQ_DAILY,
        self::FREQ_WEEKLY, self::FREQ_MONTHLY, self::FREQ_YEARLY, self::FREQ_NEVER,
    ];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->xmlWriter = new XMLWriter();
    }

    /**
     * Add a single URL entry to the sitemap.
     *
     * @param  array<string, string>  $images   Each item: ['loc'=>'...', 'title'=>'...', 'caption'=>'...']
     * @param  array<string, string>  $videos   Each item: ['thumbnail_loc'=>'...', 'title'=>'...', 'description'=>'...', 'duration'=>123]
     * @param  array<string, string>  $hreflangs  Each item: ['lang'=>'en', 'url'=>'https://...']
     */
    public function addUrl(
        string $path,
        ?DateTimeInterface $lastmod = null,
        string $changefreq = self::FREQ_WEEKLY,
        float $priority = 0.5,
        array $images = [],
        array $videos = [],
        array $hreflangs = []
    ): self {
        if (count($this->urls) >= self::MAX_URLS_PER_SITEMAP) {
            throw new SitemapException(
                'Maximum URL limit of ' . self::MAX_URLS_PER_SITEMAP . ' reached. Use generateSitemapIndex() to split into multiple sitemaps.'
            );
        }

        $this->validatePath($path);
        $this->validateChangefreq($changefreq);
        $this->validatePriority($priority);
        $this->validateImages($images);
        $this->validateVideos($videos);
        $this->validateHreflangs($hreflangs);

        $loc = $this->baseUrl . '/' . ltrim($path, '/');

        if ($this->isDuplicate($loc)) {
            return $this;
        }

        $this->urls[] = [
            'loc'        => $loc,
            'lastmod'    => $lastmod?->format('Y-m-d\TH:i:s\Z'),
            'changefreq' => $changefreq,
            'priority'   => number_format($priority, 1),
            'images'     => $images,
            'videos'     => $videos,
            'hreflangs'  => $hreflangs,
        ];

        return $this;
    }

    /**
     * Add multiple URL entries at once.
     */
    public function addUrls(array $urls): self
    {
        foreach ($urls as $url) {
            $this->addUrl(
                $url['path'],
                $url['lastmod']    ?? null,
                $url['changefreq'] ?? self::FREQ_WEEKLY,
                $url['priority']   ?? 0.5,
                $url['images']     ?? [],
                $url['videos']     ?? [],
                $url['hreflangs']  ?? []
            );
        }

        return $this;
    }

    /**
     * Generate the sitemap XML string.
     *
     * @throws SitemapException if the generated content exceeds the 50 MB limit.
     */
    public function generate(): string
    {
        $this->xmlWriter->openMemory();
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->setIndent(true);
        $this->xmlWriter->setIndentString('  ');

        $this->xmlWriter->startElement('urlset');
        $this->xmlWriter->writeAttribute('xmlns',       'http://www.sitemaps.org/schemas/sitemap/0.9');
        $this->xmlWriter->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $this->xmlWriter->writeAttribute('xmlns:video', 'http://www.google.com/schemas/sitemap-video/1.1');
        $this->xmlWriter->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach ($this->urls as $url) {
            $this->writeUrlElement($url);
        }

        $this->xmlWriter->endElement();
        $this->xmlWriter->endDocument();

        $output = $this->xmlWriter->outputMemory();

        if (strlen($output) > self::MAX_FILESIZE_BYTES) {
            throw new SitemapException('Generated sitemap exceeds the 50 MB size limit.');
        }

        return $output;
    }

    /**
     * Generate a sitemap index XML string.
     *
     * @param  array<int, array{loc: string, lastmod?: DateTimeInterface}>  $sitemapUrls
     */
    public function generateIndex(array $sitemapUrls): string
    {
        $this->xmlWriter->openMemory();
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->setIndent(true);
        $this->xmlWriter->setIndentString('  ');

        $this->xmlWriter->startElement('sitemapindex');
        $this->xmlWriter->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($sitemapUrls as $item) {
            if (empty($item['loc'])) {
                throw new InvalidArgumentException('Each sitemap index entry must have a "loc" key.');
            }

            $this->xmlWriter->startElement('sitemap');
            $this->xmlWriter->writeElement('loc', $item['loc']);

            if (isset($item['lastmod']) && $item['lastmod'] instanceof DateTimeInterface) {
                $this->xmlWriter->writeElement('lastmod', $item['lastmod']->format('Y-m-d\TH:i:s\Z'));
            }

            $this->xmlWriter->endElement();
        }

        $this->xmlWriter->endElement();
        $this->xmlWriter->endDocument();

        return $this->xmlWriter->outputMemory();
    }

    /**
     * Save the sitemap to a file (plain or gzipped).
     *
     * @param  string  $filename  File path; use .gz extension to enable gzip compression.
     */
    public function saveToFile(string $filename): bool
    {
        $content = $this->generate();

        if (str_ends_with($filename, '.gz')) {
            $compressed = gzencode($content, 9);
            if ($compressed === false) {
                throw new RuntimeException('Failed to gzip compress the sitemap content.');
            }
            return file_put_contents($filename, $compressed) !== false;
        }

        return file_put_contents($filename, $content) !== false;
    }

    /**
     * Automatically split URLs into chunks and save multiple sitemap files,
     * then save a sitemap index file pointing to all of them.
     *
     * @param  string  $directory    Directory where files will be saved.
     * @param  string  $baseFileUrl  Public base URL for the sitemap files (e.g. https://example.com/sitemaps).
     * @param  string  $prefix       Filename prefix (default: "sitemap").
     * @param  bool    $gzip         Whether to gzip the individual sitemap files.
     * @return string                The generated sitemap index XML.
     */
    public function splitAndSave(
        string $directory,
        string $baseFileUrl,
        string $prefix = 'sitemap',
        bool $gzip = false
    ): string {
        $directory   = rtrim($directory, '/');
        $baseFileUrl = rtrim($baseFileUrl, '/');
        $chunks      = array_chunk($this->urls, self::MAX_URLS_PER_SITEMAP);
        $indexItems  = [];
        $extension   = $gzip ? '.xml.gz' : '.xml';

        foreach ($chunks as $i => $chunk) {
            $filename     = "{$prefix}-" . ($i + 1) . $extension;
            $fullPath     = "{$directory}/{$filename}";
            $publicUrl    = "{$baseFileUrl}/{$filename}";

            $instance = new self($this->baseUrl);
            $instance->urls = $chunk;

            $content = $instance->generate();

            if ($gzip) {
                $compressed = gzencode($content, 9);
                if ($compressed === false) {
                    throw new RuntimeException("Failed to gzip compress chunk {$i}.");
                }
                file_put_contents($fullPath, $compressed);
            } else {
                file_put_contents($fullPath, $content);
            }

            $indexItems[] = ['loc' => $publicUrl];
        }

        return $this->generateIndex($indexItems);
    }

    public function getUrlCount(): int
    {
        return count($this->urls);
    }

    public function clearUrls(): self
    {
        $this->urls = [];
        return $this;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isDuplicate(string $loc): bool
    {
        foreach ($this->urls as $url) {
            if ($url['loc'] === $loc) {
                return true;
            }
        }
        return false;
    }

    private function writeUrlElement(array $url): void
    {
        $this->xmlWriter->startElement('url');

        $this->xmlWriter->writeElement('loc', $url['loc']);

        if ($url['lastmod']) {
            $this->xmlWriter->writeElement('lastmod', $url['lastmod']);
        }

        $this->xmlWriter->writeElement('changefreq', $url['changefreq']);
        $this->xmlWriter->writeElement('priority',   $url['priority']);

        foreach ($url['hreflangs'] as $hreflang) {
            $this->xmlWriter->startElement('xhtml:link');
            $this->xmlWriter->writeAttribute('rel',      'alternate');
            $this->xmlWriter->writeAttribute('hreflang', $hreflang['lang']);
            $this->xmlWriter->writeAttribute('href',     $hreflang['url']);
            $this->xmlWriter->endElement();
        }

        foreach ($url['images'] as $image) {
            $this->writeImageElement($image);
        }

        foreach ($url['videos'] as $video) {
            $this->writeVideoElement($video);
        }

        $this->xmlWriter->endElement();
    }

    private function writeImageElement(array $image): void
    {
        $this->xmlWriter->startElement('image:image');
        $this->xmlWriter->writeElement('image:loc', $image['loc']);

        if (!empty($image['title'])) {
            $this->xmlWriter->writeElement('image:title', $image['title']);
        }

        if (!empty($image['caption'])) {
            $this->xmlWriter->writeElement('image:caption', $image['caption']);
        }

        $this->xmlWriter->endElement();
    }

    private function writeVideoElement(array $video): void
    {
        $this->xmlWriter->startElement('video:video');
        $this->xmlWriter->writeElement('video:thumbnail_loc', $video['thumbnail_loc']);
        $this->xmlWriter->writeElement('video:title',         $video['title']);
        $this->xmlWriter->writeElement('video:description',   $video['description']);

        if (isset($video['duration'])) {
            $this->xmlWriter->writeElement('video:duration', (string) $video['duration']);
        }

        $this->xmlWriter->endElement();
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function validatePath(string $path): void
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Path cannot be empty.');
        }

        $full = $this->baseUrl . '/' . ltrim($path, '/');

        if (filter_var($full, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Invalid URL produced from path: {$full}");
        }
    }

    private function validateChangefreq(string $changefreq): void
    {
        if (!in_array($changefreq, self::VALID_FREQS, true)) {
            throw new InvalidArgumentException(
                "Invalid changefreq: '{$changefreq}'. Valid values: " . implode(', ', self::VALID_FREQS)
            );
        }
    }

    private function validatePriority(float $priority): void
    {
        if ($priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY) {
            throw new InvalidArgumentException(
                'Priority must be between ' . self::MIN_PRIORITY . ' and ' . self::MAX_PRIORITY . "."
            );
        }
    }

    private function validateImages(array $images): void
    {
        foreach ($images as $i => $image) {
            if (empty($image['loc'])) {
                throw new InvalidArgumentException("Image at index {$i} is missing the required 'loc' field.");
            }

            if (filter_var($image['loc'], FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException("Image at index {$i} has an invalid 'loc' URL: {$image['loc']}");
            }
        }
    }

    private function validateVideos(array $videos): void
    {
        $required = ['thumbnail_loc', 'title', 'description'];

        foreach ($videos as $i => $video) {
            foreach ($required as $field) {
                if (empty($video[$field])) {
                    throw new InvalidArgumentException(
                        "Video at index {$i} is missing the required '{$field}' field."
                    );
                }
            }

            if (filter_var($video['thumbnail_loc'], FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException(
                    "Video at index {$i} has an invalid 'thumbnail_loc' URL: {$video['thumbnail_loc']}"
                );
            }

            if (isset($video['duration']) && (!is_numeric($video['duration']) || $video['duration'] < 0)) {
                throw new InvalidArgumentException("Video at index {$i} has an invalid 'duration' value.");
            }
        }
    }

    private function validateHreflangs(array $hreflangs): void
    {
        foreach ($hreflangs as $i => $item) {
            if (empty($item['lang'])) {
                throw new InvalidArgumentException("Hreflang at index {$i} is missing the required 'lang' field.");
            }

            if (empty($item['url'])) {
                throw new InvalidArgumentException("Hreflang at index {$i} is missing the required 'url' field.");
            }

            if (filter_var($item['url'], FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException(
                    "Hreflang at index {$i} has an invalid 'url': {$item['url']}"
                );
            }
        }
    }
}