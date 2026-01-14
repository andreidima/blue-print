# Starting App (Laravel 11, "old school")

This repository is a starter kit meant to be used as the baseline for other Laravel applications.

It's intentionally "old school": server-rendered Blade views (Bootstrap UI) + classic session auth, with a small Vue 3 island only where it helps (currently: a date picker component).

## What's included

### Tech stack
- Laravel `^11.31` (PHP `^8.2`) (`composer.json`)
- Blade + Bootstrap 5 (`resources/views`, `resources/css/app.scss`, `resources/css/andrei.css`)
- Vite build (`vite.config.js`, `package.json`)
- Vue 3 (only for specific widgets, not a full SPA) (`resources/js/app.js`, `resources/js/components/DatePicker.vue`)

### Auth & access control
- Auth scaffolding via `laravel/ui` (classic controllers + Blade views)
- Registration disabled by default; login/logout enabled (`routes/web.php`)
- After login, users land on `/acasa`

### Users module (Admin/SuperAdmin)
- A users CRUD exists at `/utilizatori` (`routes/web.php`, `app/Http/Controllers/UserController.php`, `resources/views/users/*`)
- Users can be searched by name / phone
- Role model:
  - Roles are strings: `SuperAdmin`, `Admin`, others as needed (`app/Models/User.php`)
  - Blade gates: `admin-action`, `super-admin-action` (`app/Providers/AuthServiceProvider.php`)
  - Route middleware: `checkUserRole:...` (`app/Http/Middleware/CheckUserRole.php`)
- Account activation:
  - `activ` flag; inactive users are logged out automatically (`app/Http/Middleware/CheckUserActiv.php`)

### "Tech" area (SuperAdmin)
Accessible under `/tech/*` (restricted to `SuperAdmin`) (`routes/web.php`, `resources/views/tech/*`):
- User impersonation (temporarily become another user) (`app/Http/Controllers/Tech/ImpersonationController.php`)
- Cronjob logs viewer backed by `cron_job_logs` (`app/Models/CronJobLog.php`, `database/migrations/2025_02_15_000500_create_cron_job_logs_table.php`)
- A migrations UI to preview/run/rollback migrations from the browser (`app/Http/Controllers/Tech/MigrationController.php`)

### Optional tooling / packages
- Telescope (`laravel/telescope`, configured in `config/telescope.php`)
- Debugbar (dev dependency) (`barryvdh/laravel-debugbar`)
- PDF generation (`barryvdh/laravel-dompdf`)

## Quick start (local dev)

Prereqs:
- PHP 8.2+
- Composer
- Node.js + npm
- A database (MySQL recommended; SQLite also works for dev)

1) Install dependencies:
```bash
composer install
npm install
```

2) Create environment file:
```bash
copy .env.example .env
php artisan key:generate
```

3) Configure `.env`:
- `APP_URL`
- `DB_*` (or use sqlite)

4) Run migrations:
```bash
php artisan migrate
```

5) Build assets / run dev server:
```bash
npm run dev
php artisan serve
```

Tip: there is also a combined dev command (runs server + queue listener + logs + Vite):
```bash
composer run dev
```

## Important: user schema mismatch (fix this first)

The application logic expects extra columns on `users`:
- `role` (string)
- `activ` (boolean/int)
- `telefon` (string/nullable)

You can see these used in:
- `app/Models/User.php` (`$fillable`)
- `app/Http/Requests/UserRequest.php` (validation rules)
- UI/middleware/routes (`checkUserActiv`, `checkUserRole`, `/utilizatori`)

But the default `users` migration does not create them:
- `database/migrations/0001_01_01_000000_create_users_table.php`

This repo includes a follow-up migration that adds them:
- `database/migrations/2026_01_13_000000_add_role_activ_telefon_to_users_table.php`

Then run:
```bash
php artisan migrate
```

## Creating the first SuperAdmin

After the schema has `role` and `activ`, create a user and promote it via tinker:
```bash
php artisan tinker
```
Then:
```php
$u = \App\Models\User::where('email', 'you@example.com')->first();
$u->update(['role' => 'SuperAdmin', 'activ' => 1]);
```

Note: registration is disabled; either enable it temporarily in `routes/web.php` or insert users via tinker/seeders.

## Project conventions

- Routes are mostly in Romanian (e.g. `/acasa`, `/utilizatori`, `/tech/migratii`) (`routes/web.php`).
- Resource verb overrides are enabled for Romanian URLs (`AppServiceProvider::boot()`):
  - `create` -> `adauga`
  - `edit` -> `modifica`
- Pagination uses Bootstrap styling (`AppServiceProvider::boot()`).

## Safety notes (for real projects)

- The "Tech -> migrations" browser UI can run `php artisan migrate --force`. Keep it restricted to `SuperAdmin` (it is), and consider disabling it entirely outside local/dev environments.
- Telescope and Debugbar should be disabled in production (`TELESCOPE_ENABLED`, `APP_DEBUG`, `DEBUGBAR_ENABLED`).

## Where to start adding features

- New pages/routes: `routes/web.php` + a controller in `app/Http/Controllers/*` + a Blade view in `resources/views/*`
- New data: migration in `database/migrations/*` + model in `app/Models/*`
- New UI styling: `resources/css/app.scss` and/or `resources/css/andrei.css`
- New JS widgets: `resources/js/*` (keep Vue usage focused on components that need it)
