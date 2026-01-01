# Project Setup Guide

This project will run on MySQL instead of the default SQLite. Follow the steps below to get a local copy running on your machine.

## Prerequisites
- PHP (matching Laravel requirements) with `pdo_mysql`
- Composer
- Node.js + npm (or your preferred package manager for front-end tooling)
- MySQL server (Homebrew on macOS, MySQL Installer/Chocolatey on Windows, or your distro’s package manager)

## Local Environment Checklist
1. **Clone & dependencies**
   - **macOS/Linux (bash)**
     ```bash
     git clone <repo-url>
     cd asd
     composer install
     npm install
     ```
   - **Windows (PowerShell)**
     ```powershell
     git clone <repo-url>
     Set-Location asd
     composer install
     npm install
     ```
2. **Environment file**
   - **macOS/Linux (bash)**
     ```bash
     cp .env.example .env
     php artisan key:generate
     ```
   - **Windows (PowerShell)**
     ```powershell
     Copy-Item .env.example .env
     php artisan key:generate
     ```
3. **Install & start MySQL**
   - **macOS (Homebrew)**
     ```bash
     brew install mysql
     brew services start mysql
     # optional hardening
     mysql_secure_installation
     ```
   - **Windows (MySQL Installer or Chocolatey)**
     ```powershell
     # Using Chocolatey (run from elevated PowerShell)
     choco install mysql
     net start mysql
     ```
     If you use the official MySQL Installer, choose the Server component, enable `mysql80` to start automatically, then start it via the Services panel or `net start mysql80`.
4. **Create database + user**
   - **macOS/Linux (bash)**
     ```bash
     mysql -u root <<'SQL'
     CREATE DATABASE IF NOT EXISTS your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     CREATE USER IF NOT EXISTS 'your_db_user'@'localhost' IDENTIFIED BY 'your_db_password';
     GRANT ALL PRIVILEGES ON your_db_name.* TO 'your_db_user'@'localhost';
     FLUSH PRIVILEGES;
     SQL
     ```
   - **Windows (PowerShell)**
     ```powershell
     & "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p @"
     CREATE DATABASE IF NOT EXISTS your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     CREATE USER IF NOT EXISTS 'your_db_user'@'localhost' IDENTIFIED BY 'your_db_password';
     GRANT ALL PRIVILEGES ON your_db_name.* TO 'your_db_user'@'localhost';
     FLUSH PRIVILEGES;
     "@
     ```
   Replace `your_db_name`, `your_db_user`, and `your_db_password` with values you prefer before sharing credentials.
5. **Update `.env` with MySQL credentials**
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_db_name
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```
6. **Migrate and seed**
   ```terminal
   php artisan migrate --seed
   ```
   This executes `mysqlSeeder`, creating demo hotels, room types, rooms, staff, and users.
7. **Run the app**
   ```terminal
   php artisan serve
   npm run dev   # if you need Vite assets
   ```

## Seeded accounts (demo only)
- **Admin (back office)** — username `ADMIN_BOOT_USERNAME` (default `admin`), password `ADMIN_BOOT_PASSWORD` (default `Adm1n#2025!`), name `ADMIN_BOOT_NAME` (default `System Administrator`). See `database/seeders/AdminUserSeeder.php`.
- **Staff per hotel (20 hotels)** — manager password `Mngr#2025!`; front desk password `Front#2025!`. Emails are the slugged staff name with dots at `@lexiqa.com` (e.g., `amelia.patel@lexiqa.com`, `isla.turner@lexiqa.com`). See `database/seeders/StaffUserSeeder.php` for the full name lists.
- **Customer web accounts** (seeded in `database/seeders/ReservationsSeeder.php`):

  | Name                | Email                         | Password     |
  | ------------------- | ----------------------------- | ------------ |
  | Alice Customer      | alice.customer@asd.test       | Alice#2025!  |
  | Bob Booker          | bob.booker@asd.test           | Bob#2025!    |
  | Catherine Planner   | cat.planner@asd.test          | Cat#2025!    |
  | Diego Traveler      | diego.traveler@asd.test       | Diego#2025!  |
  | Evelyn Guest        | evelyn.guest@asd.test         | Evelyn#2025! |
  | Farah Explorer      | farah.explorer@asd.test       | Farah#2025!  |

All credentials are for local/demo use only—rotate or override via env when deploying.
