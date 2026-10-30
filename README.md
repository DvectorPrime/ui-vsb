# UI-VSB: Vehicle Service Booking & Payment Platform
### University of Ibadan · Department of Computer Science
**Course:** IFT302 — Web Design and Development  
**Client:** Maintenance Directorate, University of Ibadan  
**Repository:** [https://github.com/DvectorPrime/ui-vsb](https://github.com/DvectorPrime/ui-vsb)  
**Project Report:** [`docs/IFT302 ui-vsb Project Report.pdf`](docs/IFT302%20ui-vsb%20Project%20Report.pdf)

---

## 1. Project Overview

The **University of Ibadan Maintenance Directorate** operates a central automotive workshop responsible for servicing and repairing vehicles across the university community. Historically, scheduling appointments, verifying bay availability, and tracking repair progress required in-person visits or phone calls, leading to scheduling bottlenecks, double-booked bays, and lost paperwork.

The **Vehicle Service Booking and Payment Platform (UI-VSB)** is an end-to-end web application that digitizes and streamlines this workflow:
* **Staff & Student Customers:** Register vehicles, check workshop bay availability in real time using the asynchronous **JavaScript Fetch API** without reloading the page, place bookings with temporary 30-minute holds, pay simulated service fees via an interactive gateway, and track repair progression in real time through a live 5-step visual stepper.
* **Workshop Administrators:** Manage the workshop service catalogue and pricing, review incoming bookings, approve or reject appointments (with automated simulated refunds), update workshop job progression along a forward-only stepper, inspect customer vehicle fleets, and audit financial transaction ledgers.

---

## 2. Technical Stack & Academic Compliance

The platform strictly complies with all **IFT302 course portfolio specifications**, adhering to a **zero third-party framework** policy:

* **Frontend:** Semantic HTML5, Custom Vanilla CSS3 (responsive grid/card design, mobile touch targets $\ge 40\text{px}$, CSS custom property variables, zero Bootstrap/Tailwind), Vanilla ECMAScript (ES6+, asynchronous Fetch API, DOM manipulation, real-time client validation).
* **Backend:** PHP 8.x Object-Oriented Architecture (strict typing, custom autoloader via `spl_autoload_register`, singleton PDO database connection, encapsulation, inheritance, interfaces, polymorphism, zero Laravel/Symfony/Composer packages).
* **Database:** MySQL / MariaDB (relational schema in 3NF, foreign key integrity constraints with cascading rules, composite indexes for slot queries, ACID transactions with `SELECT ... FOR UPDATE` pessimistic concurrency locking).
* **Payment Simulation:** Fully simulated payment gateway implementing `PaymentGatewayInterface` (realistic Luhn checksum algorithm, test card response matrices, automated simulated refunding on rejection, zero credit card PAN or CVV storage).

---

## 3. Team Members & Contributions

| S/N | Name | Matric Number | Project Role |
|:---:|---|:---:|---|
| **1** | Nwachukwu Victor Efemena | 250380 | Backend Development & Concurrency Engine |
| **2** | Muhammed Awwal Mumeenat | 256647 | Frontend Development & Mobile Responsive UI |
| **3** | Akano Hazeem Olamilekan | 250351 | Authentication & Role-Based Access Control |
| **4** | Olanipekun Happiness | 250396 | Backend Development & Vehicle Management |
| **5** | Oyeniran Jibola Gbolahan | 250405 | Project Report & System Analysis |
| **6** | Ayedun Caleb Ephraim | 250501 | Project Report & Use-Case Modeling |
| **7** | Sunday Emmanuel | 250559 | Quality Assurance & Automated Testing |
| **8** | Adejoro Victory Adesewa | 256566 | Project Report & Technical Documentation |
| **9** | Adesesan Toluwani Oluwadarasimi | 250342 | Simulated Payment Gateway Integration |

---

## 4. Quick Start & Setup Guide for Evaluators

Follow these 5 steps to run the complete platform on any standard computer equipped with XAMPP (or Apache + MySQL + PHP 8.x).

### Prerequisites
* **Web Server:** Apache 2.4+ (included with XAMPP or WAMP)
* **PHP:** PHP 8.1 or PHP 8.2+ with `pdo_mysql`, `curl`, `json`, and `session` extensions enabled
* **Database:** MySQL 5.7+ or MariaDB 10.4+
* **Web Browser:** Any modern browser (Google Chrome, Microsoft Edge, Mozilla Firefox, Safari)

---

### Step 1: Place the Project in Your Web Root

Copy or clone the `ui-vsb` folder into your local web server's document root directory (`htdocs` in XAMPP or `www` in WAMP):

```bash
# Windows XAMPP default path:
cd C:\xampp\htdocs
git clone https://github.com/DvectorPrime/ui-vsb.git ui-vsb
```

> [!NOTE]
> Ensure the directory is named `ui-vsb` so that the local URL resolves to `http://localhost/ui-vsb/`.

---

### Step 2: Start Apache and MySQL

1. Open the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Click the **Start** button for **Apache**.
3. Click the **Start** button for **MySQL**.

---

### Step 3: Import the Database

The database script is located at [`database/ui_vsb.sql`](database/ui_vsb.sql). It is completely idempotent (creates `ui_vsb_db`, drops existing tables in reverse dependency order, creates the 6 relational tables, sets up foreign keys and indexes, seeds the 5 workshop services, and creates the default administrator account).

**Option A — Via phpMyAdmin (Graphical):**
1. Open your browser and navigate to `http://localhost/phpmyadmin/`.
2. Click on the **Import** tab at the top.
3. Click **Choose File**, select `database/ui_vsb.sql` from the project directory, and click **Import** at the bottom.

**Option B — Via Command Line (Terminal):**
```bash
# Default XAMPP with no password:
mysql -u root < database/ui_vsb.sql

# If your MySQL root account has a password:
mysql -u root -p < database/ui_vsb.sql
```

---

### Step 4: Configure Database Credentials (If Needed)

The application defaults to standard XAMPP credentials (`localhost`, user: `root`, password: empty).

If your local MySQL installation uses a password:
1. Copy [`config/config.local.example.php`](config/config.local.example.php) to `config/config.local.php`.
2. Open `config/config.local.php` and set your password:
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ui_vsb_db');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_mysql_password_here');
   ```
*(Note: `config.local.php` is ignored by Git, ensuring local configuration never overrides shared repository code).*

---

### Step 5: Launch the Application

Open your browser and navigate to:

👉 **`http://localhost/ui-vsb/`**

---

## 5. Credentials & Evaluation Walkthrough

### Initial Administrator Account
Importing `database/ui_vsb.sql` creates the master workshop administrator account:

* **URL:** `http://localhost/ui-vsb/login.php`
* **Email:** `admin@ui.edu.ng`
* **Password:** `Admin@123`
* **Role:** `admin`

### Customer Testing Account
The database is cleanly initialized with the workshop catalogue so evaluators can test from a clean state:
1. Navigate to **`http://localhost/ui-vsb/register.php`**.
2. Register a new customer account using any university email (e.g. `yourname@ui.edu.ng`).
3. You will be automatically authenticated and redirected to the **Customer Dashboard**.

---

## 6. Testing the Payment Gateway (Simulated)

Payment processing is fully simulated through `SimulatedPaymentGateway` adhering to `PaymentGatewayInterface`. **No real card is ever charged, and no sensitive credit card PAN or CVV is ever stored in the database.** Only the last 4 digits (`card_last4`) are persisted for customer receipts.

During payment checkout on `customer/payment.php`, use the following test cards (or click the simulation helper buttons on screen):

| Card Number | Expiry | CVV | Scenario / Expected Behavior | HTTP Code |
|---|:---:|:---:|---|:---:|
| `4242 4242 4242 4242` | Future (e.g. `12/28`) | `123` | **Successful Payment:** Payment record saved as `success`, booking moves to `pending_approval`, receipt displayed with printable confirmation. | `200 OK` |
| `4000 0000 0000 0002` | Future (e.g. `12/28`) | `123` | **Card Declined:** Simulates card decline, records payment as `failed` with reason `card_declined`. Booking remains unpaid for immediate retry. | `422 Unprocessable` |
| `4000 0000 0000 9995` | Future (e.g. `12/28`) | `123` | **Insufficient Funds:** Simulates payment failure due to insufficient funds, records failed attempt. | `422 Unprocessable` |
| *Any card number* | Past (e.g. `05/20`) | `123` | **Expired Card:** Gateway rejects transaction with `expired_card` failure reason. | `422 Unprocessable` |
| *Any tampered number* | Future | `123` | **Invalid Luhn Checksum:** Client and server-side Luhn validation rejects the number before gateway dispatch. | `400 Bad Request` |

---

## 7. Key Architecture & Features Demonstrated

### 1. Asynchronous Fetch API (`customer/book.php` $\to$ `api/slots.php`)
* When booking an appointment, selecting a service and a date triggers a background `fetch()` request to `api/slots.php?date=YYYY-MM-DD&service_id=X`.
* The server counts active bookings and active 30-minute holds for each of the 8 daily time slots (`08:00` through `15:00`).
* Slots with remaining capacity ($\text{capacity} = 2$ bays) display green remaining counts (e.g. `2 left` or `1 left`). Fully booked bays display `Full` and are disabled in the DOM.
* **Inspect in Browser:** Open DevTools (`F12`) $\to$ **Network** tab $\to$ filter `Fetch/XHR` to observe real-time JSON exchange without page reloads.

### 2. Concurrency Protection & 30-Minute Hold Expiry
* When a customer submits a booking, the backend executes an ACID transaction using `SELECT ... FOR UPDATE` on the slot capacity table to prevent race conditions when two users book the last bay simultaneously.
* Unpaid bookings are held for **30 minutes**. The payment page displays a live JavaScript countdown timer. The background engine (`Booking::expireStale()`) automatically cancels abandoned holds and frees bay capacity.

### 3. Forward-Only 5-Step Service Stepper
* Once approved by an administrator, the vehicle progresses through five distinct workshop stages:
  $$\text{Not Started} \longrightarrow \text{Vehicle Received} \longrightarrow \text{In Progress} \longrightarrow \text{Service Completed} \longrightarrow \text{Ready for Pickup}$$
* The administrator advances status via `api/admin/status.php`, which strictly enforces forward-only progression.
* Customers monitor their vehicle in real time on `customer/track.php` with 15-second background polling and an interactive audit trail modal.

### 4. Admin Workflow & Automated Refunds
* On `admin/bookings.php`, administrators review pending appointments.
* Clicking **Approve** moves the booking to `approved` and queues the job.
* Clicking **Reject** cancels the booking and automatically marks the associated payment record as `refunded`.

---

## 8. Running the Automated Test Suite

UI-VSB includes an automated test runner with **6 test suites** covering **119 automated test assertions**.

To run the master test suite from your terminal:

```bash
# From the project root:
php tests/run_all.php
```

*(Or specify the full PHP binary path on Windows: `& "C:\xampp\php\php.exe" "tests\run_all.php"`).*

### Test Suites Breakdown:
1. **`tests/test_b2_b3.php`** — Domain model tests for Vehicles, Services, Slot calculations, Concurrency locks, and 30-minute hold expiry. *(33 tests)*
2. **`tests/test_b4.php`** — Simulated payment gateway, card test matrices, Luhn checksum validation, and database security verification (ensuring zero raw card PAN or CVV columns exist). *(24 tests)*
3. **`tests/test_b5.php`** — Admin workflows, approval/rejection with auto-refund, 5-step stepper forward validation, customer batch querying (anti-N+1), and KPI dashboards. *(27 tests)*
4. **`tests/test_http_payment.php`** — Live HTTP integration test over Apache verifying login, slot allocation, 422 card decline, 200 card success, and 409 double-payment prevention. *(6 tests)*
5. **`tests/test_pages.php`** — Live HTTP page render and session guard verification across all 17 public, customer, and admin frontend pages. *(17 tests)*
6. **`tests/test_mobile.php`** — Mobile responsiveness audit, viewport meta tags, responsive CSS breakpoints (820px, 640px, 480px), touch scrolling momentum, and minimum touch target compliance. *(12 tests)*

---

## 9. System Endpoints & Page Directory

### Application Web Pages

| Route | Access | Description |
|---|---|---|
| `index.php` | Public | Homepage featuring hero banner, workflow overview, and dynamic service catalogue preview |
| `login.php` | Public | User authentication screen with role-aware redirection |
| `register.php` | Public | Customer registration with client and server duplicate email validation |
| `logout.php` | Logged In | Destroys session and cookie; redirects to login |
| `customer/dashboard.php` | Customer | Customer dashboard displaying vehicle counts, active bookings, and next appointment |
| `customer/vehicles.php` | Customer | Vehicle fleet manager with modal registration and deletion safeguards |
| `customer/services.php` | Customer | Workshop service catalogue with detailed durations and pricing |
| `customer/book.php` | Customer | Asynchronous Fetch API slot booking interface with 8-slot real-time capacity grid |
| `customer/payment.php` | Customer | Simulated card checkout with 30-min countdown timer and test card simulator |
| `customer/confirmation.php`| Customer | Payment receipt, booking reference display, and print receipt trigger |
| `customer/track.php` | Customer | Real-time 5-step vehicle progress stepper with timestamped audit timeline |
| `admin/dashboard.php` | Admin | Workshop administrative dashboard with revenue stats and KPI metrics |
| `admin/services.php` | Admin | Workshop service CRUD interface for adding, editing, and deactivating services |
| `admin/bookings.php` | Admin | Master booking management table with search filters, approval, and status stepper tools |
| `admin/customers.php` | Admin | Customer fleet overview with batch-queried vehicle lists (anti-N+1) |
| `admin/payments.php` | Admin | Financial transaction audit ledger with revenue totals and filter options |
| `admin/records.php` | Admin | Historical vehicle maintenance search with 300ms debounced Fetch querying |

### RESTful API Endpoints (`/api/`)

All API responses strictly adhere to the unified JSON envelope:  
`{ "success": boolean, "message": string, "data": mixed|null, "errors": array|null }`

| Method | Endpoint | Role | Description |
|:---:|---|:---:|---|
| `POST` | `api/auth/register.php` | Public | Register new customer account and create session |
| `POST` | `api/auth/login.php` | Public | Authenticate user credentials and return dashboard URL |
| `POST` | `api/auth/logout.php` | Logged In | Invalidate session |
| `GET` | `api/auth/me.php` | Logged In | Return current authenticated user profile |
| `GET` | `api/services.php` | Logged In | Retrieve active workshop services |
| `GET` | `api/vehicles.php` | Customer | List vehicles owned by the authenticated customer |
| `POST` | `api/vehicles.php` | Customer | Add new vehicle (`action=add`) or delete vehicle (`action=delete`) |
| `GET` | `api/slots.php` | Customer | **Fetch API**: Query real-time bay capacity for a specific date |
| `POST` | `api/book.php` | Customer | Reserve appointment with temporary 30-minute hold |
| `POST` | `api/payment.php` | Customer | Process simulated payment charge |
| `GET` | `api/bookings.php` | Customer | Retrieve customer bookings or booking detail with history (`?ref=`) |
| `GET` | `api/dashboard.php` | Customer | Retrieve customer dashboard metrics |
| `GET` | `api/admin/dashboard.php` | Admin | Workshop administrative KPIs and revenue statistics |
| `GET` | `api/admin/services.php` | Admin | Retrieve all services including deactivated packages |
| `POST` | `api/admin/services.php` | Admin | Create, update, or soft-deactivate workshop services |
| `GET` | `api/admin/bookings.php` | Admin | Filter bookings by search term, status, or date range |
| `POST` | `api/admin/bookings.php` | Admin | Approve booking or reject with automated refund |
| `POST` | `api/admin/status.php` | Admin | Progress vehicle along the forward-only 5-step stepper |
| `GET` | `api/admin/customers.php` | Admin | Retrieve customer directory with nested vehicle records |
| `GET` | `api/admin/payments.php` | Admin | Retrieve financial payment logs and revenue totals |
| `GET` | `api/admin/records.php` | Admin | Query historical vehicle maintenance logs |

---

## 10. Project Directory Structure

```
ui-vsb/
├── admin/                           # Administrator portal pages
│   ├── bookings.php                 # Master booking management & status progression
│   ├── customers.php                # Customer fleet overview
│   ├── dashboard.php                # Workshop KPI metrics & revenue summary
│   ├── payments.php                 # Financial transaction ledger
│   ├── records.php                  # Historical maintenance log search
│   └── services.php                 # Workshop service catalogue CRUD
├── api/                             # RESTful JSON API endpoints
│   ├── admin/                       # Administrative endpoints (Auth::requireRole('admin'))
│   │   ├── bookings.php             # Booking approvals & cancellations
│   │   ├── customers.php            # Customer fleet batch query
│   │   ├── dashboard.php            # Workshop statistics provider
│   │   ├── payments.php             # Payment audit reports
│   │   ├── records.php              # Vehicle history query
│   │   ├── services.php             # Service management
│   │   └── status.php               # Forward-only service stepper updater
│   ├── auth/                        # Authentication endpoints
│   │   ├── login.php                # Credential verification & session initialization
│   │   ├── logout.php               # Session destruction
│   │   ├── me.php                   # Authenticated user profile
│   │   └── register.php             # Customer registration
│   ├── book.php                     # Appointment creation & hold reservation
│   ├── bookings.php                 # Booking detail & audit trail retriever
│   ├── dashboard.php                # Customer KPI metrics provider
│   ├── payment.php                  # Simulated card charge processor
│   ├── services.php                 # Public workshop service catalogue
│   ├── slots.php                    # Asynchronous Fetch API slot availability calculator
│   └── vehicles.php                 # Vehicle management
├── assets/                          # Static frontend assets
│   ├── css/
│   │   └── style.css                # Custom responsive vanilla CSS design system
│   └── js/
│       ├── app.js                   # Unified Fetch client, validators, modals, toasts
│       └── config.js                # Frontend client runtime configuration
├── config/                          # System configuration
│   ├── config.local.example.php     # Template for local environment overrides
│   └── config.php                   # Database settings, business constants & routing
├── customer/                        # Customer portal pages
│   ├── book.php                     # Asynchronous slot booking page (Fetch API)
│   ├── confirmation.php             # Printable receipt & booking reference
│   ├── dashboard.php                # Customer appointment summary & metrics
│   ├── payment.php                  # Simulated payment checkout & countdown timer
│   ├── services.php                 # Customer service catalogue
│   ├── track.php                    # 5-step visual service progress tracker
│   └── vehicles.php                 # Registered vehicle fleet manager
├── database/
│   └── ui_vsb.sql                   # Idempotent MySQL schema and seed data
├── docs/                            # Project documentation
│   ├── Figures.docx                 # System diagrams and figure source documents
│   └── IFT302 ui-vsb Project Report.pdf # Formal academic group project report
├── includes/                        # Reusable server-side components
│   ├── bootstrap.php                # Environment initialization, autoloader, safe sessions
│   ├── footer.php                   # Common HTML footer & script bindings
│   └── header.php                   # Common navigation bar & role-aware headers
├── src/                             # PHP Object-Oriented Domain Models (PSR conventions)
│   ├── ApiResponse.php              # Standardized JSON response envelope formatter
│   ├── Auth.php                     # Session security, authentication guards & role checks
│   ├── Booking.php                  # Slot capacity engine, concurrency transactions, holds
│   ├── Database.php                 # Singleton PDO connection wrapper
│   ├── Payment.php                  # Payment transaction recorder & refund handler
│   ├── PaymentGatewayInterface.php  # Abstract payment gateway contract
│   ├── Service.php                  # Service catalogue manager
│   ├── SimulatedPaymentGateway.php  # Test card simulation engine & Luhn validator
│   ├── User.php                     # User account domain model & bcrypt password verification
│   └── Vehicle.php                  # Vehicle registration & ownership enforcement
├── tests/                           # Automated test suites
│   ├── run_all.php                  # Master test runner (executes all 6 suites)
│   ├── smoke.sh                     # Bash CLI curl smoke test suite
│   ├── test_b2_b3.php               # Vehicles, services, slots & hold expiry tests
│   ├── test_b4.php                  # Simulated payment gateway & card matrix tests
│   ├── test_b5.php                  # Admin workflows, stepper & dashboard tests
│   ├── test_http_payment.php        # Live Apache HTTP payment integration test
│   ├── test_mobile.php              # Mobile responsiveness & breakpoint verification
│   └── test_pages.php               # Live Apache HTTP page render test (all 17 pages)
├── index.php                        # Public landing page with workshop services preview
├── login.php                        # Authentication screen
├── logout.php                       # Session termination screen
├── register.php                     # Customer registration screen
└── README.md                        # Project documentation and setup guide
```

---

## 11. Troubleshooting & FAQ

* **Q: The browser displays `404 Not Found` when opening `http://localhost/ui-vsb/`**  
  *Confirm that the project folder inside `htdocs` is named exactly `ui-vsb`. If it is named differently (e.g. `ui-vehicle-service`), rename the folder to `ui-vsb` or navigate to `http://localhost/<folder-name>/`.*

* **Q: Database connection fails (`SQLSTATE[HY000] [1049] Unknown database 'ui_vsb_db'` or `[1045] Access denied`)**  
  *Make sure you imported `database/ui_vsb.sql`. If your local MySQL `root` user has a password, copy `config/config.local.example.php` to `config/config.local.php` and specify your password there.*

* **Q: Why does payment not contact an external bank?**  
  *Per the IFT302 project specification, payment is intentionally simulated via `SimulatedPaymentGateway` implementing `PaymentGatewayInterface`. It tests real gateway workflows (success, card decline, insufficient funds, expired cards, Luhn checks) without requiring real bank credentials or external network connectivity.*

* **Q: How does the system prevent two users from booking the same slot at the same time?**  
  *When a customer books, `Booking::create()` executes an ACID transaction with pessimistic row-locking (`SELECT ... FOR UPDATE`) to verify that the slot's count of confirmed bookings and active 30-minute holds does not exceed `SLOT_CAPACITY` (2 bays).*
