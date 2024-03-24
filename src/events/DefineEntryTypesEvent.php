<?php

namespace mmikkel\childme\events;

use craft\elements\Entry;

use yii\base\Event;

class DefineEntryTypesEvent extends Event
{

    /** @var Entry|null */
    public ?Entry $entry = null;

    /** @var string|null */
    public ?string $section = null;

    /** @var array */
    public array $entryTypes = [];

}
