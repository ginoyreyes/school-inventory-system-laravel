# School Inventory Management System

A full-stack **School & Computer Laboratory Inventory Management System** built with **Laravel**, **React (Vite)**, and **MySQL**, featuring role-based access control, stock movement tracking, equipment borrowing/returns, printable reports, and a complete audit trail.

---

## Features

- **Role-Based Access Control (RBAC):** `Administrator`, `Inventory Staff`, and `Viewer` permissions enforced via Laravel Gates and Sanctum API tokens.
- **Inventory Management:** Full CRUD for equipment and school supplies, sequential item codes (`INV-0001`), low-stock/out-of-stock alerts, archiving, and restoration.
- **Stock Transactions:** Atomic `Stock In`, `Stock Out`, and `Adjustment` tracking with automatic balance calculation (`TXN-00001`).
- **Borrowing & Returns:** Track borrowed equipment (`BRW-0001`), expected return dates, overdue monitoring, and one-click return processing.
- **Dashboard & Reports:** Real-time stock metrics, 7-day stock movement charts, category breakdowns, and CSV/printable reports.
- **Activity Logs & Settings:** Complete audit log of user actions plus customizable units, item conditions, and low-stock thresholds.

---

## Prerequisites

Make sure the following are installed on your machine before running the project:

- **PHP** 8.2 or higher
- **Composer**
- **MySQL** (e.g., XAMPP, Laragon, WAMP, or standalone MySQL Server)
- **Node.js** 18+ & **npm** *(optional — only needed if you plan to modify and rebuild the React UI)*

---

## Quick Install & Run

### 1. Start MySQL
Open **XAMPP** (or Laragon/WAMP) and start **Apache** and **MySQL**.

### 2. Clone the Repository
```bash
git clone [https://github.com/ginoyreyes/school-inventory-system-laravel.git](https://github.com/ginoyreyes/school-inventory-system-laravel.git)
cd school-inventory-system-laravel
```

### 3. Install & Setup (One Command)
Run this single command to install PHP dependencies, create `.env`, generate the application key, create the `school_inventory` database tables, and seed all initial records:
```bash
composer install && composer setup
```

### 4. Start the Server
```bash
php artisan serve
```

Open your browser and go to:
👉 **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

## Default Login Credentials

| Field | Value |
| :--- | :--- |
| **Username or Email** | `lab.admin` *(or `lab.admin@cvsu.edu.ph`)* |
| **Password** | `Admin1234` |

---

## Optional: Rebuilding the React Frontend

The compiled frontend is already bundled inside `public/`, so no separate Node server is required. If you edit any React source files inside `client/src/`:

1. Build the production bundle:
   ```bash
   cd client
   npm install
   npm run build
   ```
2. Copy the compiled files from `client/dist/` into `public/`:
   - **Windows (CMD):**
     ```cmd
     xcopy /E /Y /I "dist\*" "..\public\"
     ```
   - **macOS / Linux:**
     ```bash
     cp -r dist/* ../public/
     ```

---

## Useful Commands

- **Reset database back to clean seeded data:**
  ```bash
  php artisan migrate:fresh --seed
  ```
- **Clear configuration cache after changing `.env`:**
  ```bash
  php artisan config:clear
  ```
