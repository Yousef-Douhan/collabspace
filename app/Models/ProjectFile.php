<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file uploaded to a project (optionally attached to a task).
 * Named ProjectFile to avoid clashing with Laravel's File facade.
 */
class ProjectFile extends Model
{
    use HasFactory;

    protected $table = 'files';

    protected $fillable = [
        'project_id',
        'task_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $q) => $q->visibleTo($user));
    }

    /** e.g. "1.4 MB" */
    public function getFormattedSizeAttribute(): string
    {
        $size = (float) $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
