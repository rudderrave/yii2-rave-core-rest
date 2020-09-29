<?php
use ravesoft\Rave;

/* @var $this yii\web\View */
?>

<div class="pull-<?= $position ?> col-lg-<?= $width ?> widget-height-<?= $height ?>">
    <div class="panel panel-default">
        <div class="panel-heading"><?= Yii::t('rave', 'System Info') ?></div>
        <div class="panel-body">
            <b><?= Yii::t('rave', 'Rave CMS Version') ?>:</b> <?= Yii::$app->params['version']; ?><br/>
            <b><?= Yii::t('rave', 'Rave Core Version') ?>:</b> <?= Rave::getVersion(); ?><br/>
            <b><?= Yii::t('rave', 'Yii Framework Version') ?>:</b> <?= Yii::getVersion(); ?><br/>
            <b><?= Yii::t('rave', 'PHP Version') ?>:</b> <?= phpversion(); ?><br/>
        </div>
    </div>
</div>