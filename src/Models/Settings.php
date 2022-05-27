<?php
/**
 * ontheflyintegration plugin for Craft CMS 3.x
 *
 * ontheflyintegration
 *
 * @link      http://wearesugarrush.co/
 * @copyright Copyright (c) 2022 Sugar Rush
 */

namespace OnTheFlyConfigurator\Models;

use craft\base\Model;

class Settings extends Model
{
    public $apiKey = '';
    public $subDomain = '';

    public function rules()
    {
        return [
            ['apiKey', 'string'],
            ['subDomain', 'string'],
        ];
    }
}
