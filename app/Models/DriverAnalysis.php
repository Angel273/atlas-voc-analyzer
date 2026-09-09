<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAnalysis extends Model
{
    use HasFactory;

    protected $table = 'driver_analyses';

    protected $fillable = [
        'metric',
        'filters',
        'sample_size',
        'controlled_variables',
        'reference_categories',
        'drivers_data',
        'diagnostics',
        'ai_interpretation',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sample_size' => 'integer',
            'controlled_variables' => 'array',
            'reference_categories' => 'array',
            'drivers_data' => 'array',
            'diagnostics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
