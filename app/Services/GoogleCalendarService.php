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
}
