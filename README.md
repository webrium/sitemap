# Webrium Sitemap

A production-ready PHP library for generating standard XML sitemaps with support for images, videos, hreflang (multi-language), gzip compression, and automatic sitemap splitting for large sites.

## Requirements

- PHP 8.1+
- ext-xmlwriter
- ext-zlib

## Installation

```bash
composer require webrium/sitemap
```

## Basic Usage

```php
use Webrium\Sitemap\Sitemap;

$sitemap = new Sitemap('https://example.com');

$sitemap->addUrl('/',      changefreq: Sitemap::FREQ_DAILY,   priority: 1.0); // → https://example.com
$sitemap->addUrl('/about', changefreq: Sitemap::FREQ_MONTHLY, priority: 0.8);
$sitemap->addUrl('/blog',  changefreq: Sitemap::FREQ_DAILY,   priority: 0.9);

echo $sitemap->generate();
```

## Adding URLs with Full Options

```php
use DateTime;
use Webrium\Sitemap\Sitemap;

$sitemap = new Sitemap('https://example.com');

$sitemap->addUrl(
    path:       '/blog/my-post',
    lastmod:    new DateTime('2024-06-01'),
    changefreq: Sitemap::FREQ_WEEKLY,
    priority:   0.7
);
```

## Bulk Adding URLs

```php
$sitemap->addUrls([
    ['path' => '/page-1', 'priority' => 0.9],
    ['path' => '/page-2', 'changefreq' => Sitemap::FREQ_DAILY],
    ['path' => '/page-3', 'lastmod' => new DateTime('2024-01-01')],
]);
```

## Images

```php
$sitemap->addUrl(
    path:   '/gallery',
    images: [
        [
            'loc'     => 'https://example.com/images/photo.jpg',
            'title'   => 'Photo title',
            'caption' => 'Photo caption',
        ],
    ]
);
```

## Videos

```php
$sitemap->addUrl(
    path:   '/videos/intro',
    videos: [
        [
            'thumbnail_loc' => 'https://example.com/thumbs/intro.jpg',
            'title'         => 'Introduction',
            'description'   => 'A quick introduction video.',
            'duration'      => 120,
        ],
    ]
);
```

## Hreflang (Multi-language)

```php
$sitemap->addUrl(
    path:       '/about',
    hreflangs:  [
        ['lang' => 'en', 'url' => 'https://example.com/en/about'],
        ['lang' => 'fa', 'url' => 'https://example.com/fa/about'],
        ['lang' => 'x-default', 'url' => 'https://example.com/about'],
    ]
);
```

## Saving to File

```php
// Plain XML
$sitemap->saveToFile('/var/www/public/sitemap.xml');

// Gzip compressed (use .gz extension)
$sitemap->saveToFile('/var/www/public/sitemap.xml.gz');
```

## Large Sites — Automatic Splitting

When your site has more than 50,000 URLs, use `splitAndSave()` to automatically split into multiple files and generate a sitemap index:

```php
$sitemap = new Sitemap('https://example.com');

// Add all your URLs (any number)
foreach ($allPages as $page) {
    $sitemap->addUrl($page->path, $page->updatedAt);
}

$indexXml = $sitemap->splitAndSave(
    directory:   '/var/www/public/sitemaps',
    baseFileUrl: 'https://example.com/sitemaps',
    prefix:      'sitemap',
    gzip:        false
);

// Save the index file
file_put_contents('/var/www/public/sitemap-index.xml', $indexXml);
```

This generates `sitemap-1.xml`, `sitemap-2.xml`, ... and a sitemap index pointing to all of them.

## Manual Sitemap Index

```php
$indexXml = $sitemap->generateIndex([
    ['loc' => 'https://example.com/sitemaps/sitemap-1.xml', 'lastmod' => new DateTime()],
    ['loc' => 'https://example.com/sitemaps/sitemap-2.xml'],
]);
```

## Change Frequency Constants

| Constant              | Value     |
|-----------------------|-----------|
| `Sitemap::FREQ_ALWAYS`  | always  |
| `Sitemap::FREQ_HOURLY`  | hourly  |
| `Sitemap::FREQ_DAILY`   | daily   |
| `Sitemap::FREQ_WEEKLY`  | weekly  |
| `Sitemap::FREQ_MONTHLY` | monthly |
| `Sitemap::FREQ_YEARLY`  | yearly  |
| `Sitemap::FREQ_NEVER`   | never   |

## Limits

| Constraint          | Value                  |
|---------------------|------------------------|
| Max URLs per file   | 50,000                 |
| Max file size       | 50 MB                  |
| Duplicate URLs      | Silently ignored       |

## Running Tests

```bash
composer install
./vendor/bin/phpunit tests/
```

## License

MIT
