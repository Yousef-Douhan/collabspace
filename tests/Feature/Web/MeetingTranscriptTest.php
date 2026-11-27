<?php

namespace Tests\Feature\Web;

use App\Enums\MeetingResponse;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create();
        $this->meeting = Meeting::factory()->create(['created_by' => $this->host->id]);
        $this->meeting->attendees()->attach($this->host->id, ['response' => MeetingResponse::Accepted->value]);
    }

    public function test_the_host_can_paste_a_transcript_and_is_sent_to_the_summary(): void
    {
        $this->actingAs($this->host)
            ->get(route('meetings.show', [$this->meeting, 'tab' => 'notes']))
            ->assertOk()
            ->assertSee('Save transcript');

        $this->actingAs($this->host)
            ->put(route('meetings.transcript', $this->meeting), ['transcript' => "  [00:01] Lina: Kanban is done.\n[00:30] Omar: I review it Thursday.  "])
            ->assertRedirect(route('meetings.show', [$this->meeting, 'tab' => 'summary']));

        $this->assertSame("[00:01] Lina: Kanban is done.\n[00:30] Omar: I review it Thursday.", $this->meeting->fresh()->transcript);

        $this->actingAs($this->host)
            ->get(route('meetings.show', $this->meeting))
            ->assertSee('Generate summary');
    }

    public function test_an_empty_transcript_removes_it(): void
    {
        $this->meeting->update(['transcript' => 'Old text']);

        $this->actingAs($this->host)
            ->put(route('meetings.transcript', $this->meeting), ['transcript' => ''])
            ->assertRedirect();

        $this->assertNull($this->meeting->fresh()->transcript);
    }

    public function test_someone_outside_the_meeting_cannot_change_the_transcript(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put(route('meetings.transcript', $this->meeting), ['transcript' => 'Injected'])
            ->assertForbidden();

        $this->assertNull($this->meeting->fresh()->transcript);
    }

    public function test_an_arabic_transcript_is_shown_line_by_line_without_breaking_letters(): void
    {
        // "م" is the bytes D9 85, and 0x85 is a line break for a non-Unicode regex.
        $this->meeting->update(['transcript' => "[00:01] لينا: نراجع اليوم تصميم الأيقونة.\n[00:50] عمر: موافق."]);

        $this->actingAs($this->host)
            ->get(route('meetings.show', $this->meeting))
            ->assertOk()
            ->assertSee('[00:01] لينا: نراجع اليوم تصميم الأيقونة.')
            ->assertSee('[00:50] عمر: موافق.');
    }
}
