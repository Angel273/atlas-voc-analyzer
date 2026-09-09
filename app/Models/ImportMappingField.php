<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportMappingField extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'internal_field',
        'source_column',
        'transformations',
    ];

    protected function casts(): array
    {
        return [
            'transformations' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ImportMappingTemplate::class, 'template_id');
    }
}
