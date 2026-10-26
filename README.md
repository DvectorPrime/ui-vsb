# UI-VSB: Vehicle Service Booking & Payment Platform
### University of Ibadan Maintenance Directorate

A web-based platform for university staff and students to register vehicles, view real-time workshop bay availability using the asynchronous **Fetch API**, book service appointments, process simulated payments, and track workshop service progression via a live 5-step status stepper.

---

## 1. Technical Stack & Architecture

Strictly adheres to course constraints (**Zero third-party frameworks/packages**):
* **Frontend**: HTML5, Vanilla CSS3 (Custom Design System with CSS variables), Vanilla ECMAScript (Fetch API, asynchronous DOM rendering).
* **Backend**: PHP 8.2 Object-Oriented Architecture (strict typing, PSR-style conventions, custom autoloader, singleton PDO database connection).
* **Database**: MySQL / MariaDB (InnoDB engine, foreign key constraints, `idx_slot` composite index for slot calculations, ACID transactions with `SELECT ... FOR UPDATE` pessimistic concurrency locking).
* **Payment**: Purely simulated gateway implementing `PaymentGatewayInterface` (zero external network dependencies, no real credit cards, full Luhn validation).

---

## 2. Seed Logins & Test Accounts

Importing `database/ui_vsb.sql` automatically populates the seed accounts with real bcrypt hashes:

| Role | Email | Password | Full Name |
|---|---|---|---|
| **Admin** | `admin@ui.edu.ng` | `Admin@123` | Workshop Admin |
| **Customer** | `customer@ui.edu.ng` | `Customer@123` | Adeyemi Johnson |
| **Customer** | `kemi.adebayo@ui.edu.ng` | `Customer@123` | Kemi Adebayo |
| **Customer** | `emeka.okafor@ui.edu.ng` | `Customer@123` | Emeka Okafor |

### Test Cards for Simulated Payment
* **Success**: `4242 4242 4242 4242` (Expiry: any future date, e.g. `12/28`, CVV: `123`)
* **Declined**: `4000 0000 0000 0002` (Returns HTTP 422 `card_declined`)
* **Insufficient Funds**: `4000 0000 0000 9995` (Returns HTTP 422 `insufficient_funds`)
* **Expired Card**: Any card with past expiry (e.g. `05/20`) &rarr; HTTP 422 `expired_card`
* **Invalid Luhn**: Any card failing checksum &rarr; HTTP 400 validation error (no payment recorded)

> [!IMPORTANT]
> **Demo Flag**: `DEV_BYPASS_AUTH` in `config/config.php` is strictly set to `false` for presentation and submission.

---

## 3. Installation & Setup Instructions

1. **Install XAMPP** (or Apache + MySQL + PHP 8+).
2. **Clone the repository** into your local web root:
   ```bash
   # Windows XAMPP path:
   cd C:\xampp\htdocs
   git clone https://github.com/your-username/ui-vsb.git ui-vsb
   ```
   *(Ensure the folder is named `ui-vsb` so the local URL is `http://localhost/ui-vsb/`).*
3. **Start Apache & MySQL** in the XAMPP Control Panel.
4. **Import Database**:
   * Open phpMyAdmin (`http://localhost/phpmyadmin/`), select **Import**, and choose `database/ui_vsb.sql`.
   * Or via terminal:
     ```bash
     mysql -u root < database/ui_vsb.sql
     ```
5. **Local Database Configuration** (optional):
   If your MySQL password is not empty, copy `config/config.local.example.php` to `config/config.local.php` and set your credentials.

---

## 4. Running the Automated Test Suite

To run all automated domain, concurrency, simulated payment, and workflow tests:
```bash
php tests/run_all.php
```

Individual test suites:
* `php tests/test_b2_b3.php` — Vehicles, Services, Slot calculations, Concurrency lock & 30-min hold expiry.
* `php tests/test_b4.php` — Simulated payment gateway, card test matrices, Luhn check, security audit.
* `php tests/test_b5.php` — Admin workflows, approval/rejection with auto-refund, 5-step stepper.

---

## 5. API Endpoints Summary

All API responses follow the standard JSON envelope:
`{ "success": bool, "message": string, "data": mixed|null, "errors": array|null }`

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `POST` | `api/auth/register.php` | Public | Register customer account & log in |
| `POST` | `api/auth/login.php` | Public | Authenticate user & return dashboard redirect |
| `POST` | `api/auth/logout.php` | Logged In | Invalidate session |
| `GET` | `api/auth/me.php` | Logged In | Return current authenticated user profile |
| `GET` | `api/services.php` | Logged In | List active workshop services |
| `GET` | `api/vehicles.php` | Customer | List customer's registered vehicles |
| `POST` | `api/vehicles.php` | Customer | Add new vehicle (`action=add`) or delete (`action=delete`) |
| `GET` | `api/slots.php?date=&service_id=` | Customer | **Fetch API**: Real-time slot availability & remaining capacity |
| `POST` | `api/book.php` | Customer | Place temporary 30-min booking hold (concurrency protected) |
| `POST` | `api/payment.php` | Customer | Charge simulated test card via `PaymentGatewayInterface` |
| `GET` | `api/bookings.php` | Customer | List customer bookings or get detail with audit history (`?ref=`) |
| `GET` | `api/dashboard.php` | Customer | Customer dashboard summary metrics |
| `GET` | `api/admin/dashboard.php` | Admin | Workshop KPIs, pending approvals count, revenue summary |
| `GET` | `api/admin/services.php` | Admin | List all services (active and inactive) |
| `POST` | `api/admin/services.php` | Admin | Create, update, or soft-deactivate/delete services |
| `GET` | `api/admin/bookings.php` | Admin | Filter bookings by query `q`, status, and date range |
| `POST` | `api/admin/bookings.php` | Admin | Approve or reject booking (rejection auto-refunds payment) |
| `POST` | `api/admin/status.php` | Admin | Advance service status strictly forward along the 5-step stepper |
| `GET` | `api/admin/customers.php` | Admin | View customer accounts with aggregated vehicle/booking counts |
| `GET` | `api/admin/payments.php` | Admin | Payment transactions audit report and total revenue |
| `GET` | `api/admin/records.php` | Admin | Historical vehicle maintenance record search |

---

## 6. Project Ownership & Directory Structure

```
ui-vsb/
├── api/                  # Backend REST API endpoints (PHP)
│   ├── admin/            # Administrative endpoints (Auth::requireRole('admin'))
│   └── auth/             # Authentication endpoints
├── config/               # Configuration and database constants
├── database/             # Idempotent MySQL schema and seed data (ui_vsb.sql)
├── docs/                 # System architecture, API contract & walkthroughs
├── includes/             # Shared bootstrap, header, and footer
├── models/               # System design specs & writeup drafts
├── src/                  # PHP Domain Models (OOP)
│   ├── ApiResponse.php   # Standard response envelope formatter
│   ├── Auth.php          # Session security & role-based access guards
│   ├── Booking.php       # Concurrency engine & slot capacity calculator
│   ├── Database.php      # Singleton PDO connection wrapper
│   ├── Payment.php       # Payment recording, audit logging & refund handler
│   ├── PaymentGatewayInterface.php # Swappable gateway contract
│   ├── Service.php       # Workshop service catalogue domain
│   ├── SimulatedPaymentGateway.php # Test card simulation engine
│   ├── User.php          # Customer & admin account manager
│   └── Vehicle.php       # Vehicle registration & ownership checks
└── tests/                # Automated PHP & integration test suites
```
