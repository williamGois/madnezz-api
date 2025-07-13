<?php

namespace App\Domain\Task\ValueObjects;

use InvalidArgumentException;
use DateTime;

class RecurrencePattern
{
    private const VALID_FREQUENCIES = [
        'daily',
        'weekly',
        'monthly',
        'quarterly',
        'yearly'
    ];

    private string $frequency;
    private int $interval;
    private ?array $daysOfWeek;
    private ?int $dayOfMonth;
    private ?DateTime $endDate;
    private ?int $occurrences;

    public function __construct(
        string $frequency,
        int $interval = 1,
        ?array $daysOfWeek = null,
        ?int $dayOfMonth = null,
        ?DateTime $endDate = null,
        ?int $occurrences = null
    ) {
        if (!in_array($frequency, self::VALID_FREQUENCIES)) {
            throw new InvalidArgumentException("Invalid recurrence frequency: {$frequency}");
        }

        if ($interval < 1) {
            throw new InvalidArgumentException("Interval must be at least 1");
        }

        if ($frequency === 'weekly' && empty($daysOfWeek)) {
            throw new InvalidArgumentException("Weekly recurrence requires days of week");
        }

        if ($frequency === 'monthly' && $dayOfMonth === null) {
            throw new InvalidArgumentException("Monthly recurrence requires day of month");
        }

        $this->frequency = $frequency;
        $this->interval = $interval;
        $this->daysOfWeek = $daysOfWeek;
        $this->dayOfMonth = $dayOfMonth;
        $this->endDate = $endDate;
        $this->occurrences = $occurrences;
    }

    public static function daily(int $interval = 1): self
    {
        return new self('daily', $interval);
    }

    public static function weekly(array $daysOfWeek, int $interval = 1): self
    {
        return new self('weekly', $interval, $daysOfWeek);
    }

    public static function monthly(int $dayOfMonth, int $interval = 1): self
    {
        return new self('monthly', $interval, null, $dayOfMonth);
    }

    public static function quarterly(): self
    {
        return new self('quarterly', 1);
    }

    public static function yearly(): self
    {
        return new self('yearly', 1);
    }

    public function getNextOccurrence(DateTime $fromDate): ?DateTime
    {
        if ($this->endDate && $fromDate > $this->endDate) {
            return null;
        }

        $nextDate = clone $fromDate;

        switch ($this->frequency) {
            case 'daily':
                $nextDate->modify("+{$this->interval} days");
                break;

            case 'weekly':
                $currentDayOfWeek = (int)$nextDate->format('w');
                $foundNext = false;
                
                // Look for next occurrence in current week
                foreach ($this->daysOfWeek as $dayOfWeek) {
                    if ($dayOfWeek > $currentDayOfWeek) {
                        $daysToAdd = $dayOfWeek - $currentDayOfWeek;
                        $nextDate->modify("+{$daysToAdd} days");
                        $foundNext = true;
                        break;
                    }
                }
                
                // If not found in current week, go to next week
                if (!$foundNext) {
                    $daysToAdd = (7 * $this->interval) - $currentDayOfWeek + $this->daysOfWeek[0];
                    $nextDate->modify("+{$daysToAdd} days");
                }
                break;

            case 'monthly':
                $nextDate->modify("+{$this->interval} months");
                $nextDate->setDate(
                    (int)$nextDate->format('Y'),
                    (int)$nextDate->format('n'),
                    min($this->dayOfMonth, (int)$nextDate->format('t'))
                );
                break;

            case 'quarterly':
                $nextDate->modify("+3 months");
                break;

            case 'yearly':
                $nextDate->modify("+1 year");
                break;
        }

        if ($this->endDate && $nextDate > $this->endDate) {
            return null;
        }

        return $nextDate;
    }

    public function getDescription(): string
    {
        $desc = match($this->frequency) {
            'daily' => $this->interval === 1 ? 'Diariamente' : "A cada {$this->interval} dias",
            'weekly' => $this->interval === 1 ? 'Semanalmente' : "A cada {$this->interval} semanas",
            'monthly' => $this->interval === 1 ? 'Mensalmente' : "A cada {$this->interval} meses",
            'quarterly' => 'Trimestralmente',
            'yearly' => 'Anualmente',
            default => $this->frequency
        };

        if ($this->daysOfWeek) {
            $dayNames = array_map(fn($d) => $this->getDayName($d), $this->daysOfWeek);
            $desc .= ' (' . implode(', ', $dayNames) . ')';
        }

        if ($this->dayOfMonth) {
            $desc .= " (dia {$this->dayOfMonth})";
        }

        if ($this->endDate) {
            $desc .= ' até ' . $this->endDate->format('d/m/Y');
        }

        if ($this->occurrences) {
            $desc .= " ({$this->occurrences} ocorrências)";
        }

        return $desc;
    }

    private function getDayName(int $dayNumber): string
    {
        $days = [
            0 => 'Domingo',
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado'
        ];

        return $days[$dayNumber] ?? '';
    }

    public function toArray(): array
    {
        return [
            'frequency' => $this->frequency,
            'interval' => $this->interval,
            'days_of_week' => $this->daysOfWeek,
            'day_of_month' => $this->dayOfMonth,
            'end_date' => $this->endDate?->format('Y-m-d'),
            'occurrences' => $this->occurrences,
            'description' => $this->getDescription()
        ];
    }
}