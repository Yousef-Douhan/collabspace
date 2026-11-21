<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'project_id',
        'type',
        'period_start',
        'period_end',
        'notes',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'data' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
