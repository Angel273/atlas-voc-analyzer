<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'supervisor_id',
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

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(WorkforceMember::class, 'supervisor_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(WorkforceMember::class, 'team_memberships')
            ->withPivot(['role', 'effective_from', 'effective_to'])
            ->withTimestamps();
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'team_id');
    }

    public function activeMembersAtDate(CarbonInterface|string $date): Collection
    {
        $dateStr = is_string($date) ? substr($date, 0, 10) : $date->toDateString();

        return $this->members()
            ->wherePivot('effective_from', '<=', $dateStr)
            ->where(function ($query) use ($dateStr) {
                $query->wherePivotNull('effective_to')
                    ->orWherePivot('effective_to', '>=', $dateStr);
            })
            ->get();
    }
}
