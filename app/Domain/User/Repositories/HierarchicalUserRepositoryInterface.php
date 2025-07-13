<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\Email;

interface HierarchicalUserRepositoryInterface
{
    public function save(HierarchicalUser $user): void;
    
    public function findById(UserId $id): ?HierarchicalUser;
    
    public function findByEmail(Email $email): ?HierarchicalUser;
}