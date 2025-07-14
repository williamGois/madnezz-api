# Organization Hierarchy - Domain-Driven Design Architecture

## Overview

The Madnezz system implements a multi-organization hierarchy with four main levels:
- **MASTER**: Super-admin with access to all organizations
- **GO** (Gestor Organizacional): Organization manager
- **GR** (Gerente Regional): Regional manager
- **STORE_MANAGER**: Store manager

## Domain Model

### Value Objects

```mermaid
classDiagram
    class OrganizationId {
        -string value
        +toString() string
        +equals(other) bool
    }
    
    class StoreId {
        -string value
        +toString() string
        +equals(other) bool
    }
    
    class UserId {
        -string value
        +toString() string
        +equals(other) bool
    }
    
    class OrganizationUnitId {
        -string value
        +toString() string
        +equals(other) bool
    }
    
    class Email {
        -string value
        +getValue() string
        +validate() bool
    }
    
    class HierarchyRole {
        -string value
        +getValue() string
        +isMaster() bool
        +isGO() bool
        +isGR() bool
        +isStoreManager() bool
    }
```

### Entities

```mermaid
classDiagram
    class Organization {
        -OrganizationId id
        -string name
        -string code
        -bool active
        +create(name, code) Organization
        +updateName(name) void
        +updateCode(code) void
        +activate() void
        +deactivate() void
    }
    
    class OrganizationUnit {
        -OrganizationUnitId id
        -OrganizationId organizationId
        -string name
        -string code
        -string type
        -OrganizationUnitId parentId
        +create(...) OrganizationUnit
        +isCompany() bool
        +isRegional() bool
        +isStore() bool
    }
    
    class Store {
        -StoreId id
        -OrganizationId organizationId
        -UserId managerId
        -string name
        -string code
        -string address
        +create(...) Store
        +assignManager(userId) void
    }
    
    class HierarchicalUser {
        -UserId id
        -Name name
        -Email email
        -HierarchyRole role
        -OrganizationId organizationId
        -StoreId storeId
        +createMaster(...) HierarchicalUser
        +createGO(...) HierarchicalUser
        +createGR(...) HierarchicalUser
        +createStoreManager(...) HierarchicalUser
    }
    
    class Position {
        -PositionId id
        -OrganizationId organizationId
        -OrganizationUnitId unitId
        -UserId userId
        -DepartmentId departmentId
        -string title
        -string level
        +create(...) Position
    }
```

### Aggregates and Relationships

```mermaid
graph TB
    Organization -->|has many| OrganizationUnit
    OrganizationUnit -->|parent-child| OrganizationUnit
    Organization -->|has many| Store
    Organization -->|has many| Department
    Organization -->|has many| Position
    
    OrganizationUnit -->|type=company| CompanyUnit
    OrganizationUnit -->|type=regional| RegionalUnit
    OrganizationUnit -->|type=store| StoreUnit
    
    Position -->|belongs to| OrganizationUnit
    Position -->|belongs to| User
    Position -->|has many| Department
    
    Store -->|managed by| User
    Store -->|corresponds to| StoreUnit
    
    User -->|has role| HierarchyRole
    User -->|belongs to| Organization
    User -->|may belong to| Store
```

## Repository Pattern

```mermaid
classDiagram
    class OrganizationRepositoryInterface {
        <<interface>>
        +save(Organization) void
        +findById(OrganizationId) Organization
        +findByCode(string) Organization
        +codeExists(string) bool
        +findAll() Organization[]
    }
    
    class StoreRepositoryInterface {
        <<interface>>
        +save(Store) void
        +findById(StoreId) Store
        +findByCode(string) Store
        +findByOrganization(OrganizationId) Store[]
        +codeExists(string) bool
    }
    
    class HierarchicalUserRepositoryInterface {
        <<interface>>
        +save(HierarchicalUser) void
        +findById(UserId) HierarchicalUser
        +findByEmail(Email) HierarchicalUser
        +findByHierarchyRole(HierarchyRole) HierarchicalUser[]
        +findByOrganizationId(OrganizationId) HierarchicalUser[]
    }
    
    class OrganizationUnitRepositoryInterface {
        <<interface>>
        +save(OrganizationUnit) void
        +findById(OrganizationUnitId) OrganizationUnit
        +findByOrganization(OrganizationId) OrganizationUnit[]
        +findByType(OrganizationId, string) OrganizationUnit[]
        +findChildren(OrganizationUnitId) OrganizationUnit[]
    }
```

## Use Cases

```mermaid
graph LR
    subgraph Organization Management
        CreateOrganization[Create Organization]
        UpdateOrganization[Update Organization]
        ListOrganizations[List Organizations]
    end
    
    subgraph Region Management
        CreateRegion[Create Region]
        ListRegions[List Regions]
        CreateGR[Create Regional Manager]
    end
    
    subgraph Store Management
        CreateStore[Create Store]
        ListStores[List Stores by Region]
        AssignStoreManager[Assign Store Manager]
    end
    
    MASTER --> CreateOrganization
    MASTER --> UpdateOrganization
    MASTER --> ListOrganizations
    
    GO --> CreateRegion
    GO --> CreateGR
    GO --> CreateStore
    
    GR --> ListStores
```

## Factory Pattern

```mermaid
classDiagram
    class HierarchicalUserFactory {
        +createMaster(name, email, password) HierarchicalUser
        +createGO(name, email, password, orgId) HierarchicalUser
        +createGR(name, email, password, orgId) HierarchicalUser
        +createStoreManager(name, email, password, orgId, storeId) HierarchicalUser
    }
    
    class OrganizationFactory {
        +create(name, code) Organization
        +createWithGO(name, code, goData) Organization
    }
    
    class StoreFactory {
        +create(orgId, regionId, storeData) Store
        +createWithManager(orgId, regionId, storeData, managerData) Store
    }
```

## Middleware Architecture

```mermaid
sequenceDiagram
    participant Client
    participant JWTMiddleware
    participant OrganizationContextMiddleware
    participant HierarchyAccessMiddleware
    participant Controller
    participant Repository
    
    Client->>JWTMiddleware: Request with JWT
    JWTMiddleware->>JWTMiddleware: Validate token
    JWTMiddleware->>OrganizationContextMiddleware: Pass authenticated user
    
    OrganizationContextMiddleware->>OrganizationContextMiddleware: Load user position
    OrganizationContextMiddleware->>OrganizationContextMiddleware: Load organization context
    OrganizationContextMiddleware->>OrganizationContextMiddleware: Inject context data
    
    OrganizationContextMiddleware->>HierarchyAccessMiddleware: Request with context
    HierarchyAccessMiddleware->>HierarchyAccessMiddleware: Check hierarchy level
    HierarchyAccessMiddleware->>HierarchyAccessMiddleware: Check department access
    HierarchyAccessMiddleware->>HierarchyAccessMiddleware: Check resource access
    
    HierarchyAccessMiddleware->>Controller: Authorized request
    Controller->>Repository: Query with context filters
    Repository->>Repository: Apply organization context
    Repository->>Controller: Filtered results
    Controller->>Client: Response
```

## Context-Based Filtering

```mermaid
graph TB
    subgraph Organization Context
        OrgId[Organization ID]
        UnitId[Unit ID]
        UnitType[Unit Type]
        Role[Hierarchy Role]
        Departments[Departments]
    end
    
    subgraph Access Rules
        MASTER[MASTER: All Access]
        GO[GO: Organization Scope]
        GR[GR: Regional Scope]
        SM[Store Manager: Store Scope]
    end
    
    subgraph Data Filtering
        StoreFilter[Store Repository Filter]
        UserFilter[User Repository Filter]
        UnitFilter[Unit Repository Filter]
    end
    
    Role --> MASTER
    Role --> GO
    Role --> GR
    Role --> SM
    
    MASTER --> StoreFilter
    GO --> StoreFilter
    GR --> StoreFilter
    SM --> StoreFilter
    
    StoreFilter --> FilteredData[Filtered Results]
```

## Permission Inheritance

```mermaid
graph TD
    MASTER[MASTER<br/>All Permissions]
    GO[GO<br/>manage_organization<br/>view_all_stores<br/>manage_regions]
    GR[GR<br/>manage_region<br/>view_stores<br/>manage_store_managers]
    SM[Store Manager<br/>manage_store<br/>view_store_data<br/>manage_employees]
    
    MASTER -->|can do everything| GO
    GO -->|can manage| GR
    GR -->|can manage| SM
```

## Database Schema

```sql
-- Organizations
CREATE TABLE organizations (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Organization Units (Hierarchical)
CREATE TABLE organization_units (
    id UUID PRIMARY KEY,
    organization_id UUID REFERENCES organizations(id),
    parent_id UUID REFERENCES organization_units(id),
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    type ENUM('company', 'regional', 'store') NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(organization_id, code)
);

-- Stores
CREATE TABLE stores (
    id UUID PRIMARY KEY,
    organization_id UUID REFERENCES organizations(id),
    manager_id UUID REFERENCES users_ddd(id),
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(2),
    zip_code VARCHAR(10),
    phone VARCHAR(20),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Users with Hierarchy
CREATE TABLE users_ddd (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    hierarchy_role ENUM('MASTER', 'GO', 'GR', 'STORE_MANAGER') NOT NULL,
    organization_id UUID REFERENCES organizations(id),
    store_id UUID REFERENCES stores(id),
    permissions JSON,
    context_data JSON,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Positions
CREATE TABLE positions (
    id UUID PRIMARY KEY,
    organization_id UUID REFERENCES organizations(id),
    organization_unit_id UUID REFERENCES organization_units(id),
    user_id UUID REFERENCES users_ddd(id),
    title VARCHAR(255),
    level VARCHAR(50),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Departments
CREATE TABLE departments (
    id UUID PRIMARY KEY,
    organization_id UUID REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    type VARCHAR(50),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Position-Department Relationship
CREATE TABLE position_departments (
    position_id UUID REFERENCES positions(id),
    department_id UUID REFERENCES departments(id),
    PRIMARY KEY (position_id, department_id)
);
```

## Integration Flow

```mermaid
sequenceDiagram
    participant MASTER
    participant System
    participant GO
    participant GR
    participant StoreManager
    
    MASTER->>System: POST /organizations<br/>{name, code, go_user}
    System->>System: Create Organization
    System->>System: Create GO User
    System->>System: Create Company Unit
    System->>System: Create Departments
    System->>MASTER: Organization Created
    
    GO->>System: POST /organizations/{id}/regions<br/>{name, code}
    System->>System: Create Regional Unit
    System->>GO: Region Created
    
    GO->>System: POST /organizations/{id}/regions/{id}/gr<br/>{user_data}
    System->>System: Create GR User
    System->>System: Create Position
    System->>GO: GR Created
    
    GO->>System: POST /organizations/{id}/stores<br/>{region_id, store_data, manager}
    System->>System: Create Store
    System->>System: Create Store Unit
    System->>System: Create Store Manager
    System->>System: Create Position
    System->>GO: Store Created
    
    GR->>System: GET /organizations/{id}/regions/{id}/stores
    System->>System: Filter by Region
    System->>GR: Stores in Region
    
    StoreManager->>System: GET /stores/{id}
    System->>System: Check Store Access
    System->>StoreManager: Store Details
```

## Security and Access Control

### Role-Based Access Matrix

| Resource | MASTER | GO | GR | Store Manager |
|----------|--------|----|----|---------------|
| Create Organization | ✅ | ❌ | ❌ | ❌ |
| Update Organization | ✅ | ❌ | ❌ | ❌ |
| Create Region | ✅ | ✅ | ❌ | ❌ |
| Create Store | ✅ | ✅ | ❌ | ❌ |
| View All Stores | ✅ | ✅ | ❌ | ❌ |
| View Regional Stores | ✅ | ✅ | ✅ | ❌ |
| View Own Store | ✅ | ✅ | ✅ | ✅ |
| Manage Store | ✅ | ✅ | ✅ | ✅ |

### Context Filtering Rules

1. **MASTER**: No filtering - sees all data
2. **GO**: Filtered by organization_id
3. **GR**: Filtered by organization_id and regional unit
4. **Store Manager**: Filtered by organization_id and store_id