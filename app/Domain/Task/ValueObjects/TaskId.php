<?php

declare(strict_types=1);

namespace App\Domain\Task\ValueObjects;

class TaskId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Task ID must be positive');
        }
        
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(random_int(1, PHP_INT_MAX));
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(TaskId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}