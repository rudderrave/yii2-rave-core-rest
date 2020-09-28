<?php

namespace yeesoft\models;

use Yii;
/**
 * Trait TraitUser
 * @package common\models
 */
trait PostgreSQL {

    /**
     * @param $seq
     * @return mixed
     * @throws \yii\db\Exception
     */
    static public function getNextVal($seq) {

        $data = Yii::$app->db->createCommand("SELECT nextval('".$seq."') as id")
            ->queryScalar();

        if(!$data) {
            throw new \Exception('Error select next value in sequence "'.$seq.'"');
        }
        return $data;
    }

    /**
     * @param $seq
     * @return mixed
     * @throws \yii\db\Exception
     */
    static public function getCurrval($seq) {

        $data = Yii::$app->db->createCommand("SELECT setval('".$seq."',nextval('".$seq."')-1) as id;")
            ->queryScalar();
        if(!$data) {
            throw new \Exception('Error select current value in sequence "'.$seq.'"');
        }
        return $data['id'];
    }
}