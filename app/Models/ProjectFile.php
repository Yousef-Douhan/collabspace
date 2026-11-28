<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file uploaded to a project (optionally attached to a task).
 * Named ProjectFile to avoid clashing with Laravel's File facade.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $task_id
 * @property int $uploaded_by
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Task|null $task
 * @property-read User $uploader
 */
class ProjectFile extends Model
{
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Files in projects the user can see.
     *
     * @param  Builder<ProjectFile>  $query
     * @return Builder<ProjectFile>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('project_id', Project::visibleTo($user)->select('projects.id'));
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

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
