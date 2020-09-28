<?php

namespace ravesoft\db;

/**
 * @inheritdoc
 */
class ActiveRecord extends \yii\db\ActiveRecord
{
    
    /**
     * Returns TRUE if model support multilingual behavior.
     *
     * @param ActiveRecord $model
     * @return boolean
     */
    public function isMultilingual()
    {
        return ($this->getBehavior('multilingual') !== NULL);
    }
    
}
