<?php

namespace ravesoft\assets;

use yii\web\AssetBundle;

/**
 * Class MetisMenuAsset
 * @package ravesoft\assets
 */
class MetisMenuAsset extends AssetBundle
{
    public $sourcePath = '@bower/metismenu/dist';
    public $js = ['metisMenu.js'];
    public $css = ['metisMenu.css'];
    public $depends = ['yii\web\JqueryAsset'];

}
