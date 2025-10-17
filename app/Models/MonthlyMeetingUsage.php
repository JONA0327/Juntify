<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MonthlyMeetingUsage extends Model
{
    use HasFactory;

    protected $table = 'monthly_meeting_usage';

    protected $fillable = [
        'user_id',
        'username',
        'organization_id',
        'year',
        'month',
        'meetings_consumed',
        'meeting_records'
    ];

    protected $casts = [
        'meeting_records' => 'array'
    ];

    /**
     * Get or create usage record for current month
     */
    public static function getCurrentMonthUsage(string $userId, ?string $organizationId = null): self
    {
        $now = Carbon::now();

        return self::firstOrCreate([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'year' => $now->year,
            'month' => $now->month,
        ], [
            'meetings_consumed' => 0,
            'meeting_records' => []
        ]);
    }

    /**
     * Increment meetings consumed for current month
     */
    public static function incrementUsage(string $userId, ?string $organizationId = null, array $meetingData = []): void
    {
        $usage = self::getCurrentMonthUsage($userId, $organizationId);

        $usage->increment('meetings_consumed');

        // Add to audit log
        $records = $usage->meeting_records ?? [];
        $records[] = [
            'timestamp' => Carbon::now()->toISOString(),
            'action' => 'meeting_created',
            'data' => $meetingData
        ];

        $usage->update(['meeting_records' => $records]);
    }

    /**
     * Get usage for current month
     */
    public static function getCurrentMonthCount(string $userId, ?string $organizationId = null): int
    {
        $usage = self::getCurrentMonthUsage($userId, $organizationId);
        return $usage->meetings_consumed;
    }
}
