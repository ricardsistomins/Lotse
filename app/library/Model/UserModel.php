<?php

namespace app\Model;

class UserModel {
    public int     $userId;
    public string  $name;
    public string  $surname;
    public string  $username;
    public string  $email;
    public string  $passwordHash;
    public string  $role;
    public bool    $isActive;
    public ?string $lastLoginAt = null;
    public string  $createdAt;
    public string  $updatedAt;
}
