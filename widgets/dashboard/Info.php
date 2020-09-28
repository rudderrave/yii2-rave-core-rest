<?php

namespace ravesoft\widgets\dashboard;

use ravesoft\widgets\DashboardWidget;
use ravesoft\models\User;

class Info extends DashboardWidget
{
    public function run()
    {
        if (User::hasPermission('viewDashboard')) {
            return $this->render('info',
                [
                    'height' => $this->height,
                    'width' => $this->width,
                    'position' => $this->position,
                ]);
        }
    }
}