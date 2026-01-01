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
