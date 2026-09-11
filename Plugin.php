<?php namespace Albrightlabs\SitemapRobotsHumans;

use Route;
use Cache;
use Event;
use Response;
use Cms\Classes\Page;
use System\Classes\PluginBase;
use Albrightlabs\SitemapRobotsHumans\Models\Setting;
use System\Classes\SettingsManager;
use System\Classes\PluginManager;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/3.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Sitemap Robots Humans',
            'description' => 'Automatically generates sitemap.xml, robots.txt, and humans.txt files.',
            'author' => 'Albright Labs LLC',
            'icon' => 'icon-leaf'
        ];
    }

    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        //
    }

    /**
     * Read a setting as a string, falling back to $default when the stored
     * value is null as well as when the key is absent.
     *
     * Setting::get only applies its default when the key is missing. Clearing
     * a field in the backend stores null against the key, so the default never
     * applies and the null travels on into rtrim(), e() and string
     * concatenation. Under PHP 8.1+ each of those raises a deprecation, and the
     * output gets an empty value where a path or a priority belonged.
     *
     * Same root cause as the tailor_sections fatal fixed in 1.2.2, in its
     * non-fatal form. Routing every string-valued setting through here means
     * the next setting added does not reintroduce it.
     *
     * self:: resolves lexically at compile time, so this is safe to call from
     * the route closures below even though Laravel rebinds them when
     * dispatching.
     */
    protected static function settingString(string $key, string $default = ''): string
    {
        $value = Setting::get($key, $default);

        return $value === null ? $default : (string) $value;
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {

        // generates and returns sitemap index and pages sitemap, if enabled
        if (Setting::get('enable_sitemap', false)) {

            // Adds a Sitemap tab to the page settings in the CMS Editor, so theme
            // pages get the same per-page control as RainLab.Pages. The values are
            // written to the page's INI settings under the same keys the sitemap
            // route below already reads (enabled_in_sitemap, priority, changefreq),
            // so hand-edited pages and Editor-managed pages behave identically.
            Event::listen('cms.template.extendTemplateSettingsFields', function ($extension, $dataHolder) {
                if ($dataHolder->templateType !== 'page') {
                    return;
                }

                $dataHolder->settings[] = [
                    'property' => 'enabled_in_sitemap',
                    'title' => 'Include in sitemap',
                    'description' => 'Uncheck to leave this page out of sitemap_pages.xml. Hidden pages and pages with URL parameters are always left out.',
                    'type' => 'checkbox',
                    'default' => true,
                    'showExternalParam' => false,
                    'tab' => 'Sitemap',
                ];
                $dataHolder->settings[] = [
                    'property' => 'priority',
                    'title' => 'Sitemap priority',
                    'type' => 'dropdown',
                    'default' => '0.5',
                    'showExternalParam' => false,
                    'tab' => 'Sitemap',
                    'options' => [
                        '1.0' => '1.0 (Highest)',
                        '0.8' => '0.8',
                        '0.6' => '0.6',
                        '0.5' => '0.5 (Default)',
                        '0.4' => '0.4',
                        '0.2' => '0.2 (Lowest)',
                    ],
                ];
                $dataHolder->settings[] = [
                    'property' => 'changefreq',
                    'title' => 'Sitemap change frequency',
                    'type' => 'dropdown',
                    'default' => 'monthly',
                    'showExternalParam' => false,
                    'tab' => 'Sitemap',
                    'options' => [
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly (Default)',
                        'yearly' => 'Yearly',
                    ],
                ];
            });

            // Helper function to check if URL should be excluded
            $shouldExcludeUrl = function($url) {
                // Default keywords to exclude
                $defaultKeywords = ['404', 'error', 'maintenance'];

                // Check default keywords
                foreach ($defaultKeywords as $keyword) {
                    if (str_contains($url, $keyword)) {
                        return true;
                    }
                }

                // Check user-defined exclusions
                $excludedUrls = self::settingString('excluded_urls');
                if (!empty($excludedUrls)) {
                    $patterns = array_filter(array_map('trim', explode("\n", $excludedUrls)));
                    foreach ($patterns as $pattern) {
                        if (!empty($pattern) && str_contains($url, $pattern)) {
                            return true;
                        }
                    }
                }

                // Let other plugins veto a URL (e.g. pages behind a release gate
                // that redirect instead of returning 200). Any listener that
                // returns true removes the URL from the sitemap.
                $vetoes = Event::fire('albrightlabs.sitemap.excludeUrl', [$url]);
                foreach ((array) $vetoes as $veto) {
                    if ($veto === true) {
                        return true;
                    }
                }

                return false;
            };

            // /sitemap.xml - Sitemap INDEX referencing child sitemaps
            Route::get('/sitemap.xml', function () {
                $path = url('/');
                $blogSitemapUrl = self::settingString('blog_sitemap_url', '/blog/sitemap_index.xml');

                $sitemap = '<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>' . htmlspecialchars($path . '/sitemap_pages.xml', ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
    </sitemap>';

                // Only include blog sitemap if URL is configured
                if (!empty($blogSitemapUrl)) {
                    $sitemap .= '
    <sitemap>
        <loc>' . htmlspecialchars($path . $blogSitemapUrl, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
    </sitemap>';
                }

                $sitemap .= '
</sitemapindex>';

                return Response::make($sitemap)->header('Content-Type', 'application/xml');
            });

            // /sitemap_pages.xml - Pages urlset with all CMS pages
            Route::get('/sitemap_pages.xml', function () use ($shouldExcludeUrl) {

                // retrieve website base url
                $path = url('/');

                // retrieve all cms pages
                $pages = Page::all();

                // open sitemap
                $sitemap = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

                // adds each CMS page to sitemap
                foreach ($pages as $page) {

                    // exclude hidden pages
                    if ($page->is_hidden == 1) {
                        continue;
                    }

                    // exclude sitemap-free pages
                    if (isset($page->enabled_in_sitemap) && $page->enabled_in_sitemap == 0) {
                        continue;
                    }

                    // exclude dynamic pages (any URL parameter, e.g. :slug, :id,
                    // :hash) and URLs matching exclusion patterns
                    if (str_contains($page->url, ':') || $shouldExcludeUrl($page->url)) {
                        continue;
                    }

                    // add page to sitemap
                    $changefreq = $page->changefreq ?? 'monthly';
                    $priority = $page->priority ?? '0.5';
                    $sitemap .= '
    <url>
        <loc>' . htmlspecialchars($path . $page->url, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date("Y-m-d", $page->mtime) . '</lastmod>
        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>
        <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . '</priority>
    </url>';

                }

                // Check if RainLab.Pages plugin is installed, activated, and enabled in settings
                $pluginManager = PluginManager::instance();
                if (Setting::get('include_rainlab_pages', true) &&
                    $pluginManager->hasPlugin('RainLab.Pages') &&
                    !$pluginManager->isDisabled('RainLab.Pages')) {

                    // Add static pages from RainLab.Pages
                    $staticPages = \RainLab\Pages\Classes\Page::all();

                    foreach ($staticPages as $staticPage) {
                        // Skip hidden pages
                        if ($staticPage->is_hidden == 1) {
                            continue;
                        }

                        // Skip pages marked to exclude from sitemap
                        if (isset($staticPage->navigation_hidden) && $staticPage->navigation_hidden == 1) {
                            continue;
                        }

                        // Get the URL for the static page
                        $pageUrl = \RainLab\Pages\Classes\Page::url($staticPage->fileName);

                        if (!$pageUrl) {
                            continue;
                        }

                        // Skip pages matching exclusion patterns
                        if ($shouldExcludeUrl($pageUrl)) {
                            continue;
                        }

                        // Get page meta data
                        $changefreq = $staticPage->changefreq ?? 'monthly';
                        $priority = $staticPage->priority ?? '0.5';
                        $lastMod = $staticPage->updated_at ?? $staticPage->created_at ?? now();

                        // Add static page to sitemap
                        $sitemap .= '
    <url>
        <loc>' . htmlspecialchars($path . $pageUrl, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date("Y-m-d", strtotime($lastMod)) . '</lastmod>
        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>
        <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . '</priority>
    </url>';
                    }
                }

                // Check if OFFLINE.Boxes plugin is installed, activated, and enabled in settings
                if (Setting::get('include_offline_boxes', true) &&
                    $pluginManager->hasPlugin('OFFLINE.Boxes') &&
                    !$pluginManager->isDisabled('OFFLINE.Boxes')) {

                    try {
                        // Add Boxes pages
                        $boxesPages = \OFFLINE\Boxes\Models\Page::where('is_published', true)->get();

                        foreach ($boxesPages as $boxesPage) {
                            // Skip if page doesn't have a URL
                            if (empty($boxesPage->url)) {
                                continue;
                            }

                            // Skip pages matching exclusion patterns
                            if ($shouldExcludeUrl($boxesPage->url)) {
                                continue;
                            }

                            // Get page meta data
                            $changefreq = $boxesPage->meta_changefreq ?? 'monthly';
                            $priority = $boxesPage->meta_priority ?? '0.5';
                            $lastMod = $boxesPage->updated_at ?? $boxesPage->created_at ?? now();

                            // Build the full URL
                            $pageUrl = $boxesPage->url;
                            if (!str_starts_with($pageUrl, '/')) {
                                $pageUrl = '/' . $pageUrl;
                            }

                            // Add Boxes page to sitemap
                            $sitemap .= '
    <url>
        <loc>' . htmlspecialchars($path . $pageUrl, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date("Y-m-d", strtotime($lastMod)) . '</lastmod>
        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>
        <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . '</priority>
    </url>';
                        }
                    } catch (\Exception $e) {
                        // Silently skip if Boxes plugin classes are not available
                    }
                }

                // Add RainLab.Blog / AlbrightLabs.Blog posts and categories if enabled.
                // Auto-detects which blog plugin is installed; works with either natively.
                if (Setting::get('enable_blog_posts', false)) {
                    $blogPostModel = null;
                    $blogCategoryModel = null;

                    if ($pluginManager->hasPlugin('RainLab.Blog') && !$pluginManager->isDisabled('RainLab.Blog')) {
                        $blogPostModel = '\\RainLab\\Blog\\Models\\Post';
                        $blogCategoryModel = '\\RainLab\\Blog\\Models\\Category';
                    }
                    elseif ($pluginManager->hasPlugin('AlbrightLabs.Blog') && !$pluginManager->isDisabled('AlbrightLabs.Blog')) {
                        $blogPostModel = '\\AlbrightLabs\\Blog\\Models\\Post';
                        $blogCategoryModel = '\\AlbrightLabs\\Blog\\Models\\Category';
                    }

                    if ($blogPostModel && class_exists($blogPostModel)) {
                        $postPrefix = self::settingString('blog_post_url_prefix', '/blog');
                        $priority = self::settingString('blog_priority', '0.6');
                        $changefreq = self::settingString('blog_changefreq', 'weekly');

                        try {
                            $posts = $blogPostModel::where('published', 1)->get();

                            foreach ($posts as $post) {
                                if (empty($post->slug)) {
                                    continue;
                                }
                                $pageUrl = rtrim($postPrefix, '/') . '/' . $post->slug;

                                if ($shouldExcludeUrl($pageUrl)) {
                                    continue;
                                }

                                $lastMod = $post->updated_at ?? $post->published_at ?? now();
                                $sitemap .= '
    <url>
        <loc>' . htmlspecialchars($path . $pageUrl, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date("Y-m-d", strtotime($lastMod)) . '</lastmod>
        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>
        <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . '</priority>
    </url>';
                            }
                        }
                        catch (\Exception $e) {
                            // Blog table or plugin not ready - skip silently.
                        }

                        if (Setting::get('enable_blog_categories', false) && $blogCategoryModel && class_exists($blogCategoryModel)) {
                            $categoryPrefix = self::settingString('blog_category_url_prefix', '/blog/category');

                            try {
                                $categories = $blogCategoryModel::all();

                                foreach ($categories as $category) {
                                    if (empty($category->slug)) {
                                        continue;
                                    }
                                    $pageUrl = rtrim($categoryPrefix, '/') . '/' . $category->slug;

                                    if ($shouldExcludeUrl($pageUrl)) {
                                        continue;
                                    }

                                    $lastMod = $category->updated_at ?? $category->created_at ?? now();
                                    $sitemap .= '
    <url>
        <loc>' . htmlspecialchars($path . $pageUrl, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date("Y-m-d", strtotime($lastMod)) . '</lastmod>
        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>
        <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . '</priority>
    </url>';
                                }
                            }
                            catch (\Exception $e) {
                                // Category table not ready - skip silently.
                            }
                        }
                    }
                }

                // Add Tailor section entries if configured
                // Cast rather than rely on the Setting::get default: clearing the
                // repeater in the backend stores null, and the default only
                // applies when the key is absent, so a cleared list would
                // otherwise reach foreach() as null and 500 the whole sitemap.
                $tailorSections = (array) Setting::get('tailor_sections', []);
                foreach ($tailorSections as $config) {
                    if (empty($config['section_handle']) || empty($config['url_prefix'])) {
                        continue;
                    }
                    try {
                        $entries = \Tailor\Models\EntryRecord::inSection($config['section_handle'])
                            ->where('is_enabled', true)
                            ->get();

                        foreach ($entries as $entry) {
                            $pageUrl = rtrim($config['url_prefix'], '/') . '/' . $entry->slug;

                            // Skip entries matching exclusion patterns
                            if ($shouldExcludeUrl($pageUrl)) {
                                continue;
                            }

                            $lastMod = $entry->updated_at ?? $entry->created_at ?? now();
                            $priority = $config['priority'] ?? '0.6';
                            $changefreq = $config['changefreq'] ?? 'monthly';

                            $sitemap .= '
    <url>
        <loc>' . htmlspecialchars($path . $pageUrl, ENT_XML1, 'UTF-8') . '</loc>
        <lastmod>' . date("Y-m-d", strtotime($lastMod)) . '</lastmod>
        <changefreq>' . $changefreq . '</changefreq>
        <priority>' . $priority . '</priority>
    </url>';
                        }
                    } catch (\Exception $e) {
                        // Section not found or Tailor unavailable - skip silently
                    }
                }

                // close sitemap
                $sitemap .= '
</urlset>';

                // show sitemap
                return Response::make($sitemap)->header('Content-Type', 'application/xml');
            });
        }

        // generates and returns a robots.txt file, if enabled
        if (Setting::get('enable_robots', false)) {
            Route::get('robots.txt', function () {
                $content = "User-agent: *\r\n";
                $content .= e(self::settingString('robots_content'));
                return Response::make($content)->header('Content-Type', 'text/plain');
            });
        }

        // generates and returns a humans.txt file, if enabled
        if (Setting::get('enable_humans', false)) {
            Route::get('humans.txt', function () {
                $content = e(self::settingString('humans_content'));
                return Response::make($content)->header('Content-Type', 'text/plain');
            });
        }

        // generates and returns /llms.txt — a curated markdown index for AI discovery
        if (Setting::get('enable_llms', false)) {
            Route::get('/llms.txt', function () {
                $ttl = (int) Setting::get('llms_cache_ttl', 3600);
                $content = Cache::remember('llms_txt_index', $ttl, function () {
                    return \Albrightlabs\SitemapRobotsHumans\Classes\LlmsGenerator::generateIndex();
                });
                return Response::make($content)->header('Content-Type', 'text/plain; charset=utf-8');
            });
        }

        // generates and returns /llms-full.txt — full page content as markdown
        if (Setting::get('enable_llms_full', false)) {
            Route::get('/llms-full.txt', function () {
                $ttl = (int) Setting::get('llms_cache_ttl', 3600);
                $content = Cache::remember('llms_txt_full', $ttl, function () {
                    return \Albrightlabs\SitemapRobotsHumans\Classes\LlmsGenerator::generateFull();
                });
                return Response::make($content)->header('Content-Type', 'text/plain; charset=utf-8');
            });
        }

    }

    /**
     * @return array[]
     * Register settings
     */
    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'Sitemap, Robots, Humans',
                'description' => 'Manage the sitemap, robots, and humans settings.',
                'category' => SettingsManager::CATEGORY_CMS,
                'icon' => 'icon-cog',
                'class' => \Albrightlabs\SitemapRobotsHumans\Models\Setting::class,
                'order' => 500,
                'keywords' => 'sitemap robots humans'
            ]
        ];
    }

    /**
     * @return array
     * Register components
     */
    public function registerComponents()
    {
        return [
            \Albrightlabs\SitemapRobotsHumans\Components\Sitemap::class => 'sitemap'
        ];
    }

}
