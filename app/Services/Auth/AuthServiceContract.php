<?php

namespace App\Services\Auth;

interface AuthServiceContract
{
    /**
     * Attempt to log in with the given credentials. Returns true and
     * establishes the session on success.
     */
    public function attempt(string $email, string $password): bool;

    public function logout(): void;
}
