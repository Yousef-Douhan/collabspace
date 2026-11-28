<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $created_by
 * @property int|null $project_id
 * @property ReportType $type
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string|null $notes
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $creator
 * @property-read Project|null $project
 */
class Report extends Model
{
    protected $fillable = [
        'created_by',
        'project_id',
        'type',
        'period_start',
        'period_end',
        'notes',
        'data',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'data' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
