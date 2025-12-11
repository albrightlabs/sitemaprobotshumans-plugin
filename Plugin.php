<?php namespace Albrightlabs\SitemapRobotsHumans;

use Route;
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
     * boot method, called right before the request route.
     */
    public function boot()
    {

        // generates and returns sitemap index and pages sitemap, if enabled
        if (Setting::get('enable_sitemap', false)) {

            // /sitemap.xml - Sitemap INDEX referencing child sitemaps
            Route::get('/sitemap.xml', function () {
                $path = url('/');
                $blogSitemapUrl = Setting::get('blog_sitemap_url', '/blog/sitemap_index.xml');

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
            Route::get('/sitemap_pages.xml', function () {

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

                    // exclude any pages with specific strings in URL
                    $keywords = ['404', 'error', ':slug', 'maintenance'];
                    $skipPage = false;
                    foreach ($keywords as $keyword) {
                        if (str_contains($page->url, $keyword)) {
                            $skipPage = true;
                            break;
                        }
                    }
                    if ($skipPage) {
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

                        // Skip pages with error keywords
                        $keywords = ['404', 'error', 'maintenance'];
                        $skipPage = false;
                        foreach ($keywords as $keyword) {
                            if (str_contains($pageUrl, $keyword)) {
                                $skipPage = true;
                                break;
                            }
                        }
                        if ($skipPage) {
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

                            // Skip pages with error keywords
                            $keywords = ['404', 'error', 'maintenance'];
                            $skipPage = false;
                            foreach ($keywords as $keyword) {
                                if (str_contains($boxesPage->url, $keyword)) {
                                    $skipPage = true;
                                    break;
                                }
                            }
                            if ($skipPage) {
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

                // Add Tailor section entries if configured
                $tailorSections = Setting::get('tailor_sections', []);
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
                $content .= e(Setting::get('robots_content', ''));
                return Response::make($content)->header('Content-Type', 'text/plain');
            });
        }

        // generates and returns a humans.txt file, if enabled
        if (Setting::get('enable_humans', false)) {
            Route::get('humans.txt', function () {
                $content = e(Setting::get('humans_content', ''));
                return Response::make($content)->header('Content-Type', 'text/plain');
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
