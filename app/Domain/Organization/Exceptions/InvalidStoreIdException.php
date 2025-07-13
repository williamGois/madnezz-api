<?php

declare(strict_types=1);

namespace App\Domain\Organization\Exceptions;

use App\Exceptions\BusinessRuleException;

class InvalidStoreIdException extends BusinessRuleException
{
    public function __construct(string $message = 'Invalid store ID provided')
    {
        parent::__construct($message);
    }
}