## Outgoing Shared Meetings

Endpoints added (Sept 2025) to allow a user to view and revoke meetings they have shared with others.

API:
GET /api/shared-meetings/outgoing
  Returns: { success: true, shares: [ { id, meeting_id, title, status, shared_with: { id, name, email }, shared_at, responded_at, is_legacy } ] }

DELETE /api/shared-meetings/outgoing/{id}
  Revokes a share (only if authenticated user is the original sharer). Removes the record so recipient no longer sees the meeting.

UI Integration:
The tab "Reuniones compartidas" now shows two sections:
1. Reuniones que otros compartieron conmigo (existing /api/shared-meetings/v2)
2. Reuniones que yo compartí (new /api/shared-meetings/outgoing)

Each outgoing share card has a revoke (X) button. On confirmation it calls DELETE and reloads the list.

Controller methods: getOutgoingShares(), revokeOutgoingShare() in `SharedMeetingController`.

Model update: `SharedMeeting` now includes relation `sharedWithUser()`.

Blade: `reuniones.blade.php` updated with wrapper divs `incoming-shared-wrapper` and `outgoing-shared-wrapper`.

JS (`resources/js/reuniones_v2.js`): Added functions:
- loadOutgoingSharedMeetings()
- renderOutgoingShares()
- confirmRevokeShare()

Notes:
- Legacy vs modern meetings distinguished with `is_legacy` flag.
- Pending and accepted shares both listed so they can be revoked before acceptance.
