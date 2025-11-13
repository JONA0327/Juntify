<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddFkMeetingGroupMeetingToTranscriptionsLaravel extends Migration
{
	public function up(): void
	{
		// Migration intentionally left as a no-op. Foreign keys for meeting_group_meeting
		// are created in the original migration where the table is defined, or were
		// already present in the database. This placeholder prevents a runtime error
		// when Laravel expects a class matching the filename.
	}

	public function down(): void
	{
		// no-op
	}
}
