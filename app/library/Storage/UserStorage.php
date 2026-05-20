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
        'updated_at'    => 'updatedAt',
        'is_dark'       => 'isDark'
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
    
    /**
     * Update last login field
     * 
     * @param int $userId
     */
    public function updateLastLoginField(int $userId) 
    {
        $pdo = $this->getPdo();
        
        $sql = 'UPDATE users
                SET last_login_at = NOW()
                WHERE user_id = :userId';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':userId' => $userId
        ]);
    }
    
    /**
     * Update site background theme color
     * 
     * @param int $userId
     * @param bool $isDark
     * @return void
     */
    public function updateTheme(int $userId, bool $isDark): void
    {
        $pdo = $this->getPdo();
        
        $sql = 'UPDATE users
                SET is_dark = :isDark
                WHERE user_id = :userId';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':isDark' => (int)$isDark,
            ':userId' => $userId
        ]);
    }
    
    /**
     * Get all users, newest first
     * 
     * @return array
     */
    public function getAll(): array
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT ' . $this->mapFields() . '
                FROM users
                ORDER BY created_at DESC';
        
        $sth = $pdo->prepare($sql);
        $sth->execute();
        
        return $sth->fetchAll($pdo::FETCH_CLASS, UserModel::class);
    }
    
    /**
     * Get user by id
     * 
     * @param int $userId
     * @return UserModel|false
     */
    public function getById(int $userId): UserModel|false
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT ' . $this->mapFields() . '
                FROM users
                WHERE user_id = :userId';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':userId' => $userId
        ]);
        
        return $sth->fetchObject(UserModel::class) ?: false;
    }
    
    /**
     * Insert a new user and return last inserted id
     * 
     * @param string $name
     * @param string $surname
     * @param string $email
     * @param string $role
     * @param string $passwordHash
     * @return int
     */
    public function create(string $name, string $surname, string $email, string $role, string $passwordHash): int
    {
        $pdo = $this->getPdo();
   
        $base = strtolower($name . '.' . $surname);
        $username = $base;
        $counter = 2;
        
        while ($this->usernameExists($username)) {
            $username = $base . $counter++;
        }
   
        $sql = 'INSERT INTO users
                    (name, surname, username, email, password_hash, role, is_active)
                VALUES 
                    (:name, :surname, :username, :email, :passwordHash, :role, 1)';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':name'         => $name,
            ':surname'      => $surname,
            ':username'     => $username,
            ':email'        => $email,
            ':passwordHash' => $passwordHash,
            ':role'         => $role
        ]);
        
        return (int)$pdo->lastInsertId();
    }
    
    /**
     * Checks if user exists by username
     * 
     * @param string $username
     * @return bool
     */
    private function usernameExists(string $username): bool
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT user_id
                FROM users
                WHERE username = :username';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':username' => $username
        ]);
     
        return (bool)$sth->fetchColumn();
    }
    
    /**
     * Update user data
     * 
     * @param int $userId
     * @param string $name
     * @param string $surname
     * @param string $email
     * @param string $role
     * @param bool $isActive
     * @param string|null $passwordHash
     * @return void
     */
    public function update(int $userId, string $name, string $surname, string $email, string $role, bool $isActive, ?string $passwordHash = null): void
    {
        $pdo = $this->getPdo();
        $username = strtolower($name . '.' . $surname);
        
        $sql = 'UPDATE users
                    SET name = :name, surname = :surname, email = :email, username = :username,
                        role = :role, is_active = :isActive, password_hash = COALESCE(:passwordHash, password_hash)
                    WHERE user_id = :userId';

        $params = [
            ':name'         => $name,
            ':surname'      => $surname,
            ':email'        => $email,
            ':username'     => $username,
            ':role'         => $role,
            ':isActive'     => (int)$isActive,
            ':passwordHash' => $passwordHash,
            ':userId'       => $userId
        ];
        
        $sth = $pdo->prepare($sql);
        $sth->execute($params);
    }
}
