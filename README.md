# Laravel commands to start up the application

## Create a Laravel application using Laravel
### composer create-project laravel/laravel mybackend-app

## Run migrations after installation
### php artisan migrate

## Run server locally to be sure that things works
### php artisan serve

## Add sample db props (FOR SQLITE)
### DB_USERNAME=root
### DB_PASSWORD=root
### DB_DATABASE=database/database.sqlite

## Run migrarions again
### php artisan migrate

## Get a token manager
### composer require laravel/sanctum

## Run token manager
### php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

## Run migrations again to register token manager
### php artisan migrate

## Create an auth controller
### php artisan make:controller AuthController

## Create notes table
### php artisan make:model Notes -m

## Install php api
### php artisan install:api

## After the installation of php api, put your routes in /routes/api.php

## List the available routes
### php artisan route:list

## Clear cache
### php artisan route:clear
### php artisan cache:clear

## Query db/system via the terminal
### php artisan tinker 

## NOTE: Any time you make modifications to a model please run migrations

## Refresh database (You lose all your data)
### php artisan migrate:fresh

## Create a middleware
### php artisan make:middleware EnsureAuthenticated

## Check current php version
### php artisan --version

## Fresh migration
### php artisan migrate:fresh
### Use when you update the migration and models

## Clears cache
### php artisan optimize:clear

## Create a mailer
## php artisan make:mail SendOtpMail

## Backblaze private download signing

Required env vars:
- `B2_KEY_ID`
- `B2_APPLICATION_KEY`
- `B2_BUCKET_ID`
- `B2_BUCKET_NAME`
- `B2_SIGNED_URL_TTL_SECONDS` (default: `3600`)
- `B2_ALLOWED_PREFIX` (optional)

Endpoint contract:
- `POST /api/sign-download` (requires Bearer auth)
- Request accepts either:
  - `{ "sourceUrl": "https://f005.backblazeb2.com/file/<bucket>/<path/file.mp3>" }`
  - `{ "fileName": "path/file.mp3" }`
- Response `200`:
  - `{ "url": "<signed_download_url>", "fileName": "path/file.mp3", "expiresIn": 3600 }`

Health check:
- `GET /api/health/storage-signing`
- Verifies config and Backblaze authorization path with masked hints only.

Example curl:
```bash
curl -X POST "$APP_URL/api/sign-download" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"fileName":"audio/sample.mp3"}'
```

Troubleshooting matrix:
- `401 bad_auth_token` from Backblaze: invalid `B2_KEY_ID` or `B2_APPLICATION_KEY`.
- `bad_bucket_id`: stale `B2_BUCKET_ID`; service falls back to authorize response bucket id once.
- `expired token`: backend automatically refreshes account auth token cache and retries transient failures.
- `400 sourceUrl bucket does not match configured bucket`: frontend sent wrong bucket URL.
- `403 File is outside allowed prefix`: `B2_ALLOWED_PREFIX` restriction is active.

Key rotation (zero downtime):
1. Create a new Backblaze key with required bucket/file permissions.
2. Update Render env with new `B2_KEY_ID` + `B2_APPLICATION_KEY`.
3. Deploy/restart service.
4. Verify `GET /api/health/storage-signing` returns `ok: true`.
5. Revoke old Backblaze key after successful verification.
