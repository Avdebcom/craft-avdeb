<?php

namespace avdeb\craftavdeb\assets;

use craft\web\AssetBundle;
use craft\web\View;

/**
 * The embed stylesheet and the carousel script (~50 lines, no network, no cookies).
 */
class EmbedAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = dirname(__DIR__) . '/web';
        $this->css = ['avdeb.css'];
        $this->js = ['avdeb.js'];
        $this->jsOptions = ['position' => View::POS_END, 'defer' => true];
        parent::init();
    }
}
