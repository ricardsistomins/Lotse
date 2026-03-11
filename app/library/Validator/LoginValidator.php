<?php

namespace app\Validator; 

class LoginValidator {
    /**
     * Password and email validator
     * 
     * @param string $email
     * @param string $password
     * @return string|null
     */
    public function validate(string $email, string $password): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email or password.';
        }

        // allow only printable ASCII characters (letters, numbers, symbols)
        if (!preg_match('/^[\x20-\x7E]+$/', $password)) {
            return 'Invalid email or password.';
        }

        return null;
    }
}
