<?php

declare(strict_types=1);

namespace App\Domain\User\Entities;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\User\ValueObjects\UserStatus;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use DateTimeImmutable;

class HierarchicalUser
{
    private UserId $id;
    private UserName $name;
    private Email $email;
    private HashedPassword $password;
    private UserStatus $status;
    private HierarchyRole $hierarchyRole;
    private ?OrganizationId $organizationId;
    private ?StoreId $storeId;
    private ?string $phone;
    private array $permissions;
    private ?array $contextData; // For MASTER context switching
    private ?DateTimeImmutable $emailVerifiedAt;
    private ?DateTimeImmutable $lastLoginAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        UserId $id,
        UserName $name,
        Email $email,
        HashedPassword $password,
        HierarchyRole $hierarchyRole,
        UserStatus $status,
        ?OrganizationId $organizationId = null,
        ?StoreId $storeId = null,
        ?string $phone = null,
        array $permissions = [],
        ?array $contextData = null,
        ?DateTimeImmutable $emailVerifiedAt = null,
        ?DateTimeImmutable $lastLoginAt = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->hierarchyRole = $hierarchyRole;
        $this->status = $status;
        $this->organizationId = $organizationId;
        $this->storeId = $storeId;
        $this->phone = $phone;
        $this->permissions = $permissions;
        $this->contextData = $contextData;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->lastLoginAt = $lastLoginAt;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function createMaster(
        UserName $name,
        Email $email,
        HashedPassword $password,
        ?string $phone = null
    ): self {
        return new self(
            new UserId(),
            $name,
            $email,
            $password,
            HierarchyRole::master(),
            UserStatus::active(),
            null, // MASTER doesn't belong to specific organization
            null, // MASTER doesn't belong to specific store
            $phone,
            ['*'], // All permissions
            null,
            new DateTimeImmutable(),
            null,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public static function createGO(
        UserName $name,
        Email $email,
        HashedPassword $password,
        OrganizationId $organizationId,
        ?string $phone = null
    ): self {
        return new self(
            new UserId(),
            $name,
            $email,
            $password,
            HierarchyRole::go(),
            UserStatus::active(),
            $organizationId,
            null,
            $phone,
            ['manage_organization', 'create_stores', 'manage_gr_users'],
            null,
            new DateTimeImmutable(),
            null,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public static function createGR(
        UserName $name,
        Email $email,
        HashedPassword $password,
        OrganizationId $organizationId,
        ?string $phone = null
    ): self {
        return new self(
            new UserId(),
            $name,
            $email,
            $password,
            HierarchyRole::gr(),
            UserStatus::active(),
            $organizationId,
            null,
            $phone,
            ['manage_region', 'view_stores', 'manage_store_managers'],
            null,
            new DateTimeImmutable(),
            null,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public static function createStoreManager(
        UserName $name,
        Email $email,
        HashedPassword $password,
        OrganizationId $organizationId,
        StoreId $storeId,
        ?string $phone = null
    ): self {
        return new self(
            new UserId(),
            $name,
            $email,
            $password,
            HierarchyRole::storeManager(),
            UserStatus::active(),
            $organizationId,
            $storeId,
            $phone,
            ['manage_store', 'view_store_data'],
            null,
            new DateTimeImmutable(),
            null,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    // Getters
    public function getId(): UserId
    {
        return $this->id;
    }

    public function getName(): UserName
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPassword(): HashedPassword
    {
        return $this->password;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function getHierarchyRole(): HierarchyRole
    {
        return $this->hierarchyRole;
    }

    public function getOrganizationId(): ?OrganizationId
    {
        return $this->organizationId;
    }

    public function getStoreId(): ?StoreId
    {
        return $this->storeId;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getContextData(): ?array
    {
        return $this->contextData;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Hierarchy methods
    public function canAccessUser(HierarchicalUser $targetUser): bool
    {
        return $this->hierarchyRole->canAccessLevel($targetUser->hierarchyRole);
    }

    public function canManageUser(HierarchicalUser $targetUser): bool
    {
        return $this->hierarchyRole->canManageLevel($targetUser->hierarchyRole);
    }

    public function isMaster(): bool
    {
        return $this->hierarchyRole->isMaster();
    }

    public function isGO(): bool
    {
        return $this->hierarchyRole->isGo();
    }

    public function isGR(): bool
    {
        return $this->hierarchyRole->isGr();
    }

    public function isStoreManager(): bool
    {
        return $this->hierarchyRole->isStoreManager();
    }

    // Context switching for MASTER users
    public function switchContext(HierarchyRole $targetRole, ?OrganizationId $organizationId = null, ?StoreId $storeId = null): void
    {
        if (!$this->isMaster()) {
            throw new \DomainException('Only MASTER users can switch context');
        }

        $this->contextData = [
            'original_role' => $this->hierarchyRole->getValue(),
            'current_role' => $targetRole->getValue(),
            'organization_id' => $organizationId?->toString(),
            'store_id' => $storeId?->toString(),
            'switched_at' => new DateTimeImmutable()
        ];
        
        $this->updatedAt = new DateTimeImmutable();
    }

    public function resetContext(): void
    {
        if (!$this->isMaster()) {
            throw new \DomainException('Only MASTER users can reset context');
        }

        $this->contextData = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCurrentRole(): HierarchyRole
    {
        if ($this->isMaster() && $this->contextData && isset($this->contextData['current_role'])) {
            return new HierarchyRole($this->contextData['current_role']);
        }
        
        return $this->hierarchyRole;
    }

    // Permission methods
    public function hasPermission(string $permission): bool
    {
        return in_array('*', $this->permissions) || in_array($permission, $this->permissions);
    }

    public function addPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            $this->permissions[] = $permission;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function removePermission(string $permission): void
    {
        $this->permissions = array_filter($this->permissions, fn($p) => $p !== $permission);
        $this->updatedAt = new DateTimeImmutable();
    }

    // Update methods
    public function updateName(UserName $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->emailVerifiedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePhone(?string $phone): void
    {
        $this->phone = $phone;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->password = $password;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignToOrganization(OrganizationId $organizationId): void
    {
        $this->organizationId = $organizationId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignToStore(StoreId $storeId): void
    {
        $this->storeId = $storeId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function removeFromStore(): void
    {
        $this->storeId = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->status = UserStatus::active();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::inactive();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function suspend(): void
    {
        $this->status = UserStatus::suspended();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return $this->password->verify($plainPassword);
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canLogin(): bool
    {
        return $this->status->isActive();
    }
}