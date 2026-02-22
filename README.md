# Multi-Tenant Laravel API

A multi-tenant Laravel API application with database isolation. Each tenant has its own MySQL database, providing complete data isolation and scalability.

## Features

- 🏢 **Multi-Tenancy**: Each tenant gets their own isolated MySQL database
- 🔐 **JWT Authentication**: Secure token-based authentication
- 🚀 **RESTful API**: Clean and well-documented API endpoints
- 🔄 **Dynamic Database Switching**: Automatic tenant database connection management
- 📊 **Connection Caching**: Optimized database connection pooling
- 🛠️ **Artisan Commands**: CLI tools for setup and management
- 📝 **Comprehensive Documentation**: API docs and setup guides

## Architecture

### Multi-Database Tenancy

This application uses a **multi-database** approach:
- **Master Database**: Stores tenant information and metadata
- **Tenant Databases**: Each tenant has a separate database (`tenant_{tenantId}`)

### Tenant Identification

Tenants are identified by:
1. **X-Tenant-ID Header**: Preferred method for API requests
2. **Subdomain**: Automatic extraction from `{tenantId}.yourdomain.com`

## Requirements

- PHP >= 8.2
- Composer
- MySQL >= 8.0
- Laravel 11

## Quick Start

### 1. Clone and Install

```bash
cd e:\Mern\ Stact\ Dev\multi-tenant-mern\multi-tenant-laravel
composer install
```

### 2. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tenant_master
DB_USERNAME=root
DB_PASSWORD=your_password

# Tenant database configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_HOST=127.0.0.1
TENANT_DB_PORT=3306
TENANT_DB_USERNAME=root
TENANT_DB_PASSWORD=your_password
```

### 4. Setup Master Database

```bash
php artisan tenant:setup
```

### 5. Start Development Server

```bash
php artisan serve
```

Server runs on `http://localhost:8000`

## Usage

### Create a Tenant

**Via CLI:**
```bash
php artisan tenant:create
```

**Via API:**
```bash
curl -X POST http://localhost:8000/api/tenants/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenantId": "acme-corp",
    "tenantName": "Acme Corporation",
    "adminEmail": "admin@acme.com",
    "adminPassword": "password123"
  }'
```

### Authenticate

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: acme-corp" \
  -d '{
    "email": "admin@acme.com",
    "password": "password123"
  }'
```

Response includes a JWT token for authenticated requests.

### Make Authenticated Requests

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "X-Tenant-ID: acme-corp" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## API Endpoints

### Tenant Management
- `POST /api/tenants/register` - Register new tenant
- `GET /api/tenants/{tenantId}` - Get tenant information
- `GET /api/tenants` - List all tenants

### Authentication (requires X-Tenant-ID)
- `POST /api/auth/register` - Register user in tenant
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout (authenticated)
- `GET /api/auth/me` - Get current user (authenticated)

### User Management (requires authentication)
- `GET /api/users` - List all users
- `GET /api/users/{id}` - Get user by ID
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

See [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for complete API documentation.

## Artisan Commands

```bash
# Setup master database and run migrations
php artisan tenant:setup

# Create a new tenant interactively
php artisan tenant:create
```

## Frontend Integration

This Laravel API is designed to work with a separate React frontend. Configure CORS in `.env`:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000
```

The React frontend should include the `X-Tenant-ID` header in all tenant-specific requests.

## Project Structure

```
app/
├── Console/Commands/     # Artisan commands
├── Http/
│   ├── Controllers/Api/  # API controllers
│   └── Middleware/       # Custom middleware
├── Models/               # Eloquent models
└── Services/             # Business logic services
config/                   # Configuration files
database/
├── migrations/           # Database migrations
routes/
└── api.php              # API routes
```

## Learn More

- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Detailed setup instructions
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - Complete API documentation

## License

This is a custom multi-tenant application project.
