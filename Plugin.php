<?php namespace Albrightlabs\SitemapRobotsHumans;

use Route;
use Response;
use Cms\Classes\Page;
use System\Classes\PluginBase;
use Albrightlabs\SitemapRobotsHumans\Models\Setting;
use System\Classes\SettingsManager;

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

        // generates and returns sitemap, if enabled
        if (Setting::get('enable_sitemap', false)) {
            Route::get('/sitemap.xml', function () {

                // retrieve website base url
                $path = url('/');

                // retrieve all cms pages
                $pages = Page::all();

                // open sitemap
                $sitemap = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
	  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
	  xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

                // adds each page to sitemap
                foreach ($pages as $page) {

                    // exclude hidden pages
                    if ($page->is_hidden == 1) {
                        continue;
                    }

                    // exclude sitemap-free pages
                    if ($page->enabled_in_sitemap == 0) {
                        continue;
                    }

                    // exclude any pages with specific strings in URL
                    $keywords = ['404', 'error', ':slug', 'maintenance'];
                    foreach ($keywords as $keyword) {
                        if (str_contains($page->url, $keyword)) {
                            continue 2;
                        }
                    }

                    // add page to sitemap
                    $sitemap .= '
<url>
    <loc>' . $path . $page->url . '</loc>
    <lastmod>' . date("Y-m-d", $page->mtime) . '</lastmod>
    <changefreq>' . $page->changefreq . '</changefreq>
    <priority>' . $page->priority . '</priority>
</url>
        ';

                }

                // close sitemap
                $sitemap .= '</urlset>';

                // show sitemap
                return Response::make($sitemap)->header('Content-Type', 'application/xml');
            });
        }

        // generates and returns a robots.txt file, if enabled
        if (Setting::get('enable_robots', false)) {
            Route::get('robots.txt', function () {
                header("Content-Type: text/plain");
                print_r("User-agent: *\r\n");
                print_r(Setting::get('robots_content', ''));
            });
        }

        // generates and returns a humans.txt file, if enabled
        if (Setting::get('enable_humans', false)) {
            Route::get('humans.txt', function () {
                header("Content-Type: text/plain");
                print_r(Setting::get('humans_content', ''));
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

}
