# GET /api/v1/hierarchy/me Endpoint Documentation

## Overview

The `/hierarchy/me` endpoint returns the current authenticated user's complete hierarchy information, including their position in the organizational structure, departments, and permissions.

## Endpoint Details

- **URL**: `/api/v1/hierarchy/me`
- **Method**: `GET`
- **Authentication**: Required (JWT)
- **Middleware**: `jwt.auth`, `org.context`
- **Route Name**: `hierarchy.me`

## Response Structure

### Success Response (200 OK)

```json
{
    "success": true,
    "data": {
        "user": {
            "id": "123e4567-e89b-12d3-a456-426614174000",
            "name": "João Silva",
            "email": "joao@example.com",
            "hierarchy_role": "GR"
        },
        "organization": {
            "id": "456e7890-e89b-12d3-a456-426614174000",
            "name": "Empresa ABC",
            "code": "ABC001"
        },
        "position": {
            "id": "789e0123-e89b-12d3-a456-426614174000",
            "level": "GR",
            "unit_id": "012e3456-e89b-12d3-a456-426614174000",
            "unit_name": "Região Sul",
            "unit_type": "regional",
            "unit_code": "RS001"
        },
        "hierarchy_path": [
            {
                "level": "organization",
                "name": "Empresa ABC",
                "id": "456e7890-e89b-12d3-a456-426614174000"
            },
            {
                "level": "company",
                "name": "Sede",
                "id": "567e8901-e89b-12d3-a456-426614174000"
            },
            {
                "level": "regional",
                "name": "Região Sul",
                "id": "012e3456-e89b-12d3-a456-426614174000"
            }
        ],
        "departments": [
            {
                "id": "345e6789-e89b-12d3-a456-426614174000",
                "name": "Administrativo",
                "code": "ADM",
                "type": "administrative"
            },
            {
                "id": "456e7890-e89b-12d3-a456-426614174000",
                "name": "Financeiro",
                "code": "FIN",
                "type": "financial"
            }
        ],
        "permissions": ["task.create", "task.edit", "store.view"]
    }
}
```

## Response Fields

### user
- **id**: User's unique identifier
- **name**: User's full name
- **email**: User's email address
- **hierarchy_role**: User's role (MASTER, GO, GR, STORE_MANAGER)

### organization
- **id**: Organization's unique identifier
- **name**: Organization name
- **code**: Organization code
- **Note**: `null` for MASTER users without specific organization context

### position
- **id**: Position's unique identifier
- **level**: Position level (matches hierarchy_role)
- **unit_id**: Organization unit ID where user is positioned
- **unit_name**: Name of the organization unit
- **unit_type**: Type of unit (company, regional, store)
- **unit_code**: Unit's code

### hierarchy_path
Array showing the complete organizational path from top to bottom:
- Each level contains:
  - **level**: Type of organizational level
  - **name**: Name of the unit/organization
  - **id**: Unique identifier

### departments
Array of departments the user has access to:
- **id**: Department's unique identifier
- **name**: Department name
- **code**: Department code (ADM, FIN, MKT, etc.)
- **type**: Department type

### permissions
Array of permission strings assigned to the user.

## Examples by Role

### MASTER User Response
```json
{
    "user": {
        "hierarchy_role": "MASTER"
    },
    "organization": null,
    "position": null,
    "hierarchy_path": [],
    "departments": ["*"],
    "permissions": ["*"]
}
```

### GO User Response
```json
{
    "user": {
        "hierarchy_role": "GO"
    },
    "position": {
        "level": "GO",
        "unit_type": "company"
    },
    "hierarchy_path": [
        {"level": "organization", "name": "Empresa XYZ"},
        {"level": "company", "name": "Sede"}
    ]
}
```

### GR User Response
```json
{
    "user": {
        "hierarchy_role": "GR"
    },
    "position": {
        "level": "GR",
        "unit_type": "regional"
    },
    "hierarchy_path": [
        {"level": "organization", "name": "Empresa XYZ"},
        {"level": "company", "name": "Sede"},
        {"level": "regional", "name": "Região Norte"}
    ]
}
```

### Store Manager Response
```json
{
    "user": {
        "hierarchy_role": "STORE_MANAGER"
    },
    "position": {
        "level": "STORE_MANAGER",
        "unit_type": "store"
    },
    "hierarchy_path": [
        {"level": "organization", "name": "Empresa XYZ"},
        {"level": "company", "name": "Sede"},
        {"level": "regional", "name": "Região Sul"},
        {"level": "store", "name": "Loja Centro"}
    ]
}
```

## Error Responses

### 401 Unauthorized
```json
{
    "success": false,
    "message": "Unauthenticated"
}
```

### 500 Internal Server Error
```json
{
    "success": false,
    "message": "Error message details"
}
```

## Usage Example

```javascript
// JavaScript/Axios example
const response = await axios.get('/api/v1/hierarchy/me', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
    }
});

console.log(response.data.data.user.hierarchy_role);
console.log(response.data.data.hierarchy_path);
```

## Notes

- The endpoint uses the OrganizationContextMiddleware to inject organizational context
- The response is cached by the OrganizationContextMiddleware for performance
- hierarchy_path is built dynamically from parent units
- MASTER users may have limited organization context unless explicitly set