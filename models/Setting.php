<?php namespace Albrightlabs\SitemapRobotsHumans\Models;

use Model;

class Setting extends Model
{
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
}
