<?php

namespace App\Domain\Route\Policies;

use App\Domain\User\Models\User;
use App\Domain\Route\Models\Route;

class RoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-routes');
    }
    
    public function view(User $user, Route $route): bool
    {
        return $user->can('view-routes');
    }
    
    public function create(User $user): bool
    {
        return $user->can('create-routes');
    }
    
    public function update(User $user, Route $route): bool
    {
        return $user->can('edit-routes');
    }
    
    public function delete(User $user, Route $route): bool
    {
        return $user->can('delete-routes');
    }
    
    public function restore(User $user, Route $route): bool
    {
        return $user->can('delete-routes');
    }
    
    public function forceDelete(User $user, Route $route): bool
    {
        return $user->can('delete-routes');
    }
}