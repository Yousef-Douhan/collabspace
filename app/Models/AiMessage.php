<?php

namespace App\Models;

use App\Enums\AiRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
