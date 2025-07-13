<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ValueObjects;

class EnterpriseCode
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));
        
        if (empty($value)) {
            throw new \InvalidArgumentException('Enterprise code cannot be empty');
        }

        if (!preg_match('/^[A-Z0-9\-_]{2,20}$/', $value)) {
            throw new \InvalidArgumentException('Enterprise code must be 2-20 characters long and contain only uppercase letters, numbers, hyphens, and underscores');
        }

        $this->value = $value;
    }

    public static function generate(string $name): self
    {
        // Generate code from name (e.g., "Shopping Center ABC" -> "SC-ABC")
        $words = explode(' ', strtoupper(preg_replace('/[^A-Za-z0-9\s]/', '', $name)));
        $code = '';
        
        if (count($words) >= 2) {
            // Take first letter of each word
            foreach ($words as $word) {
                if (!empty($word)) {
                    $code .= substr($word, 0, 1);
                }
            }
        } else {
            // Take first 3-5 characters
            $code = substr($words[0], 0, 5);
        }
        
        // Add random suffix if needed
        $code .= '-' . strtoupper(substr(uniqid(), -4));
        
        return new self($code);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(EnterpriseCode $other): bool
    {
        return $this->value === $other->value;
    }
}