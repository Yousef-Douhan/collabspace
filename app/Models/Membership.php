<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The extra columns on the "who belongs to what" tables, so `$user->pivot->role` is typed.
 *
 * One class for every membership table; each table only has some of these columns:
 * project_members / team_user → role, task_user → is_reviewer, meeting_user → response,
 * conversation_user → is_pinned, is_archived, last_read_at.
 *
 * @property string|null $role
 * @property int|bool|null $is_reviewer
 * @property string|null $response
 * @property int|bool|null $is_pinned
 * @property int|bool|null $is_archived
 * @property string|null $last_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Membership extends Pivot {}
