<?php
/**
 * Child Me! plugin for Craft CMS 5.x
 *
 * Easily create child elements
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2024 Mats Mikkel Rummelhoff
 */

namespace mmikkel\childme;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\elements\Category;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\TemplateEvent;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use craft\models\Section;
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

    public function init(): void
    {
        parent::init();

        $request = Craft::$app->getRequest();
        if (!$request->getIsCpRequest() || $request->getIsConsoleRequest() || $request->getIsLoginRequest()) {
            return;
        }

        Craft::$app->onInit(function () {
            $this->_registerEventHandlers();
        });
    }

    private function _registerEventHandlers(): void
    {

        // Register entry and category table attribute HTML for Child Me! buttons
        foreach ([
                     Entry::class,
                     Category::class
                 ] as $elementClass) {

            Event::on(
                $elementClass,
                Element::EVENT_REGISTER_TABLE_ATTRIBUTES,
                static function (RegisterElementTableAttributesEvent $event) {
                    $event->tableAttributes['_childme_addChild'] = Craft::t('app', 'New child');
                }
            );

            // Get the HTML for that attribute
            Event::on(
                $elementClass,
                Element::EVENT_DEFINE_ATTRIBUTE_HTML,
                function (DefineAttributeHtmlEvent $event) use ($elementClass) {
                    if ($event->attribute !== '_childme_addChild') {
                        return;
                    }
                    $element = $event->sender;
                    $html = '';
                    try {
                        if ($element instanceof Entry) {
                            $html = $this->_renderChildMeButtonForEntry($element);
                        } else if ($element instanceof Category) {
                            $html = $this->_renderChildMeButtonForCategory($element);
                        }
                    } catch (\Throwable $e) {
                        Craft::error($e, __METHOD__);
                    }
                    $event->html = $html;
                }
            );

            // Don't break inline editing - https://github.com/craftcms/cms/issues/14639
            Event::on(
                $elementClass,
                Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
                static function (DefineAttributeHtmlEvent $event) {
                    if ($event->attribute === '_childme_addChild') {
                        $event->html = '';
                    }
                }
            );
        }

        // Add some JavasScript
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            static function (TemplateEvent $event) {
                if (
                    $event->templateMode !== View::TEMPLATE_MODE_CP ||
                    !in_array($event->template,  ['entries', 'categories/_index.twig'], true)
                ) {
                    return;
                }
                $js = <<<JS
                    // Init Child Me! entry type disclosure menus
                    $('body').on('click', 'button.childme-button:not([data-disclosure-trigger])', e => {
                        e.target.setAttribute('data-disclosure-trigger', 'true');
                        (new Garnish.DisclosureMenu($(e.target))).show();
                    });
                    // Hook into Craft.ElementTableSorter to hide/show Child Me! buttons when their elements' levels change
                    try {
                        const elementTableSorter = Craft.ElementTableSorter.prototype;
                        const onDragStop = elementTableSorter.onDragStop;
                        elementTableSorter.onDragStop = function () {
                            onDragStop.apply(this, arguments);
                            this.\$items.find('.childme-button').removeClass('hidden');
                            const maxLevels = parseInt(this.settings.maxLevels || null, 10);
                            if (!maxLevels) {
                                return;
                            }
                            this.\$items.filter(function () {
                                return $(this).data('level') >= maxLevels;
                            }).find('.childme-button').addClass('hidden');
                        };
                    } catch (error) {
                        console.error(error);
                    }
                JS;
                Craft::$app->getView()->registerJs($js, View::POS_END);
            }
        );

    }

    /**
     * @param Entry $entry
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    private function _renderChildMeButtonForEntry(Entry $entry): string
    {
        $section = $entry->getSection();

        if ($section?->type !== Section::TYPE_STRUCTURE || $section?->maxLevels === 1) {
            return '';
        }

        // Give plugins a chance to modify the available entry types
        $entryTypes = $section->getEntryTypes();
        if ($this->hasEventHandlers(self::EVENT_DEFINE_ENTRY_TYPES)) {
            $entryTypesEvent = new DefineEntryTypesEvent([
                'entry' => $entry,
                'section' => $section->handle,
                'entryTypes' => $section->getEntryTypes(),
            ]);
            $this->trigger(self::EVENT_DEFINE_ENTRY_TYPES, $entryTypesEvent);
            $entryTypes = array_values($entryTypesEvent->entryTypes ?? []);
        }

        if (empty($entryTypes)) {
            $entryTypes = [$entry->getType()];
        }

        $links = array_map(static function (EntryType $entryType) use ($entry, $section) {
            return [
                'url' => UrlHelper::cpUrl("entries/$section->handle/new", [
                    'site' => $entry->getSite()?->handle,
                    'parentId' => $entry->id,
                    'typeId' => $entryType->id,
                ]),
                'label' => $entryType->name,
            ];
        }, $entryTypes);

        return Craft::$app->getView()->renderTemplate(
            template: 'child-me/childme-button.twig',
            variables: [
                'links' => $links,
                'element' => $entry,
                'hidden' => !empty($section->maxLevels) && $entry->level >= $section->maxLevels,
            ]
        );
    }

    /**
     * @param Category $category
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    private function _renderChildMeButtonForCategory(Category $category): string
    {

        $group = $category->getGroup();

        if ($group->maxLevels === 1) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate(
            template: 'child-me/childme-button.twig',
            variables: [
                'links' => [[
                    'url' => UrlHelper::cpUrl("categories/$group->handle/new", [
                        'site' => $category->getSite()?->handle,
                        'parentId' => $category->id,
                    ])
                ]],
                'element' => $category,
                'hidden' => !empty($group->maxLevels) && $category->level >= $group->maxLevels,
            ]
        );
    }

}
