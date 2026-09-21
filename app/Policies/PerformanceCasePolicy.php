<?php

namespace App\Policies;

use App\Models\PerformanceCase;
use App\Models\User;

class PerformanceCasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('cases.view');
    }

    public function view(User $user, PerformanceCase $performanceCase): bool
    {
        return $user->hasPermission('cases.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('cases.create');
    }

    public function update(User $user, PerformanceCase $performanceCase): bool
    {
        return $user->hasPermission('cases.update');
    }

    public function close(User $user, PerformanceCase $performanceCase): bool
    {
        return $user->hasPermission('cases.close');
    }

    public function viewDisciplinary(User $user, PerformanceCase $performanceCase): bool
    {
        return $user->hasPermission('cases.view_disciplinary');
    }
}
