<?php

namespace ravesoft\controllers\admin;

use yii\helpers\ArrayHelper;

class DashboardController extends BaseController
{
    /**
     * @inheritdoc
     */
    public $enableOnlyActions = ['index'];
    public $widgets = NULL;

    public function actions()
    {
        if ($this->widgets === NULL) {
            $this->widgets = [];
        }

        return ArrayHelper::merge(parent::actions(), [
            'index' => [
                'class' => 'ravesoft\web\DashboardAction',
                'widgets' => $this->widgets,
            ]
        ]);
    }
}