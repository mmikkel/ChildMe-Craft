<?php
/**
 * Child Me! plugin for Craft CMS 3.x
 *
 * Easily create child elements
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2017 Mats Mikkel Rummelhoff
 */

namespace mmikkel\childme;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\elements\Category;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\records\EntryType as EntryTypeRecord;

use yii\base\Event;

/**
 * Class ChildMe
 *
 * @author    Mats Mikkel Rummelhoff
 * @package   ChildMe
 * @since     1.0.0
 *
 */
class ChildMe extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var ChildMe
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $user = Craft::$app->getUser();
        $request = Craft::$app->getRequest();

        if (!$user || !$user->id || !$request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            return;
        }

        $this->registerResources();
        $this->addElementTableAttributes();

        Craft::info(
            Craft::t(
                'child-me',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     *
     */
    protected function registerResources()
    {
        $entryTypes = EntryTypeRecord::find()->all();

        $data = [];
        foreach ($entryTypes as $entryType) {
            $section = $entryType->section->handle;
            if (!isset($data[$section])) $data[$section] = [];
            $data[$section][$entryType->id] = $entryType->name;
        }

        Craft::$app->getView()->registerAssetBundle(ChildMeBundle::class);
        Craft::$app->getView()->registerJs('Craft.ChildMePlugin.init(' . Json::encode($data) . ')');
    }

    /**
     *  Add element table attributes
     */
    protected function addElementTableAttributes()
    {
        $segments = Craft::$app->getRequest()->getSegments();
        $actionSegment = $segments[count($segments) - 1] ?? null;

        $classes = [Entry::class, Category::class];

        foreach ($classes as $class) {

            // Register "Add child" attribute
            Event::on($class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function (RegisterElementTableAttributesEvent $event) use ($actionSegment) {
                $event->tableAttributes['_childme_addChild'] = $actionSegment !== 'get-elements' ? Craft::t('child-me', 'Add child') : '';
            });

            // Get the HTML for that attribute
            Event::on($class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, function (SetElementTableAttributeHtmlEvent $event) use ($class) {
                if ($event->attribute === '_childme_addChild') {

                    $html = '';

                    switch ($class) {
                        case 'craft\elements\Entry':

                            $entry = $event->sender;

                            if ($entry->section->type !== 'structure') {
                                break;
                            }

                            $maxLevels = $entry->section->maxLevels;
                            $visible = !$maxLevels || $entry->level < $maxLevels;

                            $newUrl = UrlHelper::cpUrl(implode('/', ['entries', $entry->section->handle, 'new']), [
                                'typeId' => $entry->type->id,
                                'parentId' => $entry->id,
                            ]);

                            $html = $this->getElementTableAttributeHtml($newUrl, $visible, [
                                'data-section="' . $entry->section->handle . '"',
                                'data-id="' . $entry->id . '"'
                            ]);

                            break;
                        case 'craft\elements\Category':

                            $category = $event->sender;
                            $maxLevels = $category->group->maxLevels;
                            $visible = !$maxLevels || $category->level < $maxLevels;

                            $newUrl = UrlHelper::cpUrl(implode('/', ['categories', $category->group->handle, 'new']), [
                                'parentId' => $category->id,
                            ]);

                            $html = $this->getElementTableAttributeHtml($newUrl, $visible);

                            break;
                    }

                    $event->html = $html;
                    $event->handled = true;

                }


            });
        }


    }

    /**
     * @param $newUrl
     * @param bool $visible
     * @param array $attrs
     * @return string
     */
    protected function getElementTableAttributeHtml($newUrl, $visible = true, $attrs = [])
    {
        return '<span><a href="' . $newUrl . '" data-icon="plus" data-childmeadd ' . implode(' ', $attrs) . 'title="' . Craft::t('child-me', 'Add child') . '"' . (!$visible ? ' style="display:none;" aria-hidden="true" tabindex="-1"' : '') . '></a></span>';
    }

}
