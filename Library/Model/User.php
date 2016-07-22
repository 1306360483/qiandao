<?php

namespace Model;

use Core\Database;
use Core\Error;
use Core\Model;

/** @Table Member */
class User extends Model
{
    const ROLE_NEW = 'new';
    const ROLE_STANDARD = 'standard';
    const ROLE_ADMIN = 'administrator';
    const ROLE_SUSPENDED = 'suspended';

    public $id;
    public $username;
    public $email;
    public $role = self::ROLE_STANDARD;
    public $nickname = '';
    public $registerTime = TIMESTAMP;
    public $lastActive = TIMESTAMP;
    private $password;

    public static function requiredLogin()
    {
        if (!self::getCurrent()) {
            throw new Error('You have no permission to access this page');
        }
    }

    public static function getCurrent()
    {
        /** @var User $user */
        $user = $_SESSION['currentUser'];
        if ($user && TIMESTAMP - $user->lastActive > 600) {
            $userObj = self::getUserByUserId($user->id);
            if (!$userObj) {
                $user = null;
            } elseif ($user->password != $userObj->password) {
                $user = null;
            } elseif ($user->role == self::ROLE_SUSPENDED) {
                $user = null;
            } else {
                $userObj->lastActive = TIMESTAMP;
                $userObj->save();
                $user = $userObj;
            }
            $_SESSION['currentUser'] = $user;
        }
        return $user;
    }

    /**
     * @param $userId
     * @return User
     */
    public static function getUserByUserId($userId)
    {
        $statement = Database::getInstance()->prepare('SELECT * FROM `Member` WHERE id = ?');
        $statement->bindValue(1, $userId, Database::PARAM_INT);
        $statement->execute();
        return $statement->fetchObject(__CLASS__);
    }

    /**
     * @param $username
     * @return User
     */
    public static function getUserByUsername($username)
    {
        $statement = Database::getInstance()->prepare('SELECT * FROM `Member` WHERE username = ?');
        $statement->bindValue(1, $username);
        $statement->execute();
        return $statement->fetchObject(__CLASS__);
    }

    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }

    public function setPassword($password)
    {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }
}
