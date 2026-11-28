<?php

namespace App\Models;

use App\Enums\AiRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property AiRole $role
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class AiMessage extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'content',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AiRole::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
