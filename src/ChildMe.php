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
use craft\models\EntryType;
use craft\services\Plugins;
use craft\web\View;

use mmikkel\childme\events\DefineEntryTypesEvent;

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
     * @event DefineEntryTypesEvent The event that is triggered when defining the available entry types for a section
     * @since 1.2.0
     */
    const EVENT_DEFINE_ENTRY_TYPES = 'defineEntryTypes';

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

        $request = Craft::$app->getRequest();

        if (!$request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            return;
        }

        // Handler: EVENT_AFTER_LOAD_PLUGINS
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            function () {
                $this->doIt();
            }
        );

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
    protected function doIt()
    {
        $user = Craft::$app->getUser();
        if (!$user->id) {
            return;
        }
        $this->addElementTableAttributes();
        $this->registerResources();
    }

    /**
     *
     */
    protected function registerResources()
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function () {
                try {
                    // Map section and entry type IDs to entry type names
                    $entryTypesMap = [];
                    $sections = Craft::$app->getSections()->getAllSections();
                    foreach ($sections as $section) {
                        // Give plugins a chance to modify the available entry types
                        $event = new DefineEntryTypesEvent([
                            'section' => $section->handle,
                            'entryTypes' => $section->getEntryTypes(),
                        ]);
                        Event::trigger(static::class, self::EVENT_DEFINE_ENTRY_TYPES, $event);
                        $entryTypesMap[$section->handle] = \array_reduce(\array_values($event->entryTypes ?? []), function (array $carry, EntryType $entryType) {
                            $carry["id:{$entryType->id}"] = Craft::t('site', $entryType->name);
                            return $carry;
                        }, []);
                    }
                    // Map site IDs to site handles
                    $siteMap = [];
                    $sites = Craft::$app->getSites()->getAllSites();
                    foreach ($sites as $site) {
                        if (!$site->primary) {
                            $siteMap['site:' . $site->id] = $site->handle;
                        }
                    }
                    $data = [
                        'entryTypes' => $entryTypesMap,
                        'sites' => $siteMap,
                        'isCraft34' => \version_compare(Craft::$app->getVersion(), '3.4.0', '>='),
                    ];
                    Craft::$app->getView()->registerAssetBundle(ChildMeBundle::class);
                    Craft::$app->getView()->registerJs('Craft.ChildMePlugin.init(' . Json::encode($data) . ')');
                } catch (InvalidConfigException $e) {
                    Craft::error(
                        'Error registering AssetBundle - ' . $e->getMessage(),
                        __METHOD__
                    );
                }
            }
        );
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

                            /** @var Entry $entry */
                            $entry = $event->sender;
                            $section = $entry->getSection();

                            if ($section->type !== 'structure') {
                                break;
                            }

                            $maxLevels = $section->maxLevels;
                            $visible = !$maxLevels || $entry->level < $maxLevels;

                            // Give plugins a chance to modify the available entry types
                            $entryTypesEvent = new DefineEntryTypesEvent([
                                'section' => $section->handle,
                                'entryTypes' => $section->getEntryTypes(),
                            ]);

                            Event::trigger(static::class, self::EVENT_DEFINE_ENTRY_TYPES, $entryTypesEvent);

                            $entryTypes = \array_values($entryTypesEvent->entryTypes ?? [$entry->getType()]);

                            $entryType = $entryTypes[0];

                            $variables = [
                                'typeId' => $entryType->id,
                                'parentId' => $entry->id,
                            ];

                            $attributes = [
                                'data-section="' . $section->handle . '"',
                                'data-id="' . $entry->id . '"',
                            ];

                            if (Craft::$app->getIsMultiSite()) {
                                $site = $entry->getSite();
                                $variables['site'] = $site->handle;
                                $attributes[] = 'data-site="' . $site->handle . '"';
                            }

                            $newUrl = UrlHelper::cpUrl(implode('/', ['entries', $entry->section->handle, 'new']), $variables);

                            $html = $this->getElementTableAttributeHtml($newUrl, $visible, $attributes);

                            break;
                        case 'craft\elements\Category':

                            /** @var Category $category */
                            $category = $event->sender;
                            $maxLevels = $category->group->maxLevels;
                            $visible = !$maxLevels || $category->level < $maxLevels;

                            $variables = [
                                'parentId' => $category->id,
                            ];

                            $urlSegments = ['categories', $category->group->handle, 'new'];

                            if (Craft::$app->getIsMultiSite()) {
                                $site = $category->getSite();
                                $variables['site'] = $site->handle;
                                $urlSegments[] = $site->handle;
                            }

                            $newUrl = UrlHelper::cpUrl(implode('/', $urlSegments), $variables);

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
