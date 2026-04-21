<?php

namespace App\Repositories;

class UserRepository
{
    // This repository is deprecated. Use JWT auth context directly from middleware.
    // See AuthMiddleware which provides auth context with all necessary user details.
    
    public function findById($userId)
    {
        throw new \RuntimeException('UserRepository.findById() deprecated. Use JWT auth context instead.');
    }
}