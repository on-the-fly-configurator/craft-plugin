<?php
/**
 * ontheflyintegration plugin for Craft CMS 3.x
 *
 * ontheflyintegration
 *
 * @link      http://wearesugarrush.co/
 * @copyright Copyright (c) 2022 Sugar Rush
 */

namespace OnTheFlyConfigurator;

use OnTheFlyConfigurator\Classes\Cart;
use OnTheFlyConfigurator\Models\Settings;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

class OnTheFlyIntegration extends Plugin
{
    public static $plugin;
    public $schemaVersion = '1.0.4';
    public $hasCpSettings = true;
    public $hasCpSection = false;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('ontheflyintegration', Cart::class);
            }
        ); 

        Craft::info(
            Craft::t(
                'ontheflyintegration',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'ontheflyintegration/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
