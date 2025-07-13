# Madnezz API - Hierarchical Organization Access Control

## 📚 Swagger Documentation

Acesse a documentação completa em: `http://localhost:9000/api/documentation`

## 🔐 Authentication

Primeiro faça login para obter o token JWT:

```bash
# Login como GO (Director)
curl -X POST http://localhost:9000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "go@madnezz.com",
    "password": "password"
  }'

# Login como GR (Regional Manager)
curl -X POST http://localhost:9000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "gr1@madnezz.com",
    "password": "password"
  }'

# Login como Store Manager
curl -X POST http://localhost:9000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "sm1@madnezz.com",
    "password": "password"
  }'
```

## 🏢 Organization Context

### Get Organization Context
**Endpoint:** `GET /api/v1/organization/context`  
**Access:** All authenticated users with organizational position

```bash
curl -X GET "http://localhost:9000/api/v1/organization/context" \
  -H "Authorization: Bearer {JWT_TOKEN}" \
  -H "Accept: application/json"
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "organization_id": "612b29b1-06a4-4885-ab98-de099feb6ecd",
    "organization_name": "Madnezz Corporation",
    "organization_code": "MADNEZZ",
    "position_level": "go",
    "organization_unit_id": "e1acb852-4489-43d3-847f-ec027d016ccf",
    "organization_unit_name": "Madnezz Corporation",
    "organization_unit_type": "company",
    "departments": ["administrative", "financial", "marketing", "operations", "trade", "macro"],
    "position_id": "a9da6ad7-8cf2-4f0a-a31a-b5ff49c39c8c"
  }
}
```

## 📊 Hierarchy-Based Access Control

### GO Level (Director) - Highest Level
**Endpoint:** `GET /api/v1/organization/dashboard`  
**Access:** GO level only

```bash
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer {GO_TOKEN}" \
  -H "Accept: application/json"
```

### GR Level (Regional Manager)
**Endpoint:** `GET /api/v1/regional/dashboard`  
**Access:** GR level and above (GR + GO)

```bash
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer {GR_TOKEN}" \
  -H "Accept: application/json"
```

### Store Level (Store Manager)
**Endpoint:** `GET /api/v1/store/dashboard`  
**Access:** All levels (Store Manager + GR + GO)

```bash
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer {SM_TOKEN}" \
  -H "Accept: application/json"
```

## 🏢 Department-Based Access Control

### Administrative Department Reports
**Endpoint:** `GET /api/v1/reports/administrative`  
**Access:** Users with Administrative department access

```bash
curl -X GET "http://localhost:9000/api/v1/reports/administrative" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Financial Department Reports
**Endpoint:** `GET /api/v1/reports/financial`  
**Access:** Users with Financial department access

```bash
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

## 🎯 Resource-Specific Access Control

### Store Details
**Endpoint:** `GET /api/v1/store/{store_id}/details`  
**Access:** Automatic validation based on user's hierarchy and store access

```bash
curl -X GET "http://localhost:9000/api/v1/store/488f1116-41ce-4976-9b6f-90ccaf68bede/details" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Organization Unit Details
**Endpoint:** `GET /api/v1/unit/{unit_id}/details`  
**Access:** Automatic validation based on user's hierarchy and unit access

```bash
curl -X GET "http://localhost:9000/api/v1/unit/7d777412-9585-4a83-ab04-ebd28aae725e/details" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

## 🔀 Combined Access Control

### Regional Marketing Campaigns
**Endpoint:** `GET /api/v1/campaigns/regional`  
**Access:** GR level and above + Marketing department access

```bash
curl -X GET "http://localhost:9000/api/v1/campaigns/regional" \
  -H "Authorization: Bearer {GR_TOKEN}" \
  -H "Accept: application/json"
```

## 🧪 Testing Scenarios

### Scenario 1: GO User Access
```bash
# GO can access all levels
GO_TOKEN="..." # Get from login

# ✅ Should work - GO can access GO dashboard
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer $GO_TOKEN"

# ✅ Should work - GO can access GR dashboard
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer $GO_TOKEN"

# ✅ Should work - GO can access Store dashboard
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer $GO_TOKEN"

# ✅ Should work - GO has all departments
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer $GO_TOKEN"
```

### Scenario 2: GR User Access
```bash
# GR has limited access
GR_TOKEN="..." # Get from login

# ❌ Should fail - GR cannot access GO dashboard
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer $GR_TOKEN"

# ✅ Should work - GR can access GR dashboard
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer $GR_TOKEN"

# ✅ Should work - GR can access Store dashboard
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer $GR_TOKEN"

# ❌ Should fail - GR doesn't have Financial department
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer $GR_TOKEN"

# ✅ Should work - GR has Administrative department
curl -X GET "http://localhost:9000/api/v1/reports/administrative" \
  -H "Authorization: Bearer $GR_TOKEN"
```

### Scenario 3: Store Manager Access
```bash
# Store Manager has most limited access
SM_TOKEN="..." # Get from login

# ❌ Should fail - SM cannot access GO dashboard
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer $SM_TOKEN"

# ❌ Should fail - SM cannot access GR dashboard
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer $SM_TOKEN"

# ✅ Should work - SM can access Store dashboard
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer $SM_TOKEN"

# ❌ Should fail - SM doesn't have Financial department
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer $SM_TOKEN"

# ❌ Should fail - SM doesn't have Administrative department
curl -X GET "http://localhost:9000/api/v1/reports/administrative" \
  -H "Authorization: Bearer $SM_TOKEN"
```

## 📋 Access Control Matrix

| Endpoint | GO | GR | Store Manager |
|----------|----|----|---------------|
| `/organization/context` | ✅ | ✅ | ✅ |
| `/organization/dashboard` | ✅ | ❌ | ❌ |
| `/regional/dashboard` | ✅ | ✅ | ❌ |
| `/store/dashboard` | ✅ | ✅ | ✅ |
| `/reports/administrative` | ✅ | ✅ | ❌ |
| `/reports/financial` | ✅ | ❌ | ❌ |
| `/campaigns/regional` | ✅ | ✅* | ❌ |

*Requires Marketing department access

## 🏗️ Architecture Notes

### Middleware Stack
1. `jwt.auth` - JWT authentication validation
2. `org.context` - Organization context injection
3. `hierarchy.access:level,department` - Hierarchy and department validation

### Hierarchy Levels
- **GO (Director)**: Highest level, manages entire organization
- **GR (Regional Manager)**: Manages regional units and stores
- **Store Manager**: Manages individual stores

### Departments
- **Administrative**: General administration
- **Financial**: Financial control and accounting
- **Marketing**: Marketing and communication
- **Operations**: Operations and logistics
- **Trade**: Trade marketing and sales
- **Macro**: Strategic macro planning

### Access Rules
1. Higher hierarchy levels inherit access from lower levels
2. Department access is role-specific and non-inherited
3. Resource access is automatically validated based on organizational hierarchy
4. GO users have access to all departments by default
5. GR users have access to operational departments
6. Store Managers have access to store-level departments only

## 🔒 Security Features

- **JWT-based authentication** with token expiration
- **Multi-layered access control** (hierarchy + department + resource)
- **Automatic context injection** for organizational data
- **Domain-Driven Design** architecture with proper separation of concerns
- **Value objects** for type safety and business rule enforcement
- **Repository pattern** for data access abstraction