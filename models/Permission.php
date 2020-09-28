<?php

namespace ravesoft\models;

use Exception;
use ravesoft\helpers\AuthHelper;
use Yii;
use yii\rbac\DbManager;

class Permission extends AbstractItem
{

    const ITEM_TYPE = self::TYPE_PERMISSION;

    /**
     * @param int $userId
     *
     * @return array|\yii\rbac\Permission[]
     */
    public static function getUserPermissions($userId)
    {
        if(!Yii::$app->yee->auth) {
            return (new DbManager())->getPermissionsByUser($userId);
        }

        $response = (new RestClient())->CreateRequest()
            ->setUrl('permission/get-user-permission')
            ->setData(['id' => $userId])
            ->send();
        return $response->getData();
    }

    /**
     * Assign route to permission and create them if they don't exists
     * Helper mainly for migrations
     *
     * @param string $permissionName
     * @param array|string $routes
     * @param null|string $permissionDescription
     * @param null|string $groupCode
     *
     * @throws \InvalidArgumentException
     * @return true|static|string
     */
    public static function assignRoutes($permissionName, $routes, $permissionDescription = null, $groupCode = null)
    {
        $permission = static::findOne(['name' => $permissionName]);
        $routes = (array) $routes;

        if (!$permission) {
            $permission = static::create($permissionName, $permissionDescription, $groupCode);

            if ($permission->hasErrors()) {
                return $permission;
            }
        }

        foreach ($routes as $route) {
            $route = '/' . ltrim($route, '/');
            try {
                Yii::$app->db->createCommand()
                        ->insert(Yii::$app->yee->auth_item_child_table, [
                            'parent' => $permission->name,
                            'child' => $route,
                        ])->execute();
            } catch (Exception $e) {
                // Don't throw Exception because this permission may already have this route,
                // so just go to the next route
            }
        }

        AuthHelper::invalidatePermissions();

        return true;
    }

}
