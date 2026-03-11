<?php

namespace app\Storage;

use app\Model\UserModel;

class UserStorage extends AbstractStorage
{
    /**
     * Primary key
     *
     * @var string
     */
    protected string $primary = 'user_id';

    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'users';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'user_id'       => 'userId',
        'name'          => 'name',
        'surname'       => 'surname',
        'username'      => 'username',
        'email'         => 'email',
        'password_hash' => 'passwordHash',
        'role'          => 'role',
        'is_active'     => 'isActive',
        'last_login_at' => 'lastLoginAt',
        'created_at'    => 'createdAt',
        'updated_at'    => 'updatedAt'
    );
 
    /**
     * Search user in db by email
     * 
     * @param string $email
     * @return UserModel|false
     */
    public function getUserByEmail(string $email): UserModel|false 
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT ' . $this->mapFields() . '
                FROM users
                WHERE email = :email';
        
        $sth = $pdo->prepare($sql);
        
        $sth->execute([
            ':email' => $email
        ]);
        
        return $sth->fetchObject(UserModel::class) ?: false;
    }
}
