<?php namespace Albrightlabs\SitemapRobotsHumans\Components;

use Cms\Classes\ComponentBase;
use Cms\Classes\Page;

class Sitemap extends ComponentBase
{
    /**
     * Component details
     */
    public function componentDetails()
    {
        return [
            'name' => 'Sitemap',
            'description' => 'Displays a human-readable sitemap with hierarchical page structure'
        ];
    }

    /**
     * Component properties
     */
    public function defineProperties()
    {
        return [];
    }

    /**
     * Run on page load
     */
    public function onRun()
    {
        $this->page['sitemapPages'] = $this->getPages();
    }

    /**
     * Get all pages filtered and organized hierarchically
     */
    protected function getPages()
    {
        $pages = Page::all()->filter(function ($page) {
            // Exclude hidden pages
            if ($page->is_hidden) {
                return false;
            }

            // Exclude dynamic pages with URL parameters
            if (str_contains($page->url, ':')) {
                return false;
            }

            // Exclude error and system pages
            $excludeKeywords = ['404', 'error', 'maintenance'];
            foreach ($excludeKeywords as $keyword) {
                if (str_contains($page->url, $keyword)) {
                    return false;
                }
            }

            // Exclude account/admin pages
            if (str_contains($page->url, 'account-')) {
                return false;
            }

            return true;
        });

        return $this->buildTree($pages);
    }

    /**
     * Build hierarchical tree structure from flat page list
     * Groups child pages under their parent URLs
     */
    protected function buildTree($pages)
    {
        $tree = [];
        $childPages = [];

        // Sort pages by URL
        $pages = $pages->sortBy('url');

        // First pass: identify parent and child pages
        foreach ($pages as $page) {
            $url = $page->url;
            $segments = explode('/', trim($url, '/'));

            if (count($segments) === 1) {
                // Top-level page
                $tree[$url] = [
                    'url' => $url,
                    'title' => $page->title,
                    'children' => []
                ];
            } else {
                // Child page - store for second pass
                $childPages[] = [
                    'url' => $url,
                    'title' => $page->title,
                    'segments' => $segments
                ];
            }
        }

        // Second pass: attach children to parents
        foreach ($childPages as $child) {
            $parentUrl = '/' . $child['segments'][0];

            if (isset($tree[$parentUrl])) {
                $tree[$parentUrl]['children'][] = [
                    'url' => $child['url'],
                    'title' => $child['title']
                ];
            } else {
                // Parent doesn't exist, add as top-level
                $tree[$child['url']] = [
                    'url' => $child['url'],
                    'title' => $child['title'],
                    'children' => []
                ];
            }
        }

        // Sort children within each parent
        foreach ($tree as &$item) {
            usort($item['children'], function ($a, $b) {
                return strcmp($a['title'], $b['title']);
            });
        }

        // Sort top-level by title
        uasort($tree, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });

        return array_values($tree);
    }
}
