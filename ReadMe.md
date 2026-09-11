# 📃 Sitemap Robots Humans Plugin

### 🚨 Requires OctoberCMS 3.0+

## ✨ What does this plugin do?
Generates the discovery files a website needs, all managed from Settings > Sitemap, Robots, Humans:

- **sitemap.xml**: a sitemap index pointing at `sitemap_pages.xml`, which lists your site's pages automatically
- **robots.txt**: served from content you enter in the backend
- **humans.txt**: served from content you enter in the backend
- **llms.txt**: a curated markdown index of your pages for AI crawlers
- **llms-full.txt**: the full content of opted-in pages rendered as markdown

Each file can be enabled or disabled independently from its own settings tab.

### 🗺️ What goes into the sitemap
- **CMS theme pages**: every page in the active theme, including pages created in the backend CMS Editor. Hidden pages are left out. Pages whose URL parameters are all optional (e.g. `/cars/:path?`) are listed at their base URL (`/cars`); pages with a required parameter (e.g. `/blog/:slug`) are left out.
- **Theme content files**: files in a theme's `content` folders, as managed in the CMS Editor. Add a folder under **Theme Content Folders** with the URL prefix its files are served at, e.g. `cars` at `/cars` lists `content/cars/accord.htm` as `/cars/accord` (or `/cars/accord.htm` with **Include file extension in URL** checked).
- **RainLab.Pages**: static pages, when the plugin is installed and the option is enabled.
- **OFFLINE.Boxes**: published Boxes pages, when the plugin is installed and the option is enabled.
- **RainLab.Blog / AlbrightLabs.Blog**: published posts, and optionally categories, using a configurable URL prefix. The installed blog plugin is detected automatically.
- **Tailor**: entries from any Tailor sections you add, each with its own URL prefix, priority, and change frequency.

### 📝 Per-page sitemap settings for theme pages
When the sitemap is enabled, a **Sitemap** tab is added to the page settings in the CMS Editor with:

- **Include in sitemap**: uncheck to leave the page out
- **Sitemap priority**: 1.0 to 0.2, default 0.5
- **Sitemap change frequency**: daily, weekly, monthly (default), or yearly

These are saved in the page's settings section, so you can also set them by hand in the template file:

```ini
title = "About"
url = "/about"
enabled_in_sitemap = 1
priority = "0.8"
changefreq = "yearly"
```

### 🚫 Excluding URLs
- Add URL patterns (one per line) to **Excluded URLs** on the Sitemap tab. Any URL containing a pattern is left out.
- URLs containing `404`, `error`, or `maintenance` are always excluded.
- Other plugins can veto URLs by listening for the `albrightlabs.sitemap.excludeUrl` event and returning `true`:

```php
Event::listen('albrightlabs.sitemap.excludeUrl', function ($url) {
    return str_starts_with($url, '/coming-soon');
});
```

### 🤖 llms.txt and llms-full.txt
- Pages are opted in by adding `enabled_in_llms = 1` to their settings section.
- **llms.txt** can include a summary blockquote and group links into sections by URL prefix.
- **llms-full.txt** extracts each page's main content using a configurable CSS selector (default `main`). The **Cache TTL** field controls how long generated output is cached; it is shown on the LLMs-full.txt tab when llms-full.txt is enabled, and also applies to llms.txt.

### 🧩 Sitemap component
The `sitemap` component renders a human-readable, hierarchical list of your theme pages for use on an HTML sitemap page.

## ❓ Why would I use this plugin?
Save time by not having to generate a sitemap.xml file for the website or worry about changing it each time a page is added or updated.
Easily maintain robots.txt, humans.txt, and AI discovery files from the CMS settings panel.

## 🖥️ How do I install this plugin?
1. Clone this repository into `plugins/albrightlabs/sitemaprobotshumans`
2. Run `composer update` from the project root to install the `league/html-to-markdown` dependency
3. Run the console command `php artisan october:migrate`
4. From the admin area, go to Settings > Sitemap, Robots, Humans and enable the features you need from each tab.
5. If applicable, add content to the robots.txt and humans.txt input fields.

## ⏫ How do I update this plugin?
Run either of the following commands:
* From the project root, run `php artisan october:util git pull`
* From the plugin root, run `git pull`

## 🚨 Are there any requirements for this plugin?
- OctoberCMS 3.0+
- `league/html-to-markdown` ^5.1 (installed via Composer)

RainLab.Pages, OFFLINE.Boxes, RainLab.Blog, AlbrightLabs.Blog, and Tailor integrations are all optional and only used when present.

## ✨ Future plans
* Feel free to make requests by emailing them to [support@albrightlabs.com](mailto:support@albrightlabs.com)
