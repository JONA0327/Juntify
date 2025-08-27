<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarService
{
    protected Client $client;
    protected Calendar $calendar;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->setScopes([Calendar::CALENDAR]);
        $this->client->setAccessType('offline');

        $this->calendar = new Calendar($this->client);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getCalendar(): Calendar
    {
        return $this->calendar;
    }

    public function createEvent(string $summary, string $start, string $end, string $calendarId = 'primary'): string
    {
        $event = new Event([
            'summary' => $summary,
            'start'   => ['dateTime' => $start],
            'end'     => ['dateTime' => $end],
        ]);

        $created = $this->calendar->events->insert($calendarId, $event);

        return $created->getId();
    }

    /**
     * Insert or update an event. Start/End can be either ['dateTime'=>RFC3339] or ['date'=>YYYY-MM-DD].
     */
    public function upsertEvent(string $summary, array $start, array $end, string $calendarId = 'primary', ?string $eventId = null): string
    {
        $event = new Event([
            'summary' => $summary,
            'start'   => $start,
            'end'     => $end,
        ]);

        if ($eventId) {
            $updated = $this->calendar->events->patch($calendarId, $eventId, $event);
            return $updated->getId();
        }

        $created = $this->calendar->events->insert($calendarId, $event);
        return $created->getId();
    }
}
