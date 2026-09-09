<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'file_hash',
        'sheet_name',
        'header_row',
        'uploaded_by',
        'mapping_template_id',
        'used_mapping',
        'row_count',
        'accepted_rows',
        'rejected_rows',
        'duplicate_rows',
        'errors',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'used_mapping' => 'array',
            'errors' => 'array',
            'header_row' => 'integer',
            'row_count' => 'integer',
            'accepted_rows' => 'integer',
            'rejected_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ImportMappingTemplate::class, 'mapping_template_id');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }
}
