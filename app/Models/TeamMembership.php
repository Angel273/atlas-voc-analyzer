<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TeamMembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMembership extends Model
{
    /** @use HasFactory<TeamMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'workforce_member_id',
        'role',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function workforceMember(): BelongsTo
    {
        return $this->belongsTo(WorkforceMember::class, 'workforce_member_id');
    }

    public function member(): BelongsTo
    {
        return $this->workforceMember();
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        $dateStr = is_string($date) ? substr($date, 0, 10) : $date->toDateString();

        return $query->where('effective_from', '<=', $dateStr)
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateStr);
            });
    }
}
