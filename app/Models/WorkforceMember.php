<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WorkforceMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkforceMember extends Model
{
    /** @use HasFactory<WorkforceMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'role',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_memberships')
            ->withPivot(['role', 'effective_from', 'effective_to'])
            ->withTimestamps();
    }

    public function supervisedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'supervisor_id');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'agent_id');
    }

    public function supervisedSurveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'supervisor_id');
    }

    public function resolveTeamAtDate(CarbonInterface|string $date): ?Team
    {
        $dateStr = is_string($date) ? substr($date, 0, 10) : $date->toDateString();

        $membership = $this->memberships()
            ->where('effective_from', '<=', $dateStr)
            ->where(function ($query) use ($dateStr) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateStr);
            })
            ->orderBy('effective_from', 'desc')
            ->first();

        return $membership?->team;
    }
}
