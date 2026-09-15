<?php

namespace App\Support\Scheduling;

use App\Support\CompetitionSettings;

/**
 * Het slotraster van één speeldag: vaste slots van (wedstrijdduur +
 * wisseltijd) minuten vanaf de begintijd, met de pauze na het middelste slot.
 * Puur afgeleid van de openingstijden en de instellingen, zodat de planner en
 * de schemaweergave gegarandeerd hetzelfde raster gebruiken.
 */
final readonly class SlotGrid
{
    /**
     * @param  list<Slot>  $slots
     * @param  array{start_minute: int, end_minute: int}|null  $break
     */
    public function __construct(
        public array $slots,
        public ?array $break,
        public int $matchDurationMinutes,
        public int $bufferMinutes,
    ) {}

    /**
     * Bouwt het raster voor een speeldag die van `$startsAt` tot `$endsAt`
     * open is. Past er geen tweede slot met pauze, dan vervalt de pauze.
     */
    public static function for(string $startsAt, string $endsAt, CompetitionSettings $settings): self
    {
        $slotLength = $settings->matchDurationMinutes + $settings->bufferMinutes;
        $begin = ClockTime::toMinutes($startsAt);
        $end = ClockTime::toMinutes($endsAt);

        if ($slotLength <= 0) {
            return new self([], null, $settings->matchDurationMinutes, $settings->bufferMinutes);
        }

        $breakDuration = max(0, $settings->breakDurationMinutes);
        $capacity = max(0, intdiv($end - $begin - $breakDuration, $slotLength));

        if ($capacity < 2 && $breakDuration > 0) {
            $breakDuration = 0;
            $capacity = max(0, intdiv($end - $begin, $slotLength));
        }

        $beforeBreak = intdiv($capacity + 1, 2);

        $slots = [];

        for ($index = 0; $index < $capacity; $index++) {
            $offset = $index * $slotLength + ($index >= $beforeBreak ? $breakDuration : 0);

            $slots[] = new Slot($index, $begin + $offset, $begin + $offset + $slotLength);
        }

        $breakStart = $begin + $beforeBreak * $slotLength;

        return new self(
            slots: $slots,
            break: $breakDuration > 0 ? ['start_minute' => $breakStart, 'end_minute' => $breakStart + $breakDuration] : null,
            matchDurationMinutes: $settings->matchDurationMinutes,
            bufferMinutes: $settings->bufferMinutes,
        );
    }

    /**
     * Het slot dat precies op deze minuut begint, of null.
     */
    public function slotStartingAt(int $startMinute): ?Slot
    {
        foreach ($this->slots as $slot) {
            if ($slot->startMinute === $startMinute) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Het slot dat precies op deze kloktijd (`H:i`) begint, of null.
     */
    public function slotAt(string $startsAt): ?Slot
    {
        return $this->slotStartingAt(ClockTime::toMinutes($startsAt));
    }

    /**
     * Het einde van de wedstrijd zelf: zonder de wisseltijd die wel in het
     * slot zit.
     */
    public function matchEndMinute(Slot $slot): int
    {
        return $slot->startMinute + $this->matchDurationMinutes;
    }

    public function isEmpty(): bool
    {
        return $this->slots === [];
    }

    /**
     * Het begin van de pauze als kloktijd (`H:i`), of null zonder pauze.
     */
    public function breakStartsAt(): ?string
    {
        return $this->break === null ? null : ClockTime::fromMinutes($this->break['start_minute']);
    }

    /**
     * Het einde van de pauze als kloktijd (`H:i`), of null zonder pauze.
     */
    public function breakEndsAt(): ?string
    {
        return $this->break === null ? null : ClockTime::fromMinutes($this->break['end_minute']);
    }
}
