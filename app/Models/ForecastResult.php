<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastResult extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'forecast_id',
        'date',
        'actual_value',
        'raw_forecast_value',
        'forecast_value',
        'confidence_low',
        'confidence_high',
        'was_bounded',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'actual_value' => 'float',
            'raw_forecast_value' => 'float',
            'forecast_value' => 'float',
            'confidence_low' => 'float',
            'confidence_high' => 'float',
            'was_bounded' => 'boolean',
        ];
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(Forecast::class);
    }
}
