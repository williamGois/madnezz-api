<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use App\Exceptions\BusinessRuleException;

class InvalidHierarchyRoleException extends BusinessRuleException
{
    public function __construct(string $message = 'Invalid hierarchy role provided')
    {
        parent::__construct($message);
    }
}