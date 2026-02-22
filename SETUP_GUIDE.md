# Setup Guide - Multi-Tenant Laravel API

This guide provides detailed instructions for setting up and configuring the multi-tenant Laravel API application.

## Prerequisites

### Required Software

- **PHP** >= 8.2 with extensions:
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
- **Composer** - PHP dependency manager
- **MySQL** >= 8.0
- **Git** (optional, for version control)

### Check PHP Version

```bash
php -v
```

### Check Composer

```bash
composer --version
```

## Installation Steps

### 1. Navigate to Project Directory

```bash
cd e:\Mern\ Stact\ Dev\multi-tenant-mern\multi-tenant-laravel
```

### 2. Install Dependencies

```bash
composer install
```

This will install all required Laravel packages and dependencies.

### 3. Environment Configuration

#### Copy Environment File

```bash
cp .env.example .env
```

#### Generate Application Key

```bash
php artisan key:generate
```

#### Configure Environment Variables

Edit the `.env` file with your settings:

```env
# Application
APP_NAME="Multi-Tenant Laravel API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Master Database (for tenant management)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tenant_master
DB_USERNAME=root
DB_PASSWORD=your_mysql_password

# Tenant Database Configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_HOST=127.0.0.1
TENANT_DB_PORT=3306
TENANT_DB_USERNAME=root
TENANT_DB_PASSWORD=your_mysql_password

# CORS (for React frontend)
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### 4. Database Setup

#### Start MySQL

Ensure your MySQL server is running.

#### Create Master Database

Run the setup command:

```bash
php artisan tenant:setup
```

This command will:
- Create the master database (`tenant_master`)
- Run all migrations
- Create the `tenants` table

**Expected Output:**
```
🚀 Setting up master database for multi-tenant application...
Creating database: tenant_master
✅ Database 'tenant_master' created successfully
Running migrations...
✅ Master database setup completed successfully!
```

### 5. Verify Installation

#### Start Development Server

```bash
php artisan serve
```

Server should start on `http://localhost:8000`

#### Test Health Endpoint

Open a browser or use curl:

```bash
curl http://localhost:8000/api/health
```

**Expected Response:**
```json
{
  "status": "OK",
  "timestamp": "2026-02-16T11:54:40.000000Z",
  "environment": "local"
}
```

## Creating Your First Tenant

### Method 1: Using Artisan Command (Interactive)

```bash
php artisan tenant:create
```

Follow the prompts:
```
🏢 Create New Tenant

Tenant ID (lowercase, alphanumeric and hyphens only):
> acme-corp

Tenant Name:
> Acme Corporation

Admin Email:
> admin@acme.com

Admin Password (min 6 characters):
> ********
```

### Method 2: Using API

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

**Success Response:**
```json
{
  "success": true,
  "message": "Tenant registered successfully",
  "data": {
    "tenantId": "acme-corp",
    "tenantName": "Acme Corporation",
    "databaseName": "tenant_acme-corp"
  }
}
```

### Verify Tenant Creation

Check MySQL for the new database:

```sql
SHOW DATABASES LIKE 'tenant_%';
```

You should see `tenant_acme-corp` in the list.

## Testing the API

### 1. Login as Admin

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: acme-corp" \
  -d '{
    "email": "admin@acme.com",
    "password": "password123"
  }'
```

Save the token from the response.

### 2. Get Current User

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "X-Tenant-ID: acme-corp" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 3. Register a New User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: acme-corp" \
  -d '{
    "name": "John Doe",
    "email": "john@acme.com",
    "password": "password123"
  }'
```

## Frontend Integration

### React Frontend Setup

1. Ensure your React app is configured to include headers:

```javascript
// api.js
const API_BASE_URL = 'http://localhost:8000/api';
const TENANT_ID = 'acme-corp'; // Can be dynamic

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'X-Tenant-ID': TENANT_ID,
  },
});

// For authenticated requests
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

2. Update CORS in Laravel `.env`:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000
```

## Troubleshooting

### Database Connection Errors

**Issue:** `SQLSTATE[HY000] [1045] Access denied`

**Solution:** Check MySQL credentials in `.env`:
- Ensure `DB_USERNAME` and `DB_PASSWORD` are correct
- Verify MySQL is running
- Test connection: `mysql -u root -p`

### Tenant Database Not Created

**Issue:** Tenant registration succeeds but database not created

**Solution:**
- Check MySQL user has `CREATE DATABASE` permission
- Verify logs: `tail -f storage/logs/laravel.log`

### CORS Errors in Frontend

**Issue:** Browser blocks requests due to CORS

**Solution:**
- Update `CORS_ALLOWED_ORIGINS` in `.env`
- Clear browser cache
- Restart Laravel server

### Token Authentication Fails

**Issue:** `Unauthorized - Invalid token`

**Solution:**
- Ensure `APP_KEY` is set in `.env`
- Check token format in Authorization header: `Bearer {token}`
- Verify token hasn't expired (7 days default)

## Production Deployment

### Security Considerations

1. **Change APP_DEBUG** to `false` in production
2. **Use strong passwords** for database and admin accounts
3. **Enable HTTPS** for all API requests
4. **Implement rate limiting** for API endpoints
5. **Regular backups** of master and tenant databases

### Environment Configuration

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your api.yourdomain.com

# Use strong, unique key
APP_KEY=base64:YOUR_GENERATED_KEY
```

## Next Steps

- Read [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for complete endpoint details
- Explore the codebase to customize for your needs
- Set up automated backups for databases
- Configure production web server (Nginx/Apache)

## Support

For issues or questions:
- Check Laravel documentation: https://laravel.com/docs
- Review application logs: `storage/logs/laravel.log`
