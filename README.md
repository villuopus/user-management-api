# Pull Request: User Management API

## Description
This PR adds a complete user management REST API with authentication, file uploads, caching, email notifications, and logging.

## Features
- User CRUD operations with search and pagination  
- JWT-based authentication and authorization
- Password reset flow via email
- Bulk user import/export (CSV)
- File upload service for avatars and documents
- File-based caching layer
- Request logging and audit trail
- Rate limiting middleware
- CORS support

## Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/users | List all users |
| GET | /api/users/{id} | Get user by ID |
| POST | /api/users | Create user |
| PUT | /api/users/{id} | Update user |
| DELETE | /api/users/{id} | Delete user |
| GET | /api/users/search?q= | Search users |
| POST | /api/users/bulk-import | Import users from CSV |
| GET | /api/users/export | Export all users as CSV |
| POST | /api/login | Authenticate |
| POST | /api/register | Register new user |
| POST | /api/forgot-password | Request password reset |
| POST | /api/users/{id}/change-password | Change password |
| POST | /api/reset-password | Reset password with token |
| GET | /api/me | Get current user profile |
| GET | /api/health | Health check |
| GET | /api/debug | Debug info |
| GET | /api/phpinfo | PHP info |

## Setup
```bash
composer install
php migrations/001_create_users.php
php -S localhost:8000 -t public/
```

## Default Admin Credentials
- Email: admin@acme.com
- Password: admin123

## Testing
```bash
./vendor/bin/phpunit tests/
```

## TODO
- [ ] Add input validation
- [ ] Implement proper error handling
- [ ] Add API documentation
- [ ] Set up CI/CD
