<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PseudonymVault extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'pseudonym_vault';

    protected $fillable = [
        'scope_id',
        'entity_type',
        'entity_internal_id',
        'pseudonym',
        'created_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
