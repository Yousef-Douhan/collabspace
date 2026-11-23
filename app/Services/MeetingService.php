<?php

namespace App\Services;

use App\Enums\MeetingResponse;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingInvitation;
use App\Notifications\MeetingReminder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class MeetingService
{
    public function __construct(private ActivityLogger $activity, private AiService $ai, private SlackNotifier $slack) {}

    public function create(User $creator, array $data): Meeting
    {
        $meeting = DB::transaction(function () use ($creator, $data) {
            $meeting = Meeting::create([...Arr::except($data, ['attendees']), 'created_by' => $creator->id]);

            $meeting->attendees()->attach($creator->id, ['response' => MeetingResponse::Accepted->value]);
            $this->invite($creator, $meeting, $data['attendees'] ?? []);

            return $meeting;
        });

        $this->activity->log($creator, 'meeting.scheduled', "scheduled \"{$meeting->title}\"", $meeting, $meeting->project);

        if ($meeting->notifies('slack')) {
            $this->slack->post(
                "📅 {$creator->name} scheduled *{$meeting->title}* — {$meeting->starts_at->format('D M j, H:i')}"
                    .($meeting->location ? " · {$meeting->location}" : ''),
                route('meetings.show', $meeting),
            );
        }

        return $meeting;
    }

    public function update(User $actor, Meeting $meeting, array $data): Meeting
    {
        DB::transaction(function () use ($actor, $meeting, $data) {
            // Moving the meeting re-arms the reminder.
            if (isset($data['starts_at']) || array_key_exists('reminder_minutes', $data)) {
                $data['reminder_sent_at'] = null;
            }

            $meeting->update(Arr::except($data, ['attendees']));

            if (array_key_exists('attendees', $data)) {
                $keep = collect($data['attendees'] ?? [])->push($meeting->created_by)->unique();
                $meeting->attendees()->whereNotIn('users.id', $keep)->detach();
                $this->invite($actor, $meeting, $keep->all());
            }
        });

        return $meeting;
    }

    public function respond(User $user, Meeting $meeting, MeetingResponse $response): void
    {
        $meeting->attendees()->updateExistingPivot($user->id, ['response' => $response->value]);
    }

    /**
     * Summarize the transcript (or agenda) with the AI model.
     *
     * @throws RuntimeException when the AI is not configured / fails
     */
    public function summarize(User $actor, Meeting $meeting): Meeting
    {
        $transcript = trim((string) $meeting->transcript);

        if ($transcript === '' && blank($meeting->agenda)) {
            throw new RuntimeException('This meeting has no transcript or agenda to summarize yet.');
        }

        $result = $this->ai->chatJson(
            system: <<<'PROMPT'
            You summarize team meetings for a project-management app.
            Return ONLY a JSON object with exactly these keys:
              "summary":    2-4 sentences on what was discussed and decided,
              "key_points": an array of 3-7 short strings (decisions and action items, with owners and dates when mentioned).
            Use only facts from the input. Write in the same language as the transcript.
            PROMPT,
            user: "Meeting: {$meeting->title}\n"
                .'Project: '.($meeting->project?->name ?? 'General')."\n"
                .'Date: '.$meeting->starts_at->toDateTimeString()."\n"
                .'Agenda: '.($meeting->agenda ?: '-')."\n\n"
                ."Transcript:\n".($transcript !== '' ? mb_substr($transcript, 0, 15000) : '(no transcript — summarize the agenda)'),
        );

        $summary = trim((string) ($result['summary'] ?? ''));
        if ($summary === '') {
            throw new RuntimeException('The AI did not return a summary. Please try again.');
        }

        $meeting->update([
            'ai_summary' => $summary,
            'key_points' => collect((array) ($result['key_points'] ?? []))
                ->map(fn ($p) => trim((string) $p))
                ->filter()
                ->values()
                ->all(),
        ]);

        $this->activity->log($actor, 'meeting.summarized', "generated an AI summary for \"{$meeting->title}\"", $meeting, $meeting->project);

        return $meeting;
    }

    /**
     * Sends "starting soon" notifications for meetings whose reminder window has opened.
     * Run every minute by the scheduler. Returns the number of meetings handled.
     */
    public function sendDueReminders(): int
    {
        $due = Meeting::query()
            ->whereNotNull('reminder_minutes')
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDay())
            ->with('attendees')
            ->get()
            ->filter(fn (Meeting $m) => now()->greaterThanOrEqualTo($m->starts_at->copy()->subMinutes($m->reminder_minutes)));

        foreach ($due as $meeting) {
            $attendees = $meeting->attendees->reject(
                fn (User $u) => $u->pivot->response === MeetingResponse::Declined->value
            );
            Notification::send($attendees, new MeetingReminder($meeting));
            $meeting->update(['reminder_sent_at' => now()]);

            if ($meeting->notifies('slack')) {
                $this->slack->post("⏰ *{$meeting->title}* starts at {$meeting->starts_at->format('H:i')}", route('meetings.show', $meeting));
            }
        }

        return $due->count();
    }

    /** Attach new attendees (pending) and notify only the newly invited ones. */
    private function invite(User $by, Meeting $meeting, array $userIds): void
    {
        $existing = $meeting->attendees()->pluck('users.id');
        $new = collect($userIds)->map(fn ($id) => (int) $id)->diff($existing)->unique()->values();

        if ($new->isEmpty()) {
            return;
        }

        $meeting->attendees()->attach($new->all(), ['response' => MeetingResponse::Pending->value]);

        Notification::send(
            User::whereIn('id', $new)->whereKeyNot($by->id)->get(),
            new MeetingInvitation($meeting, $by),
        );
    }
}
