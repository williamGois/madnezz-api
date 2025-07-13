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

### Services
- **API**: http://localhost:8080
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379