<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DslTool extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'label',
        'description',
        'is_builtin',
        'is_active',
        'execution_mode',
        'parameters_schema',
        'dsl_template',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'is_active' => 'boolean',
            'parameters_schema' => 'array',
            'dsl_template' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBuiltin(Builder $query): Builder
    {
        return $query->where('is_builtin', true);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_builtin', false);
    }
}
