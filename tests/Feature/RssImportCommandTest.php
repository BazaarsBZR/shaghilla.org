<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\FeedSource;
use App\Models\KeywordRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RssImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_rss_items_and_applies_keyword_rules_and_dedupes(): void
    {
        $lebanonCategory = Category::create([
            'slug' => 'lebanon',
            'name_ar' => 'لبنان',
            'name_en' => 'Lebanon',
            'sort_order' => 1,
            'is_system' => true,
        ]);

        $workersCategory = Category::create([
            'slug' => 'workers',
            'name_ar' => 'عمال',
            'name_en' => 'Workers',
            'sort_order' => 2,
            'is_system' => true,
        ]);

        KeywordRule::create([
            'type' => KeywordRule::TYPE_WORKER,
            'keyword' => 'عامل',
            'is_active' => true,
        ]);

        KeywordRule::create([
            'type' => KeywordRule::TYPE_BREAKING,
            'keyword' => 'عاجل',
            'is_active' => true,
        ]);

        $feedUrl = 'https://example.test/Rss/News/1/لبنان';
        $encodedFeedUrl = 'https://example.test/Rss/News/1/%D9%84%D8%A8%D9%86%D8%A7%D9%86';

        FeedSource::create([
            'name' => 'Example RSS',
            'url' => $feedUrl,
            'is_active' => true,
            'default_category_id' => $lebanonCategory->id,
        ]);

        $this->assertDatabaseCount('feed_sources', 1);
        $this->assertSame(1, FeedSource::query()->where('is_active', true)->count());

        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
    <channel>
        <title>Example</title>
        <item>
            <title><![CDATA[خبر عاجل عن عامل]]></title>
            <link>https://example.test/news/1</link>
            <guid isPermaLink="false">example-guid-1</guid>
            <pubDate>Mon, 13 Jan 2026 12:00:00 +0000</pubDate>
            <description><![CDATA[هذا خبر عاجل يخص عامل.]]></description>
            <enclosure url="https://example.test/image.jpg" type="image/jpeg" />
        </item>
        <item>
            <title><![CDATA[خبر ثان]]></title>
            <link>https://example.test/news/2</link>
            <guid isPermaLink="false">example-guid-2</guid>
            <pubDate>Mon, 13 Jan 2026 12:05:00 +0000</pubDate>
            <description><![CDATA[نص قصير]]></description>
            <media:content url="https://example.test/media.jpg" medium="image" type="image/jpeg" />
        </item>
    </channel>
</rss>
XML;

        Http::fake(fn ($request) => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']));

        $this->artisan('news:import-rss')
            ->assertExitCode(0);

        Http::assertSentCount(1);

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        $this->assertSame($encodedFeedUrl, $recorded[0][0]->url());

        $this->assertDatabaseCount('articles', 2);

        /** @var Article $article */
        $article = Article::query()->where('canonical_url', 'https://example.test/news/1')->firstOrFail();

        $this->assertTrue($article->is_breaking);
        $this->assertSame($workersCategory->id, $article->category_id);
        $this->assertSame('ar', $article->language);
        $this->assertSame('published', $article->status);
        $this->assertSame('https://example.test/news/1', $article->canonical_url);
        $this->assertSame('https://example.test/image.jpg', $article->image_url);

        $second = Article::query()->where('canonical_url', 'https://example.test/news/2')->firstOrFail();
        $this->assertSame('https://example.test/media.jpg', $second->image_url);

        $this->artisan('news:import-rss')
            ->assertExitCode(0);

        $this->assertDatabaseCount('articles', 2);
    }
}
