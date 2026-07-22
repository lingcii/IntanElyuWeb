# Local Development Setup (No XAMPP / No Apache)

This project no longer requires XAMPP, Apache, or the `htdocs` folder.
Everything runs through PHP's built-in server and Laravel's `artisan serve`.

---

## Prerequisites

Make sure the following are installed and available in your `PATH`:

| Tool | Minimum Version | Check |
|------|----------------|-------|
| PHP  | 8.2+           | `php -v` |
| Composer | any | `composer -V` |
| Node.js  | 18+ | `node -v` |
| npm      | 9+  | `npm -v` |

You do **not** need XAMPP, Apache, or any local MySQL server.  
The database runs on Railway (already configured in `backend/.env`).

---

## Quick Start

Double-click `start-dev.bat` from the project root, or run in PowerShell:

```powershell
.\start-dev.ps1
```

This opens two terminal windows:

| Window | Command | URL |
|--------|---------|-----|
| Laravel Backend | `php artisan serve --host=127.0.0.1 --port=8000` | http://127.0.0.1:8000 |
| PHP Frontend    | `php -S localhost:8080` | http://localhost:8080 |

Open http://localhost:8080/login.php to use the app.

---

## Manual Start (step by step)

### 1 — Laravel Backend

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

Runs the API at **http://127.0.0.1:8000**.  
Keep this terminal open — do not close it.

### 2 — PHP Frontend

```bash
cd Frontend/Website/Frontend
php -S localhost:8080
```

Serves the PHP pages at **http://localhost:8080**.  
Open http://localhost:8080/login.php in your browser.


## Architecture

```
Browser
  │
  ├─ http://localhost:8080          ← PHP built-in server
  │    Frontend/Website/Frontend/   (PHP pages, login, dashboards)
  │    Talks to Laravel via fetch() using window.API_CONFIG
  │

  └─ http://127.0.0.1:8000          ← Laravel (php artisan serve)
       backend/                     (REST API)
       Connects to Railway MySQL
```

---

## How Each Piece Is Configured

### Backend — `backend/.env`

```dotenv
APP_URL=http://127.0.0.1:8000

SESSION_DRIVER=file
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=false
```

- `APP_URL` points to `artisan serve`, not Apache/localhost:80.
- `SESSION_DRIVER=file` — sessions stored in `backend/storage/framework/sessions/`.  
  No database or Redis required for local development.
- `SESSION_SECURE_COOKIE=false` — allows cookies over plain HTTP in dev.
- `SESSION_SAME_SITE=lax` — allows cross-origin requests from the PHP frontend
  while still protecting against CSRF.

### Backend — `backend/config/cors.php`

All three dev origins are explicitly allowed with credentials:

```
http://localhost:8080   ← PHP frontend

http://127.0.0.1:8000   ← Laravel itself
```

`supports_credentials: true` is required for the session cookie to be sent
on cross-origin fetch requests.

### PHP Frontend — `scripts/api-config.js`

Builds the backend URL dynamically:

```js
const apiHost = window.location.hostname;   // "localhost"
const apiPort = '8000';
const baseUrl = `${window.location.protocol}//${apiHost}:${apiPort}`;
```

When the page is served from `http://localhost:8080`, the API calls go to
`http://localhost:8000/api/...` — the session cookie domain matches because
both use `localhost`.

### Backend — `public/.htaccess`

The `.htaccess` file is still present for **future Apache/production** use.
It has **no effect** when running `php artisan serve` — the built-in PHP
router handles all requests natively, routing everything to `public/index.php`
exactly as the htaccess rules would.

---

## First-Time Setup After Moving the Project

If you moved the project folder (e.g. from `C:\xamp\htdocs\...` to `C:\Projects\...`):

```bash
# 1. Install backend dependencies
cd backend
composer install

# 2. Clear config cache (important after moving)
php artisan config:clear
php artisan cache:clear
php artisan route:clear

```

The `backend/storage` and `backend/bootstrap/cache` folders must be writable.
On Windows this is normally automatic — if you get permission errors, right-click
the folder → Properties → Security → give your user Full Control.

---

## Troubleshooting

### "CORS policy" error in browser console

Check that `backend/config/cors.php` has the origin that matches your browser URL.
The PHP frontend must be on `localhost` (not `127.0.0.1`) because `api-config.js`
always uses `window.location.hostname` to build the API URL — mixing `localhost` and
`127.0.0.1` on the same browser tab breaks the session cookie.

### Session not persisting / logged out immediately

1. Confirm `SESSION_DOMAIN=null` in `.env` (do not set it to `localhost`).
2. Confirm both the PHP frontend and backend use the same hostname (`localhost` or `127.0.0.1`) — not a mix of both.
3. Run `php artisan config:clear` after any `.env` change.

### "php: command not found"

PHP must be in your system `PATH`. With a standalone PHP install:
- Add the PHP folder (e.g. `C:\php`) to `System Environment Variables → Path`.
- Restart your terminal after changing the PATH.

### Port already in use

Change the port in the startup command:
```bash
php artisan serve --host=127.0.0.1 --port=8001
php -S localhost:8081
```
Then update `cors.php` to include the new ports.

---

## Removing XAMPP Completely (Optional)

Once you've confirmed everything works with the new setup:

1. Move the project folder out of `htdocs` to wherever you want (e.g. `C:\Projects\`).
2. Stop the XAMPP Apache and MySQL services from the XAMPP Control Panel.
3. You can uninstall XAMPP entirely — nothing in this project depends on it.

The Railway MySQL database connection in `backend/.env` is unaffected by any local changes.
