# Madnezz API

A robust Laravel API built with Clean Architecture principles, Docker, PostgreSQL, and comprehensive security features.

## 🚀 Features

- **Laravel 12** - Latest Laravel framework
- **Clean Architecture** - Service layer, Repository pattern, DTOs
- **Authentication** - Laravel Sanctum with role-based access control
- **Security** - Comprehensive security headers and middleware
- **Database** - PostgreSQL with optimized connections
- **Caching** - Redis for sessions, cache, and queues
- **Docker** - Production-ready multi-stage builds
- **Validation** - Multi-layer business rule validation
- **API Resources** - Consistent response formatting
- **Error Handling** - Structured exception handling

## 🐳 Quick Start

```bash
# Start the environment
docker-compose up -d

# Install dependencies and setup
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate

# Access the API at http://localhost:8080
```

## 📚 API Endpoints

### Authentication
- `POST /api/v1/register` - Register user
- `POST /api/v1/login` - Login user
- `POST /api/v1/logout` - Logout user
- `GET /api/v1/profile` - Get user profile
- `PUT /api/v1/profile` - Update profile
- `POST /api/v1/change-password` - Change password

### Task Management (Kanban)
- `GET /api/v1/tasks/kanban` - Get store-based Kanban board
- `GET /api/v1/tasks` - List tasks with filters
- `POST /api/v1/tasks` - Create new task
- `PUT /api/v1/tasks/{id}` - Update task
- `DELETE /api/v1/tasks/{id}` - Delete task

### Services
- **API**: http://localhost:8080
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379

## 🏪 Store-Based Kanban Board

The Kanban board displays tasks organized by store, providing better visibility across locations:

### Middleware Stack
1. **VisibleStoresMiddleware** - Determines which stores the user can see based on hierarchy role
2. **CacheKanbanMiddleware** - Caches responses for improved performance

### Response Format
```json
{
    "success": true,
    "data": {
        "board": [
            {
                "store_id": "uuid",
                "store_name": "Store Name",
                "store_code": "STORE001",
                "tasks": [...],
                "counts": {
                    "TODO": 5,
                    "IN_PROGRESS": 3,
                    "IN_REVIEW": 2,
                    "BLOCKED": 1,
                    "DONE": 10
                }
            }
        ],
        "total_stores": 1
    }
}
```

### Visibility Rules
- **MASTER**: All stores across all organizations
- **GO**: All stores in their organization
- **GR**: All stores in their region
- **Store Manager**: Only their assigned store