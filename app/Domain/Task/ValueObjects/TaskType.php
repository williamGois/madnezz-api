<?php

namespace App\Domain\Task\ValueObjects;

use InvalidArgumentException;

class TaskType
{
    private const VALID_TYPES = [
        'one_time',
        'recurring',
        'scheduled',
        'milestone',
        'subtask'
    ];

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_TYPES)) {
            throw new InvalidArgumentException("Invalid task type: {$value}");
        }
        
        $this->value = $value;
    }

    public static function oneTime(): self
    {
        return new self('one_time');
    }

    public static function recurring(): self
    {
        return new self('recurring');
    }

    public static function scheduled(): self
    {
        return new self('scheduled');
    }

    public static function milestone(): self
    {
        return new self('milestone');
    }

    public static function subtask(): self
    {
        return new self('subtask');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isRecurring(): bool
    {
        return $this->value === 'recurring';
    }

    public function requiresRecurrencePattern(): bool
    {
        return $this->value === 'recurring';
    }

    public function canHaveSubtasks(): bool
    {
        return in_array($this->value, ['one_time', 'milestone', 'scheduled']);
    }

    public function equals(TaskType $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function getIcon(): string
    {
        return match($this->value) {
            'one_time' => 'check-circle',
            'recurring' => 'repeat',
            'scheduled' => 'calendar',
            'milestone' => 'flag',
            'subtask' => 'git-branch',
            default => 'file-text'
        };
    }

    public function getLabel(): string
    {
        return match($this->value) {
            'one_time' => 'Tarefa Única',
            'recurring' => 'Recorrente',
            'scheduled' => 'Agendada',
            'milestone' => 'Marco',
            'subtask' => 'Subtarefa',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}