# API Documentation - Multi-Tenant Laravel API

Complete API reference for the multi-tenant Laravel application.

## Base URL

```
http://localhost:8000/api
```

## Authentication

Most endpoints require authentication using JWT tokens. Include the token in the Authorization header:

```
Authorization: Bearer {your_token}
```

## Tenant Identification

For tenant-specific endpoints, include the tenant ID in one of two ways:

**Method 1: Header (Recommended)**
```
X-Tenant-ID: acme-corp
```

**Method 2: Subdomain**
```
Use subdomain: acme-corp.yourdomain.com
```

---

## Endpoints

### Health Check

#### GET /health

Check API server status.

**Authentication:** Not required  
**Tenant ID:** Not required

**Response:**
```json
{
  "status": "OK",
  "timestamp": "2026-02-16T11:54:40.000000Z",
  "environment": "local"
}
```

---

## Tenant Management

### Register Tenant

#### POST /tenants/register

Create a new tenant with isolated database.

**Authentication:** Not required  
**Tenant ID:** Not required

**Request Body:**
```json
{
  "tenantId": "acme-corp",
  "tenantName": "Acme Corporation",
  "adminEmail": "admin@acme.com",
  "adminPassword": "password123"
}
```

**Validation Rules:**
- `tenantId`: Required, unique, lowercase alphanumeric and hyphens only
- `tenantName`: Required, max 255 characters
- `adminEmail`: Required, valid email
- `adminPassword`: Required, minimum 6 characters

**Success Response (201):**
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

**Error Response (400):**
```json
{
  "success": false,
  "message": "Tenant ID already exists"
}
```

### Get Tenant

#### GET /tenants/{tenantId}

Get information about a specific tenant.

**Authentication:** Not required  
**Tenant ID:** Not required

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant_id": "acme-corp",
    "tenant_name": "Acme Corporation",
    "database_name": "tenant_acme-corp",
    "status": "active",
    "created_at": "2026-02-16T11:54:40.000000Z"
  }
}
```

### List Tenants

#### GET /tenants

Get all tenants (should be protected in production).

**Authentication:** Not required  
**Tenant ID:** Not required

**Success Response (200):**
```json
{
  "success": true,
  "count": 2,
  "data": [
    {
      "tenant_id": "acme-corp",
      "tenant_name": "Acme Corporation",
      "database_name": "tenant_acme-corp",
      "status": "active",
      "created_at": "2026-02-16T11:54:40.000000Z"
    }
  ]
}
```

---

## Authentication

### Register User

#### POST /auth/register

Register a new user in the tenant.

**Authentication:** Not required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@acme.com",
  "password": "password123"
}
```

**Validation Rules:**
- `name`: Required, max 255 characters
- `email`: Required, valid email
- `password`: Required, minimum 6 characters

**Success Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "id": 2,
    "name": "John Doe",
    "email": "john@acme.com",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

### Login

#### POST /auth/login

Authenticate user and receive JWT token.

**Authentication:** Not required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "admin@acme.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "id": 1,
    "name": "Admin",
    "email": "admin@acme.com",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

### Logout

#### POST /auth/logout

Logout current user (revoke token).

**Authentication:** Required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

### Get Current User

#### GET /auth/me

Get authenticated user information.

**Authentication:** Required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Admin",
    "email": "admin@acme.com",
    "created_at": "2026-02-16T11:54:40.000000Z"
  }
}
```

---

## User Management

### List Users

#### GET /users

Get all users in the tenant.

**Authentication:** Required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "count": 3,
  "data": [
    {
      "id": 1,
      "name": "Admin",
      "email": "admin@acme.com",
      "created_at": "2026-02-16T11:54:40.000000Z"
    },
    {
      "id": 2,
      "name": "John Doe",
      "email": "john@acme.com",
      "created_at": "2026-02-16T12:30:15.000000Z"
    }
  ]
}
```

### Get User

#### GET /users/{id}

Get a specific user by ID.

**Authentication:** Required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "John Doe",
    "email": "john@acme.com",
    "created_at": "2026-02-16T12:30:15.000000Z"
  }
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "User not found"
}
```

### Update User

#### PUT /users/{id}

Update user information.

**Authentication:** Required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (all fields optional):**
```json
{
  "name": "John Updated",
  "email": "john.new@acme.com",
  "password": "newpassword123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "id": 2,
    "name": "John Updated",
    "email": "john.new@acme.com",
    "created_at": "2026-02-16T12:30:15.000000Z",
    "updated_at": "2026-02-16T13:45:20.000000Z"
  }
}
```

### Delete User

#### DELETE /users/{id}

Delete a user.

**Authentication:** Required  
**Tenant ID:** Required

**Headers:**
```
X-Tenant-ID: acme-corp
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

---

## Error Responses

### Common Error Codes

- **400 Bad Request**: Invalid request data or validation failure
- **401 Unauthorized**: Missing or invalid authentication token
- **404 Not Found**: Resource not found
- **500 Internal Server Error**: Server error

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error (only in debug mode)"
}
```

---

## Testing Database Isolation

To verify complete tenant isolation:

1. Create two tenants (e.g., `tenant-a` and `tenant-b`)
2. Register a user with the same email in both tenants
3. Login to each tenant separately using the tenant-specific header
4. Verify that both users exist independently in their respective databases

Each tenant operates in complete isolation with no data leakage.

---

## Rate Limiting

Currently no rate limiting is implemented. For production:
- Implement Laravel's built-in rate limiting
- Configure per-endpoint limits
- Use Redis for distributed rate limiting

---

## Postman Collection

Import the Postman collection for testing:

1. Create a new environment in Postman
2. Set variables:
   - `base_url`: `http://localhost:8000/api`
   - `tenant_id`: Your tenant ID
   - `token`: JWT token (auto-set after login)
3. Import endpoints from this documentation
4. Test all flows systematically

---

## Security Best Practices

### In Production:

1. **Use HTTPS**: Always use SSL/TLS encryption
2. **Secure Tokens**: Store tokens securely (httpOnly cookies or secure storage)
3. **Validate Input**: All user input is validated server-side
4. **Rate Limiting**: Implement rate limiting to prevent abuse
5. **SQL Injection**: Use parameterized queries (already implemented)
6. **CORS**: Restrict `CORS_ALLOWED_ORIGINS` to trusted domains only
7. **Passwords**: Always hash passwords (already implemented with bcrypt)
8. **Token Expiration**: Tokens expire after 7 days (configurable)

---

## Support & Further Reading

- **Laravel Documentation**: https://laravel.com/docs
- **JWT Best Practices**: https://tools.ietf.org/html/rfc7519
- **Multi-Tenancy Patterns**: Various architectures and approaches
