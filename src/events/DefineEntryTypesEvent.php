<?php

namespace mmikkel\childme\events;

use yii\base\Event;

class DefineEntryTypesEvent extends Event
{

    /**
     * @var string
     */
    public $section;

    /**
     * @var array 
     */
    public $entryTypes = [];

}
