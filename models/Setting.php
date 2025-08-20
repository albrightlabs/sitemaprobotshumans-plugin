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
}
