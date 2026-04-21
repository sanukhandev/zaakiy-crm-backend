# ZaakiyCRM API - Swagger/OpenAPI Documentation

## Base URL
- **Local**: `http://localhost:8000/api`
- **Production**: `https://api.zaakicrm.com/api`

## Authentication
All protected endpoints require a Bearer token (JWT from Supabase Auth) in the Authorization header:
```
Authorization: Bearer <your_supabase_jwt_token>
```

## Available Routes

### Health Check
**GET** `/v1/health`

No authentication required. Check if the API is running.

**Response (200)**:
```json
{
  "status": "ok"
}
```

---

### Auth - Get Current User
**GET** `/v1/me`

Requires authentication. Retrieve the authenticated user's information from the JWT token.

**Response (200)**:
```json
{
  "success": true,
  "data": {
    "user_id": "3a0b1f1e-c6ff-4b9f-a4b3-d230609c41ce",
    "email": "test@demo.com",
    "role": "authenticated",
    "tenant_id": null,
    "jwt": { "...full decoded JWT..." }
  }
}
```

**Response (401)**: Unauthorized

---

### Session - Get Session
**GET** `/v1/session`

Requires authentication. Retrieve the authenticated user's current session details.

**Response (200)**:
```json
{
  "success": true,
  "data": {
    "user_id": "3a0b1f1e-c6ff-4b9f-a4b3-d230609c41ce",
    "email": "test@demo.com",
    "role": "authenticated",
    "tenant_id": null
  }
}
```

**Response (401)**: Unauthorized

---

### Leads - List All Leads
**GET** `/v1/leads`

Requires authentication. Retrieve a list of all leads in the CRM.

**Response (200)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "status": "new",
      "created_at": "2026-04-21T10:30:00Z"
    }
  ]
}
```

**Response (401)**: Unauthorized

---

### Leads - Create New Lead
**POST** `/v1/leads`

Requires authentication. Add a new lead to the CRM.

**Request Body**:
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+1987654321",
  "status": "new"
}
```

**Response (201)**:
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+1987654321",
    "created_at": "2026-04-21T10:35:00Z"
  }
}
```

**Response (401)**: Unauthorized  
**Response (422)**: Validation error

---

### Leads - Update Lead
**PATCH** `/v1/leads/{id}`

Requires authentication. Update an existing lead's information.

**URL Parameters**:
- `id` (integer, required): The lead ID

**Request Body**:
```json
{
  "name": "Jane Smith",
  "email": "jane.smith@example.com",
  "phone": "+1987654321",
  "status": "qualified"
}
```

**Response (200)**:
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane.smith@example.com",
    "phone": "+1987654321",
    "updated_at": "2026-04-21T10:40:00Z"
  }
}
```

**Response (401)**: Unauthorized  
**Response (404)**: Lead not found

---

## Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthorized"
}
```
Occurs when:
- Missing Authorization header
- Invalid or expired JWT token
- Token signature verification failed

### 422 Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email field is required"]
  }
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

---

## Testing with cURL

### Health Check
```bash
curl -X GET http://localhost:8000/api/v1/health
```

### Get Current User (requires token)
```bash
curl -X GET http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

### Create a Lead
```bash
curl -X POST http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "status": "new"
  }'
```

### Update a Lead
```bash
curl -X PATCH http://localhost:8000/api/v1/leads/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Smith",
    "status": "qualified"
  }'
```

---

## Security Notes

1. Always use HTTPS in production
2. Keep your Supabase JWT token secret
3. Bearer tokens expire - handle token refresh accordingly
4. All protected endpoints verify the JWT signature and extract user claims
5. The API uses ES256 JWT algorithm for Supabase authentication

---

## Generated
Last updated: April 21, 2026
