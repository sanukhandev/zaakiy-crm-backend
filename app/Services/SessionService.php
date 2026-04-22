<?php

namespace App\Services;

class SessionService
{
    public function getSession($auth)
    {
        // Return auth context from JWT directly
        return $auth;
    }
}
