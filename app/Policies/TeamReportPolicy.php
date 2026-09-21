<?php

namespace App\Policies;

use App\Models\TeamReport;
use App\Models\User;

class TeamReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function view(User $user, TeamReport $teamReport): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reports.generate');
    }

    public function download(User $user, ?TeamReport $teamReport = null): bool
    {
        return $user->hasPermission('reports.download');
    }
}
