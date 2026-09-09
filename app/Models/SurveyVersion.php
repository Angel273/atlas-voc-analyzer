<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'previous_hash',
        'new_hash',
        'import_id',
        'changed_by',
        'diff',
    ];

    protected function casts(): array
    {
        return [
            'diff' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
