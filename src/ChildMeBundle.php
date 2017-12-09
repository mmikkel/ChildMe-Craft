<?php
namespace mmikkel\childme;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ChildMeBundle extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@mmikkel/childme/resources';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'childme.js',
        ];

        $this->css = [
            'childme.css',
        ];

        parent::init();
    }
}
