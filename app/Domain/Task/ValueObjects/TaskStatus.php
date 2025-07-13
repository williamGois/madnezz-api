<?php

declare(strict_types=1);

namespace App\Domain\Task\ValueObjects;

class TaskStatus
{
    public const TODO = 'TODO';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const IN_REVIEW = 'IN_REVIEW';
    public const BLOCKED = 'BLOCKED';
    public const DONE = 'DONE';
    public const CANCELLED = 'CANCELLED';
    
    private const VALID_STATUSES = [
        self::TODO,
        self::IN_PROGRESS,
        self::IN_REVIEW,
        self::BLOCKED,
        self::DONE,
        self::CANCELLED
    ];
    
    private const COMPLETED_STATUSES = [
        self::DONE,
        self::CANCELLED
    ];
    
    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_STATUSES)) {
            throw new \InvalidArgumentException("Invalid task status: {$value}");
        }
        
        $this->value = $value;
    }

    public static function todo(): self
    {
        return new self(self::TODO);
    }

    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS);
    }

    public static function inReview(): self
    {
        return new self(self::IN_REVIEW);
    }

    public static function blocked(): self
    {
        return new self(self::BLOCKED);
    }

    public static function done(): self
    {
        return new self(self::DONE);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isCompleted(): bool
    {
        return in_array($this->value, self::COMPLETED_STATUSES);
    }

    public function canTransitionTo(TaskStatus $newStatus): bool
    {
        // Define allowed transitions
        $transitions = [
            self::TODO => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::IN_REVIEW, self::BLOCKED, self::TODO, self::CANCELLED],
            self::IN_REVIEW => [self::DONE, self::IN_PROGRESS, self::BLOCKED, self::CANCELLED],
            self::BLOCKED => [self::IN_PROGRESS, self::TODO, self::CANCELLED],
            self::DONE => [self::IN_PROGRESS], // Allow reopening
            self::CANCELLED => [self::TODO] // Allow reactivating
        ];
        
        return in_array($newStatus->getValue(), $transitions[$this->value] ?? []);
    }

    public function equals(TaskStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}