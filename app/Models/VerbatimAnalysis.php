<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerbatimAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'category_id',
        'confidence',
        'provider',
        'model',
        'prompt_version',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'processed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'survey_id');
    }
}
