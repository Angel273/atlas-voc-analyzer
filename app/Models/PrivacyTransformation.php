<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyTransformation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ai_run_id',
        'transformation_type',
        'tokens_count',
        'redacted_types',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'redacted_types' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
