# Intan-Elyu Tourism Management System Setup Guide

This guide will help you install and run both the backend (Laravel) and frontend (Mobile/Web) components of the system on your local machine.

## Prerequisites

Before you begin, ensure you have the following installed on your system:
- **PHP** (>= 8.1 recommended)
- **Composer** (PHP dependency manager)
- **Node.js** (and npm)
- **MySQL / MariaDB** (via XAMPP, WAMP, Laragon, or standalone)
- **Git**

---

## 1. Backend Setup (Laravel API)

The backend provides the API, database connection, and dashboard controllers.

### Step 1: Install Dependencies
Open your terminal and navigate to the `backend` folder:
```bash
cd backend
composer install
```

### Step 2: Environment Configuration
Duplicate the example environment file:
```bash
cp .env.example .env
```
Open the newly created `.env` file and configure your database settings. For example, if you are using XAMPP, your settings will usually look like this:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intan_elyu_db
DB_USERNAME=root
DB_PASSWORD=
```
*(Make sure to create a database named `intan_elyu_db` in your MySQL server before proceeding).*

### Step 3: Generate Application Key
```bash
php artisan key:generate
```

### Step 4: Run Migrations and Seeders
This will create the necessary tables and populate them with dummy data:
```bash
php artisan migrate --seed
```

### Step 5: Start the Backend Server
Run the local development server:
```bash
php artisan serve
```
The API will now be available at `http://127.0.0.1:8000`. Keep this terminal window open.

---

## 2. Frontend Mobile Setup (Capacitor / HTML / JS)

The mobile frontend is built using standard web technologies (HTML, CSS, JS) and runs using Capacitor for native builds, but it can be served locally for development.

### Step 1: Navigate to the Mobile Directory
Open a **new** terminal window and navigate to the mobile frontend folder:
```bash
cd Frontend/Mobile
```

### Step 2: Install Dependencies
Install the required node modules for Capacitor (if you plan on building the Android app):
```bash
npm install
```

### Step 3: Start the Frontend Development Server
Since the source code is built on PHP (using views like `dashboard.php` and `map.php`), you need to serve the `src` directory using PHP's built-in web server.
```bash
cd src
php -S localhost:3000
```
Your mobile web app will now be accessible at `http://localhost:3000`. You can visit this URL in your web browser. 

*(Tip: Open Chrome DevTools with F12 and toggle the "Device Toolbar" to view the app in mobile dimensions).*

---

## 3. Running the Android App (Optional)

If you wish to test or build the application natively for Android using Capacitor:

1. Ensure you have **Android Studio** installed.
2. From the `Frontend/Mobile` directory, run:
```bash
npx cap sync android
```
3. Open the project in Android Studio:
```bash
npx cap open android
```
4. Build and run the app on an emulator or a physical device connected via USB.

---

## Troubleshooting

- **CORS Issues:** If the frontend cannot communicate with the backend, ensure your backend `.env` has the correct `APP_URL` and your Laravel CORS settings (`config/cors.php`) allow requests from `http://localhost:3000`.
- **Database Connection Errors:** Verify your MySQL server is running (e.g., Apache and MySQL modules are started in XAMPP control panel) and the credentials in `.env` are correct.
- **Missing Images:** Run `php artisan storage:link` in the backend directory to create a symbolic link for local storage images.
