
# 📃 Sitemap Robots Humans Plugin

### 🚨 Requires OctoberCMS 2.0

## ✨ What does this plugin do?
Provides a sitemap.xml, robots.txt, and humans.txt file for a website.
Website managers can install this plugin and go to Settings > Sitemap, Robots, Humans to enable/disable each file and set the robots.txt and humans.txt content.

## ❓ Why would I use this plugin?
Save time by not having to generate a sitemap.xml file for the website or worry about changing it each time a page is added or updated.
Easily maintain a robots.txt and humans.txt file from the CMS settings panel.

## 🖥️ How do I install this plugin?
1. Clone this repository into `plugins/albrightlabs/sitemaprobotshumans`
2. Run the console command `php artisan october:migrate`
3. From the admin area, go to Settings > Sitemap, Robots, Humans and enable the three features from the three tabs shown.
4. If applicable, add content to the robots.txt and humans.txt input fields.

## ⏫ How do I update this plugin?
Run either of the following commands:
* From the project root, run `php artisan october:util git pull`
* From the plugin root, run `git pull`

## 🚨 Are there any requirements for this plugin?
None, other than installation and initial input of settings.

## ⚙️ Explanation of settings
* Enable sitemap/robots/humans: checking the box will enable the feature.
* Robots/Humans content: provide the content for each file.

## ✨ Future plans
* Feel free to make requests by emailing them to [support@albrightlabs.com](support@albrightlabs.com)
