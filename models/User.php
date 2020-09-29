<?php

namespace ravesoft\models;

use ravesoft\helpers\AuthHelper;
use ravesoft\helpers\RaveHelper;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "user".
 *
 * @property integer $id
 * @property string $username
 * @property string $email
 * @property integer $email_confirmed
 * @property string $auth_key
 * @property string $password_hash
 * @property string $confirmation_token
 * @property string $bind_to_ip
 * @property string $registration_ip
 * @property integer $status
 * @property integer $superadmin
 * @property string $avatar
 * @property integer $created_at
 * @property integer $updated_at
 */
class User extends UserIdentity
{

    const STATUS_ACTIVE = 10;
    const STATUS_INACTIVE = 0;
    const STATUS_BANNED = -1;
    const SCENARIO_NEW_USER = 'newUser';
    const GENDER_NOT_SET = 0;
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;

    /**
     * @var string
     */
    public $gridRoleSearch;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $repeat_password;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        if(!Yii::$app->rave->auth) {
            return Yii::$app->rave->user_table;
        }
        return '';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['username', 'email'], 'required'],
            ['username', 'unique'],
            ['username', 'match', 'pattern' => Yii::$app->rave->usernameRegexp, 'message' => Yii::t('rave/auth', 'The username should contain only Latin letters, numbers and the following characters: "-" and "_".')],
            ['username', 'match', 'not' => true, 'pattern' => Yii::$app->rave->usernameBlackRegexp, 'message' => Yii::t('rave/auth', 'Username contains not allowed characters or words.')],
            [['username', 'email', 'bind_to_ip'], 'trim'],
            [['status', 'email_confirmed'], 'integer'],
            ['email', 'email'],
            ['email', 'validateEmailUnique'],
            ['bind_to_ip', 'validateBindToIp'],
            ['bind_to_ip', 'string', 'max' => 255],
            [['first_name', 'last_name'], 'string', 'max' => 124],
            [['skype'], 'string', 'max' => 64],
            [['phone'], 'string', 'max' => 24],
            [['bind_to_ip', 'info'], 'string', 'max' => 255],
            ['gender', 'integer'],
            ['birth_day', 'integer', 'max' => 31],
            ['birth_month', 'integer', 'max' => 12],
            ['birth_year', 'integer', 'max' => 2099],
            [['birth_month', 'birth_day'], 'integer', 'min' => 1],
            ['birth_year', 'integer', 'min' => 1880],
            ['password', 'required', 'on' => [self::SCENARIO_NEW_USER, 'changePassword']],
            ['password', 'string', 'max' => 255, 'on' => [self::SCENARIO_NEW_USER, 'changePassword']],
            ['password', 'string', 'min' => 6, 'on' => [self::SCENARIO_NEW_USER, 'changePassword']],
            ['password', 'trim', 'on' => [self::SCENARIO_NEW_USER, 'changePassword']],
            ['repeat_password', 'required', 'on' => [self::SCENARIO_NEW_USER, 'changePassword']],
            ['repeat_password', 'compare', 'compareAttribute' => 'password'],
        ];
    }

    /**
     * Store result in session to prevent multiple db requests with multiple calls
     *
     * @param bool $fromSession
     *
     * @return static
     */
    public static function getCurrentUser($fromSession = true)
    {
        if (!$fromSession) {
            return static::findOne(Yii::$app->user->id);
        }

        $user = Yii::$app->session->get('__currentUser');

        if (!$user) {
            $user = static::findOne(Yii::$app->user->id);

            Yii::$app->session->set('__currentUser', $user);
        }

        return $user;
    }

    /**
     * Assign role to user
     *
     * @param int $userId
     * @param string $roleName
     *
     * @return bool
     */
    public static function assignRole($userId, $roleName)
    {
        try {
            Yii::$app->db->createCommand()
                    ->insert(Yii::$app->rave->auth_assignment_table, [
                        'user_id' => $userId,
                        'item_name' => $roleName,
                        'created_at' => time(),
                    ])->execute();

            AuthHelper::invalidatePermissions();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Assign roles to user
     *
     * @param int $userId
     * @param array $roles
     *
     * @return bool
     */
    public function assignRoles(array $roles)
    {
        foreach ($roles as $role) {
            User::assignRole($this->id, $role);
        }
    }

    /**
     * Revoke role from user
     *
     * @param int $userId
     * @param string $roleName
     *
     * @return bool
     */
    public static function revokeRole($userId, $roleName)
    {
        $result = Yii::$app->db->createCommand()
                        ->delete(Yii::$app->rave->auth_assignment_table, ['user_id' => $userId, 'item_name' => $roleName])
                        ->execute() > 0;

        if ($result) {
            AuthHelper::invalidatePermissions();
        }

        return $result;
    }

    /**
     * @param string|array $roles
     * @param bool $superAdminAllowed
     *
     * @return bool
     */
    public static function hasRole($roles, $superAdminAllowed = true)
    {
        if ($superAdminAllowed AND Yii::$app->user->isSuperadmin) {
            return true;
        }
        $roles = (array) $roles;

        AuthHelper::ensurePermissionsUpToDate();

        return array_intersect($roles, Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROLES, [])) !== [];
    }

    /**
     * @param string $permission
     * @param bool $superAdminAllowed
     *
     * @return bool
     */
    public static function hasPermission($permission, $superAdminAllowed = true)
    {
        if ($superAdminAllowed AND Yii::$app->user->isSuperadmin) {
            return true;
        }

        AuthHelper::ensurePermissionsUpToDate();

        return in_array($permission, Yii::$app->session->get(AuthHelper::SESSION_PREFIX_PERMISSIONS, []));
    }

    /**
     * Useful for Menu widget
     *
     * <example>
     *    ...
     *        [ 'label'=>'Some label', 'url'=>['/site/index'], 'visible'=>User::canRoute(['/site/index']) ]
     *    ...
     * </example>
     *
     * @param string|array $route
     * @param bool $superAdminAllowed
     *
     * @return bool
     */
    public static function canRoute($route, $superAdminAllowed = true)
    {
        if ($superAdminAllowed AND Yii::$app->user->isSuperadmin) {
            return true;
        }

        $baseRoute = AuthHelper::unifyRoute($route);

        if (substr($baseRoute, 0, 4) === "http") {
            return true;
        }

        if (Route::isFreeAccess($baseRoute)) {
            return true;
        }

        AuthHelper::ensurePermissionsUpToDate();

        return Route::isRouteAllowed($baseRoute, Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROUTES, []));
    }

    /**
     * getStatusList
     * @return array
     */
    public static function getStatusList()
    {
        return array(
            self::STATUS_ACTIVE => Yii::t('rave', 'Active'),
            self::STATUS_INACTIVE => Yii::t('rave', 'Inactive'),
            self::STATUS_BANNED => Yii::t('rave', 'Banned'),
        );
    }

    /**
     * Get gender list
     * @return array
     */
    public static function getGenderList()
    {
        return array(
            self::GENDER_NOT_SET => Yii::t('yii', '(not set)'),
            self::GENDER_MALE => Yii::t('rave', 'Male'),
            self::GENDER_FEMALE => Yii::t('rave', 'Female'),
        );
    }

    /**
     * getUsersList
     *
     * @return array
     */
    public static function getUsersList()
    {
        $users = static::find()->select(['id', 'username'])->asArray()->all();
        return ArrayHelper::map($users, 'id', 'username');
    }

    /**
     * getStatusValue
     *
     * @param string $val
     *
     * @return string
     */
    public static function getStatusValue($val)
    {
        $ar = self::getStatusList();

        return isset($ar[$val]) ? $ar[$val] : $val;
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Check that there is no such confirmed E-mail in the system
     */
    public function validateEmailUnique()
    {
        if ($this->email) {
            $exists = User::findOne(['email' => $this->email]);

            if ($exists AND $exists->id != $this->id) {
                $this->addError('email', Yii::t('rave', 'This e-mail already exists'));
            }
        }
    }

    /**
     * Validate bind_to_ip attr to be in correct format
     */
    public function validateBindToIp()
    {
        if ($this->bind_to_ip) {
            $ips = explode(',', $this->bind_to_ip);

            foreach ($ips as $ip) {
                if (!filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    $this->addError('bind_to_ip', Yii::t('rave', "Wrong format. Enter valid IPs separated by comma"));
                }
            }
        }
    }

    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('rave', 'ID'),
            'username' => Yii::t('rave', 'Login'),
            'superadmin' => Yii::t('rave', 'Superadmin'),
            'confirmation_token' => Yii::t('rave', 'Confirmation Token'),
            'registration_ip' => Yii::t('rave', 'Registration IP'),
            'bind_to_ip' => Yii::t('rave', 'Bind to IP'),
            'status' => Yii::t('rave', 'Status'),
            'gridRoleSearch' => Yii::t('rave', 'Roles'),
            'created_at' => Yii::t('rave', 'Created'),
            'updated_at' => Yii::t('rave', 'Updated'),
            'password' => Yii::t('rave', 'Password'),
            'repeat_password' => Yii::t('rave', 'Repeat password'),
            'email_confirmed' => Yii::t('rave', 'E-mail confirmed'),
            'email' => Yii::t('rave', 'E-mail'),
            'first_name' => Yii::t('rave', 'First Name'),
            'last_name' => Yii::t('rave', 'Last Name'),
            'skype' => Yii::t('rave', 'Skype'),
            'phone' => Yii::t('rave', 'Phone'),
            'gender' => Yii::t('rave', 'Gender'),
            'birth_day' => Yii::t('rave', 'Birthday'),
            'birth_month' => Yii::t('rave', 'Birth month'),
            'birth_year' => Yii::t('rave', 'Birth year'),
            'info' => Yii::t('rave', 'Short Info'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRoles()
    {
        return $this->hasMany(Role::className(), ['name' => 'item_name'])
                        ->viaTable(Yii::$app->rave->auth_assignment_table, ['user_id' => 'id']);
    }

    /**
     * Make sure user will not deactivate himself and superadmin could not demote himself
     * Also don't let non-superadmin edit superadmin
     *
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            if (php_sapi_name() != 'cli') {
                $this->registration_ip = RaveHelper::getRealIp();
            }
            $this->generateAuthKey();
        } else {
            // Console doesn't have Yii::$app->user, so we skip it for console
            if (php_sapi_name() != 'cli') {
                if (Yii::$app->user->id == $this->id) {
                    // Make sure user will not deactivate himself
                    $this->status = static::STATUS_ACTIVE;

                    // Superadmin could not demote himself
                    if (Yii::$app->user->isSuperadmin AND $this->superadmin != 1) {
                        $this->superadmin = 1;
                    }
                }

                // Don't let non-superadmin edit superadmin
                if (!Yii::$app->user->isSuperadmin AND $this->oldAttributes['superadmin'] == 1
                ) {
                    return false;
                }
            }
        }

        // If password has been set, than create password hash
        if ($this->password) {
            $this->setPassword($this->password);
        }

        return parent::beforeSave($insert);
    }

    /**
     * Don't let delete yourself and don't let non-superadmin delete superadmin
     *
     * @inheritdoc
     */
    public function beforeDelete()
    {
        // Console doesn't have Yii::$app->user, so we skip it for console
        if (php_sapi_name() != 'cli') {
            // Don't let delete yourself
            if (Yii::$app->user->id == $this->id) {
                return false;
            }

            // Don't let non-superadmin delete superadmin
            if (!Yii::$app->user->isSuperadmin AND $this->superadmin == 1) {
                return false;
            }
        }

        return parent::beforeDelete();
    }

    /**
     * @inheritdoc
     * @return PostQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new UserQuery(get_called_class());
    }

    /**
     * Get created date
     *
     * @return string
     */
    public function getCreatedDate()
    {
        return Yii::$app->formatter->asDate(($this->isNewRecord) ? time() : $this->created_at);
    }

    /**
     * Get created date
     *
     * @return string
     */
    public function getUpdatedDate()
    {
        return Yii::$app->formatter->asDate(($this->isNewRecord) ? time() : $this->updated_at);
    }

    /**
     * Get created time
     *
     * @return string
     */
    public function getCreatedTime()
    {
        return Yii::$app->formatter->asTime(($this->isNewRecord) ? time() : $this->updated_at);
    }

    /**
     * Get created time
     *
     * @return string
     */
    public function getUpdatedTime()
    {
        return Yii::$app->formatter->asTime(($this->isNewRecord) ? time() : $this->updated_at);
    }

    /**
     * Get created datetime
     *
     * @return string
     */
    public function getCreatedDatetime()
    {
        return "{$this->createdDate} {$this->createdTime}";
    }

    /**
     * Get created datetime
     *
     * @return string
     */
    public function getUpdatedDatetime()
    {
        return "{$this->updatedDate} {$this->updatedTime}";
    }

    /**
     * @param string $size
     * @return boolean|string
     */
    public function getAvatar($size = 'small')
    {
        if (!empty($this->avatar)) {
            $avatars = json_decode($this->avatar);

            if (isset($avatars->$size)) {
                return $avatars->$size;
            }
        }

        return false;
    }

    /**
     *
     * @param array $avatars
     */
    public function setAvatars($avatars)
    {
        $this->avatar = json_encode($avatars);
        return $this->save();
    }

    /**
     *
     */
    public function removeAvatar()
    {
        $this->avatar = '';
        return $this->save();
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        if(!Yii::$app->rave->auth) {
            return parent::attributes();
        }
        return [
            "id",
            "username",
            "auth_key",
            "password_hash",
            "password_reset_token",
            "email",
            "status",
            "created_at",
            "updated_at",
            "superadmin",
            "registration_ip",
            "bind_to_ip",
            "email_confirmed",
            "confirmation_token",
            "avatar",
            "first_name",
            "last_name",
            "birth_day",
            "birth_month",
            "birth_year",
            "gender",
            "phone",
            "skype",
            "info"
        ];
    }

    /**
     * @inheritdoc
     */
    public function load($data, $formName = NULL)
    {
        if(!Yii::$app->rave->auth) {
            return parent::load($data, $formName);
        }

        if (!empty($data)) {
            $this->setAttributes($data,false);
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     *
     */
    public static function findIdentity($id)
    {
        if(!Yii::$app->rave->auth) {
            return static::findOne($id);
        }

        $response = (new RestClient())->CreateRequest()
            ->setUrl('permission/find-by-id')
            ->setData(['id' => $id])
            ->send();
        $resp = $response->getData();
        $newUser = new User();
        $newUser->load($resp);
        return $newUser;
    }

    /**
     * Finds user by username
     *
     * @param  string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        if(!Yii::$app->rave->auth) {
            return static::findOne(['username' => $username, 'status' => User::STATUS_ACTIVE]);
        }
        try {
            $response = (new RestClient())->CreateRequest()
                ->setUrl('permission/find-by-username')
                ->setData(['username' => $username])
                ->send();
            $resp = $response->getData();
            $newUser = new User();
            $newUser->load($resp);
            return $newUser;
        } catch (\Exception $exception) {
            return null;
        }

    }

}
