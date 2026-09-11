<?php namespace Albrightlabs\SitemapRobotsHumans\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Setting extends Model
{
    use Validation;

    /**
     * @var array implement these behaviors
     */
    public $implement = [
        \System\Behaviors\SettingsModel::class
    ];

    /**
     * @var string settingsCode unique to this model
     */
    public $settingsCode = 'albrightlabs_sitemaprobotshumans_settings';

    /**
     * @var string settingsFields configuration
     */
    public $settingsFields = 'fields.yaml';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'robots_content' => 'nullable|string|max:5000',
        'humans_content' => 'nullable|string|max:5000'
    ];

    /**
     * @var array Attribute names for validation errors
     */
    public $attributeNames = [
        'robots_content' => 'Robots.txt content',
        'humans_content' => 'Humans.txt content'
    ];

    /**
     * Get available Tailor sections for dropdown
     */
    public function getSectionHandleOptions()
    {
        $options = [];
        try {
            $sections = \Tailor\Classes\BlueprintIndexer::instance()->listSections();
            foreach ($sections as $section) {
                $options[$section->handle] = $section->name . ' (' . $section->handle . ')';
            }
        } catch (\Exception $e) {
            // Tailor not available
        }
        return $options;
    }

    /**
     * Get the active theme's content folders for dropdown
     */
    public function getContentFolderOptions()
    {
        $options = [];
        try {
            $files = \Cms\Classes\Content::listInTheme(\Cms\Classes\Theme::getActiveTheme(), true);
            foreach ($files as $file) {
                $dir = dirname($file->getFileName());
                while ($dir !== '.' && $dir !== '') {
                    $options[$dir] = $dir;
                    $dir = dirname($dir);
                }
            }
        } catch (\Throwable $e) {
            // No active theme
        }
        ksort($options);
        return $options;
    }
}
