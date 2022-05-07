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
use craft\events\TemplateEvent;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use craft\models\Section;
use craft\web\Application;
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

    /**
     * @event DefineEntryTypesEvent The event that is triggered when defining the available entry types for a section
     * @since 1.2.0
     */
    public const EVENT_DEFINE_ENTRY_TYPES = 'defineEntryTypes';

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        $request = Craft::$app->getRequest();
        if (!$request->getIsCpRequest() || $request->getIsConsoleRequest() || $request->getIsLoginRequest()) {
            return;
        }

        Event::on(
            Application::class,
            Application::EVENT_INIT,
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
     * @return void
     */
    protected function doIt(): void
    {
        $user = Craft::$app->getUser();
        if (!$user->id) {
            return;
        }
        $this->addElementTableAttributes();
        $this->registerAssetBundle();
    }

    /**
     * @return void
     */
    protected function registerAssetBundle(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function (TemplateEvent $event) {

                if ($event->templateMode !== View::TEMPLATE_MODE_CP) {
                    return;
                }

                // Map section and entry type IDs to entry type names
                $entryTypesMap = [];
                $sections = Craft::$app->getSections()->getAllSections();
                foreach ($sections as $section) {

                    if ($section->type !== Section::TYPE_STRUCTURE) {
                        continue;
                    }

                    $entryTypes = $section->getEntryTypes();

                    // Give plugins a chance to modify the available entry types
                    if ($this->hasEventHandlers(self::EVENT_DEFINE_ENTRY_TYPES)) {
                        $event = new DefineEntryTypesEvent([
                            'section' => $section->handle,
                            'entryTypes' => $section->getEntryTypes(),
                        ]);
                        $this->trigger(self::EVENT_DEFINE_ENTRY_TYPES, $event);
                        $entryTypes = $event->entryTypes ?? [];
                    }

                    $entryTypesMap[$section->handle] = \array_reduce(\array_values($entryTypes), function (array $carry, EntryType $entryType) {
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
                ];

                Craft::$app->getView()->registerAssetBundle(ChildMeBundle::class);
                Craft::$app->getView()->registerJs('Craft.ChildMePlugin.init(' . Json::encode($data) . ')');
            }
        );
    }

    /**
     *  Add element table attributes
     */
    protected function addElementTableAttributes()
    {
        $segments = Craft::$app->getRequest()->getSegments();
        $actionSegment = $segments[(is_array($segments) || $segments instanceof \Countable ? count($segments) : 0) - 1] ?? null;

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
                        case Entry::class:

                            /** @var Entry $entry */
                            $entry = $event->sender;
                            $section = $entry->getSection();

                            if ($section->type !== Section::TYPE_STRUCTURE) {
                                break;
                            }

                            $maxLevels = $section->maxLevels;
                            $visible = !$maxLevels || $entry->level < $maxLevels;

                            // Give plugins a chance to modify the available entry types
                            if ($this->hasEventHandlers(self::EVENT_DEFINE_ENTRY_TYPES)) {
                                $this->trigger(self::EVENT_DEFINE_ENTRY_TYPES, new DefineEntryTypesEvent([
                                    'section' => $section->handle,
                                    'entryTypes' => $section->getEntryTypes(),
                                ]));
                            }

                            $entryTypes = \array_values($entryTypesEvent->entryTypes ?? [$entry->getType()]);

                            $entryType = $entryTypes[0];

                            $variables = [
                                'typeId' => $entryType->id,
                                'parentId' => $entry->getId(),
                            ];

                            $attributes = [
                                'data-section="' . $section->handle . '"',
                                'data-id="' . $entry->getId() . '"',
                            ];

                            if (Craft::$app->getIsMultiSite()) {
                                $site = $entry->getSite();
                                $variables['site'] = $site->handle;
                                $attributes[] = 'data-site="' . $site->handle . '"';
                            }

                            $newUrl = UrlHelper::cpUrl(implode('/', ['entries', $entry->getSection()->handle, 'new']), $variables);

                            $html = $this->getElementTableAttributeHtml($newUrl, $visible, $attributes);

                            break;

                        case Category::class:

                            /** @var Category $category */
                            $category = $event->sender;
                            $maxLevels = $category->getGroup()->maxLevels;
                            $visible = !$maxLevels || $category->level < $maxLevels;

                            $variables = [
                                'parentId' => $category->getId(),
                            ];

                            $urlSegments = ['categories', $category->getGroup()->handle, 'new'];

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
        return '<span><a data-childmeadd data-href="' . $newUrl . '" data-icon="plus" ' . implode(' ', $attrs) . 'title="' . Craft::t('child-me', 'Add child') . '"' . ($visible ? '' : ' style="display:none;" aria-hidden="true" tabindex="-1"') . '></a></span>';
    }

}
