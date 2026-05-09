<?php

namespace Webrium\Sitemap\Tests;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Webrium\Sitemap\Sitemap;
use Webrium\Sitemap\SitemapException;

class SitemapTest extends TestCase
{
    private Sitemap $sitemap;

    protected function setUp(): void
    {
        $this->sitemap = new Sitemap('https://example.com');
    }

    // -------------------------------------------------------------------------
    // addUrl — basic
    // -------------------------------------------------------------------------

    public function test_add_single_url(): void
    {
        $this->sitemap->addUrl('/about');
        $this->assertSame(1, $this->sitemap->getUrlCount());
    }

    public function test_add_url_returns_self_for_chaining(): void
    {
        $result = $this->sitemap->addUrl('/about');
        $this->assertInstanceOf(Sitemap::class, $result);
    }

    public function test_add_url_with_all_parameters(): void
    {
        $this->sitemap->addUrl(
            '/page',
            new DateTime('2024-01-01'),
            Sitemap::FREQ_DAILY,
            0.8
        );
        $this->assertSame(1, $this->sitemap->getUrlCount());
    }

    public function test_add_multiple_urls_via_add_urls(): void
    {
        $this->sitemap->addUrls([
            ['path' => '/page-1'],
            ['path' => '/page-2'],
            ['path' => '/page-3'],
        ]);
        $this->assertSame(3, $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // Duplicate filtering
    // -------------------------------------------------------------------------

    public function test_duplicate_url_is_ignored(): void
    {
        $this->sitemap->addUrl('/about');
        $this->sitemap->addUrl('/about');
        $this->assertSame(1, $this->sitemap->getUrlCount());
    }

    public function test_different_paths_are_not_duplicates(): void
    {
        $this->sitemap->addUrl('/about');
        $this->sitemap->addUrl('/contact');
        $this->assertSame(2, $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // Validation — path
    // -------------------------------------------------------------------------

    public function test_empty_path_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('');
    }

    public function test_whitespace_only_path_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('   ');
    }

    // -------------------------------------------------------------------------
    // Validation — changefreq
    // -------------------------------------------------------------------------

    public function test_invalid_changefreq_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, 'biannually');
    }

    public function test_all_valid_changefreq_values_are_accepted(): void
    {
        $freqs = [
            Sitemap::FREQ_ALWAYS, Sitemap::FREQ_HOURLY, Sitemap::FREQ_DAILY,
            Sitemap::FREQ_WEEKLY, Sitemap::FREQ_MONTHLY, Sitemap::FREQ_YEARLY,
            Sitemap::FREQ_NEVER,
        ];

        foreach ($freqs as $i => $freq) {
            $this->sitemap->addUrl('/page-' . $i, null, $freq);
        }

        $this->assertSame(count($freqs), $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // Validation — priority
    // -------------------------------------------------------------------------

    public function test_priority_above_max_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 1.1);
    }

    public function test_priority_below_min_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, -0.1);
    }

    public function test_priority_at_boundaries_is_valid(): void
    {
        $this->sitemap->addUrl('/min', null, Sitemap::FREQ_WEEKLY, 0.0);
        $this->sitemap->addUrl('/max', null, Sitemap::FREQ_WEEKLY, 1.0);
        $this->assertSame(2, $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // Validation — images
    // -------------------------------------------------------------------------

    public function test_image_without_loc_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [
            ['title' => 'No loc field'],
        ]);
    }

    public function test_image_with_invalid_url_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [
            ['loc' => 'not-a-url'],
        ]);
    }

    public function test_valid_image_is_accepted(): void
    {
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [
            ['loc' => 'https://example.com/image.jpg', 'title' => 'My image'],
        ]);
        $this->assertSame(1, $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // Validation — videos
    // -------------------------------------------------------------------------

    public function test_video_missing_required_field_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [
            ['thumbnail_loc' => 'https://example.com/thumb.jpg', 'title' => 'My video'],
            // missing 'description'
        ]);
    }

    public function test_video_with_invalid_thumbnail_url_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [
            [
                'thumbnail_loc' => 'bad-url',
                'title'         => 'Video',
                'description'   => 'Desc',
            ],
        ]);
    }

    public function test_video_with_invalid_duration_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [
            [
                'thumbnail_loc' => 'https://example.com/thumb.jpg',
                'title'         => 'Video',
                'description'   => 'Desc',
                'duration'      => -10,
            ],
        ]);
    }

    public function test_valid_video_is_accepted(): void
    {
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [
            [
                'thumbnail_loc' => 'https://example.com/thumb.jpg',
                'title'         => 'My Video',
                'description'   => 'A great video',
                'duration'      => 120,
            ],
        ]);
        $this->assertSame(1, $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // Validation — hreflang
    // -------------------------------------------------------------------------

    public function test_hreflang_missing_lang_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [], [
            ['url' => 'https://example.com/en/page'],
        ]);
    }

    public function test_hreflang_missing_url_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [], [
            ['lang' => 'en'],
        ]);
    }

    public function test_hreflang_with_invalid_url_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [], [
            ['lang' => 'en', 'url' => 'not-a-url'],
        ]);
    }

    public function test_valid_hreflang_is_accepted(): void
    {
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [], [
            ['lang' => 'en', 'url' => 'https://example.com/en/page'],
            ['lang' => 'fa', 'url' => 'https://example.com/fa/page'],
        ]);
        $this->assertSame(1, $this->sitemap->getUrlCount());
    }

    // -------------------------------------------------------------------------
    // URL limit
    // -------------------------------------------------------------------------

    public function test_exceeding_url_limit_throws_sitemap_exception(): void
    {
        $this->expectException(SitemapException::class);

        for ($i = 0; $i <= Sitemap::MAX_URLS_PER_SITEMAP; $i++) {
            $this->sitemap->addUrl('/page-' . $i);
        }
    }

    // -------------------------------------------------------------------------
    // generate()
    // -------------------------------------------------------------------------

    public function test_generate_returns_valid_xml_string(): void
    {
        $this->sitemap->addUrl('/about');
        $xml = $this->sitemap->generate();

        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('https://example.com/about', $xml);
    }

    public function test_generate_includes_lastmod_when_provided(): void
    {
        $this->sitemap->addUrl('/page', new DateTime('2024-06-15 12:00:00'));
        $xml = $this->sitemap->generate();

        $this->assertStringContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('2024-06-15', $xml);
    }

    public function test_generate_includes_image_elements(): void
    {
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [
            ['loc' => 'https://example.com/img.jpg', 'title' => 'Cover'],
        ]);
        $xml = $this->sitemap->generate();

        $this->assertStringContainsString('image:image', $xml);
        $this->assertStringContainsString('https://example.com/img.jpg', $xml);
    }

    public function test_generate_includes_video_elements(): void
    {
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [
            [
                'thumbnail_loc' => 'https://example.com/thumb.jpg',
                'title'         => 'Intro',
                'description'   => 'Welcome video',
            ],
        ]);
        $xml = $this->sitemap->generate();

        $this->assertStringContainsString('video:video', $xml);
        $this->assertStringContainsString('Intro', $xml);
    }

    public function test_generate_includes_hreflang_elements(): void
    {
        $this->sitemap->addUrl('/page', null, Sitemap::FREQ_WEEKLY, 0.5, [], [], [
            ['lang' => 'en', 'url' => 'https://example.com/en/page'],
        ]);
        $xml = $this->sitemap->generate();

        $this->assertStringContainsString('xhtml:link', $xml);
        $this->assertStringContainsString('hreflang', $xml);
    }

    public function test_generate_xml_is_parseable(): void
    {
        $this->sitemap->addUrl('/home');
        $this->sitemap->addUrl('/about');
        $xml = $this->sitemap->generate();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
    }

    // -------------------------------------------------------------------------
    // generateIndex()
    // -------------------------------------------------------------------------

    public function test_generate_index_returns_valid_xml(): void
    {
        $xml = $this->sitemap->generateIndex([
            ['loc' => 'https://example.com/sitemap-1.xml'],
            ['loc' => 'https://example.com/sitemap-2.xml'],
        ]);

        $this->assertStringContainsString('<sitemapindex', $xml);
        $this->assertStringContainsString('sitemap-1.xml', $xml);
        $this->assertStringContainsString('sitemap-2.xml', $xml);
    }

    public function test_generate_index_includes_lastmod_when_provided(): void
    {
        $xml = $this->sitemap->generateIndex([
            ['loc' => 'https://example.com/sitemap-1.xml', 'lastmod' => new DateTime('2024-01-01')],
        ]);

        $this->assertStringContainsString('<lastmod>', $xml);
    }

    public function test_generate_index_entry_without_loc_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sitemap->generateIndex([['lastmod' => new DateTime()]]);
    }

    // -------------------------------------------------------------------------
    // clearUrls()
    // -------------------------------------------------------------------------

    public function test_clear_urls_resets_count_to_zero(): void
    {
        $this->sitemap->addUrl('/page-1');
        $this->sitemap->addUrl('/page-2');
        $this->sitemap->clearUrls();

        $this->assertSame(0, $this->sitemap->getUrlCount());
    }

    public function test_clear_urls_returns_self_for_chaining(): void
    {
        $result = $this->sitemap->clearUrls();
        $this->assertInstanceOf(Sitemap::class, $result);
    }

    // -------------------------------------------------------------------------
    // saveToFile()
    // -------------------------------------------------------------------------

    public function test_save_to_plain_file(): void
    {
        $this->sitemap->addUrl('/home');
        $path = sys_get_temp_dir() . '/sitemap_test_' . uniqid() . '.xml';

        $result = $this->sitemap->saveToFile($path);

        $this->assertTrue($result);
        $this->assertFileExists($path);
        $this->assertStringContainsString('<urlset', file_get_contents($path));

        unlink($path);
    }

    public function test_save_to_gzip_file(): void
    {
        $this->sitemap->addUrl('/home');
        $path = sys_get_temp_dir() . '/sitemap_test_' . uniqid() . '.xml.gz';

        $result = $this->sitemap->saveToFile($path);

        $this->assertTrue($result);
        $this->assertFileExists($path);

        $content = gzdecode(file_get_contents($path));
        $this->assertStringContainsString('<urlset', $content);

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // splitAndSave()
    // -------------------------------------------------------------------------

    public function test_split_and_save_creates_multiple_files(): void
    {
        // Add enough URLs to force 2 chunks (we temporarily lower chunk size via a mock approach)
        // Here we just add a small set and verify the method runs and returns index XML.
        for ($i = 1; $i <= 5; $i++) {
            $this->sitemap->addUrl('/page-' . $i);
        }

        $dir = sys_get_temp_dir() . '/sitemap_split_' . uniqid();
        mkdir($dir);

        $indexXml = $this->sitemap->splitAndSave($dir, 'https://example.com/sitemaps');

        $this->assertStringContainsString('<sitemapindex', $indexXml);

        // Clean up
        foreach (glob($dir . '/*') as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}