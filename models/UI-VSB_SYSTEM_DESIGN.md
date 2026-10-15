# ui-vsb — Vehicle Service Booking & Payment Platform
## System Design (Frontend + Backend) — build reference

Stack: HTML, CSS, vanilla JavaScript (Fetch API), PHP 8 (OOP, PDO), MySQL.
Runs on XAMPP/WAMP (`htdocs/ui-vsb/`). Payment is **simulated only**.

---

## 1. Core decisions (lock these in first)

| Topic | Decision |
|---|---|
| Roles | `customer` and `admin`, stored in `users.role`. One login page, redirect by role. |
| Auth | PHP sessions. Passwords with `password_hash()` / `password_verify()`. |
| DB access | One PDO connection (singleton `Database` class), **prepared statements only**. |
| API style | Plain PHP files in `/api/` returning JSON (`{success, message, data}`). Page JS calls them with `fetch()`. |
| Slots | Workshop hours 08:00–16:00, one-hour slots (8 per day). Each slot has a capacity of **2 bays**. Slot is "full" when 2 active bookings exist for that date+time. |
| Booking lifecycle | `pending_payment` → (pay) `pending_approval` → admin `approved` / `rejected`. Rejected paid bookings get a simulated refund. |
| Service progress | Separate from booking status: `not_started` → `received` → `in_progress` → `completed` → `ready_for_pickup`. Admin updates it, customer tracks it. |
| Booking ref | `VSB-YYYYMMDD-XXXX` (date + 4 random uppercase alphanumerics), unique. |
| Payment | Simulated gateway with test cards (section 6). Never store full card number or CVV, only last 4 digits. |
| Currency | Naira (₦), stored as `DECIMAL(10,2)`. |

---

## 2. Folder structure

```
ui-vsb/
├── index.php                  # landing page (services preview + login/register links)
├── register.php
├── login.php
├── logout.php
├── config/
│   └── config.php             # DB credentials, constants (SLOT_CAPACITY, etc.)
├── src/                       # PHP OOP classes (autoloaded)
│   ├── Database.php
│   ├── User.php
│   ├── Vehicle.php
│   ├── Service.php
│   ├── Booking.php
│   ├── Payment.php
│   ├── PaymentGatewayInterface.php
│   ├── SimulatedPaymentGateway.php
│   ├── Auth.php               # session + role guard helpers
│   └── ApiResponse.php        # json_response helper
├── api/
│   ├── slots.php              # GET  available slots for date+service   (Fetch #1)
│   ├── book.php               # POST create booking                      (Fetch #2)
│   ├── payment.php            # POST process simulated payment           (Fetch #3)
│   ├── booking_status.php     # GET  track a booking
│   ├── vehicles.php           # GET list / POST add / DELETE
│   └── admin/
│       ├── services.php      # POST add / PUT edit / DELETE
│       ├── bookings.php      # GET list+search / POST approve|reject
│       ├── status.php         # POST update service status
│       ├── customers.php      # GET
│       └── payments.php       # GET
├── customer/
│   ├── dashboard.php
│   ├── vehicles.php           # add/list vehicles
│   ├── services.php           # browse services + prices
│   ├── book.php               # booking form (uses slots API)
│   ├── payment.php            # pay for a booking
│   ├── confirmation.php       # booking ref + payment receipt
│   └── track.php              # my bookings + progress
├── admin/
│   ├── dashboard.php
│   ├── services.php
│   ├── bookings.php
│   ├── customers.php
│   ├── payments.php
│   └── records.php            # search service records
├── assets/
│   ├── css/style.css
│   └── js/
│       ├── app.js             # shared fetch helper, toast, validation
│       ├── book.js            # slots check + booking submit
│       ├── payment.js         # card form + pay
│       ├── track.js
│       └── admin.js
└── database/
    └── ui_vsb.sql                # schema + seed data (SUBMIT THIS)
```

---

## 3. Database design

### 3.1 Tables & relationships

- `users` 1—N `vehicles`
- `users` 1—N `bookings`
- `vehicles` 1—N `bookings`
- `services` 1—N `bookings`
- `bookings` 1—N `payments` (1 successful max; failed attempts are also logged)
- `bookings` 1—N `status_history` (audit trail for tracking)

### 3.2 SQL (put in `database/ui_vsb.sql`)

```sql
CREATE DATABASE IF NOT EXISTS ui_vsb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ui_vsb_db;

CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  phone         VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  vehicle_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  reg_number   VARCHAR(20) NOT NULL UNIQUE,
  make         VARCHAR(50) NOT NULL,
  model        VARCHAR(50) NOT NULL,
  year         YEAR NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE services (
  service_id    INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  description   TEXT,
  price         DECIMAL(10,2) NOT NULL,
  duration_mins INT NOT NULL DEFAULT 60,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE bookings (
  booking_id      INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref     VARCHAR(20) NOT NULL UNIQUE,
  user_id         INT NOT NULL,
  vehicle_id      INT NOT NULL,
  service_id      INT NOT NULL,
  booking_date    DATE NOT NULL,
  slot_time       TIME NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,          -- price snapshot at booking time
  booking_status  ENUM('pending_payment','pending_approval','approved','rejected','cancelled')
                  NOT NULL DEFAULT 'pending_payment',
  service_status  ENUM('not_started','received','in_progress','completed','ready_for_pickup')
                  NOT NULL DEFAULT 'not_started',
  notes           VARCHAR(255) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
  FOREIGN KEY (service_id) REFERENCES services(service_id),
  INDEX idx_slot (booking_date, slot_time)
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id     INT AUTO_INCREMENT PRIMARY KEY,
  booking_id     INT NOT NULL,
  transaction_ref VARCHAR(30) NOT NULL UNIQUE,      -- e.g. TXN-20261006-8F3K2A
  amount         DECIMAL(10,2) NOT NULL,
  method         VARCHAR(20) NOT NULL DEFAULT 'test_card',
  card_last4     CHAR(4) NULL,
  status         ENUM('success','failed','refunded') NOT NULL,
  failure_reason VARCHAR(100) NULL,
  paid_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
) ENGINE=InnoDB;

CREATE TABLE status_history (
  history_id  INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL,
  status      VARCHAR(30) NOT NULL,
  note        VARCHAR(255) NULL,
  changed_by  INT NULL,
  changed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Seed data
INSERT INTO services (name, description, price, duration_mins) VALUES
('Engine Servicing',   'Full engine check, filter and fluid service', 45000.00, 120),
('Oil Change',         'Engine oil and oil filter replacement',        15000.00,  45),
('Brake Inspection',   'Pads, discs, fluid and line inspection',       10000.00,  60),
('Tyre Replacement',   'Remove, fit and balance new tyres',            30000.00,  90),
('Vehicle Diagnostics','Computer diagnostics and fault report',        12000.00,  60);

-- admin password below = "Admin@123" (generate a fresh hash with password_hash() and paste it)
INSERT INTO users (full_name,email,phone,password_hash,role) VALUES
('Workshop Admin','admin@ui.edu.ng','08000000000','<PASTE_HASH_HERE>','admin');
```

### 3.3 Slot availability query (the heart of the Fetch feature)

```sql
SELECT slot_time, COUNT(*) AS used
FROM bookings
WHERE booking_date = :date
  AND booking_status IN ('pending_payment','pending_approval','approved')
GROUP BY slot_time;
```
PHP generates the 8 hourly slots, subtracts `used` from `SLOT_CAPACITY` (2), and returns each as `available: true/false` with `remaining`.

Unpaid `pending_payment` bookings older than 30 minutes should stop blocking a slot. Add `AND (booking_status <> 'pending_payment' OR created_at > NOW() - INTERVAL 30 MINUTE)` to the WHERE.

---

## 4. Backend design (PHP OOP)

Autoload with `spl_autoload_register` from `src/`. All classes use PDO through `Database::getInstance()->getConnection()`.

### 4.1 Classes

| Class | Responsibility | Key methods |
|---|---|---|
| `Database` | Singleton PDO wrapper | `getInstance()`, `getConnection()` |
| `User` | Registration, login, profile, customer lists | `register($data)`, `login($email,$pw)`, `findById()`, `getAllCustomers()` |
| `Vehicle` | Vehicle CRUD for a customer | `add($userId,$data)`, `getByUser($userId)`, `belongsTo($vehicleId,$userId)`, `delete()` |
| `Service` | Service catalogue + pricing | `getAllActive()`, `getById()`, `create()`, `update()`, `delete()` (soft delete via `is_active=0` if it has bookings) |
| `Booking` | Slots, booking creation, approval, status | `getAvailableSlots($date)`, `isSlotFree($date,$time)`, `create()`, `generateRef()`, `getByUser()`, `getAll($search)`, `approve()`, `reject()`, `updateServiceStatus()`, `addHistory()` |
| `Payment` | Payment records | `record()`, `getByBooking()`, `getAll()`, `refund()` |
| `PaymentGatewayInterface` | Contract for any gateway | `charge(array $card, float $amount): array` |
| `SimulatedPaymentGateway` | Implements the interface with test-card rules | `charge()` (no real money) |
| `Auth` | Session handling and guards | `login()`, `logout()`, `requireLogin()`, `requireRole('admin')`, `currentUser()` |
| `ApiResponse` | Standard JSON output | `success($data,$msg)`, `error($msg,$code)` |

OOP points to be able to explain: encapsulation (private `$pdo`, getters), singleton (`Database`), interface + polymorphism (`PaymentGatewayInterface` ← `SimulatedPaymentGateway`; a real gateway could be swapped in), separation of concerns (classes do data work, `api/*.php` only handle HTTP).

### 4.2 Booking creation logic (`Booking::create`)

1. Validate: logged in, vehicle belongs to user, service active, date is today or later and not in the past, slot is one of the 8 valid slots.
2. `BEGIN TRANSACTION`.
3. Re-check slot capacity with `SELECT ... FOR UPDATE` (prevents two users taking the last bay at once).
4. If full → rollback, return error `409 Slot no longer available` (**a demo failure case**).
5. Insert booking with `amount` = current service price, status `pending_payment`.
6. Insert `status_history` row. `COMMIT`.
7. Return `booking_ref`, `booking_id`, `amount`.

### 4.3 Status transition rules (enforce in `Booking`)

- Pay only allowed when `booking_status = pending_payment`.
- Approve/reject only when `pending_approval`.
- Reject → `payments.status = 'refunded'` (simulated) + history note.
- `service_status` can only be updated when `booking_status = approved`, and only forward in the order listed above.

---

## 5. API endpoints

All return `{ "success": bool, "message": string, "data": any }`. All except register/login need a session. Admin endpoints check `role = admin` (else 403).

| Method | Endpoint | Purpose | Input | Success output |
|---|---|---|---|---|
| GET | `/api/slots.php` | Available slots (**Fetch**) | `date`, `service_id` | `data: [{time:"08:00", available:true, remaining:2}, ...]` |
| POST | `/api/book.php` | Create booking | `vehicle_id, service_id, date, slot_time` | `data: {booking_id, booking_ref, amount}` |
| POST | `/api/payment.php` | Simulated payment | `booking_id, card_number, expiry, cvv, card_name` | `data: {transaction_ref, amount, status}` |
| GET | `/api/booking_status.php` | Track booking | `booking_ref` or none (list mine) | `data: {booking_status, service_status, history[]}` |
| GET/POST/DELETE | `/api/vehicles.php` | Vehicles | see form | list / created / deleted |
| POST/PUT/DELETE | `/api/admin/services.php` | Service CRUD | `name, description, price, duration_mins` | updated list |
| GET | `/api/admin/bookings.php` | List + search | `q` (ref, plate, customer, status, date) | bookings |
| POST | `/api/admin/bookings.php` | Approve/reject | `booking_id, action` | updated booking |
| POST | `/api/admin/status.php` | Update service status | `booking_id, service_status, note` | updated booking |
| GET | `/api/admin/customers.php` | Customers + their vehicles | none | list |
| GET | `/api/admin/payments.php` | Payment records | optional filters | list |

HTTP codes: 200 OK, 201 created, 400 validation, 401 not logged in, 403 wrong role, 409 slot conflict, 422 payment declined, 500 server.

Register and login can be normal form posts or small JSON endpoints (`/api/register.php`, `/api/login.php`). Use JSON + fetch for them too so every flow is consistent.

---

## 6. Simulated payment gateway

`SimulatedPaymentGateway::charge()` rules (document these in the write-up and demo them):

| Test card | Result | `failure_reason` |
|---|---|---|
| `4242 4242 4242 4242` | **Success** | — |
| `4000 0000 0000 0002` | Declined | `card_declined` |
| `4000 0000 0000 9995` | Declined | `insufficient_funds` |
| Any card with expiry in the past | Declined | `expired_card` |
| Fails Luhn check or wrong length | Rejected before charging | `invalid_card_number` |
| CVV not 3 digits | Rejected | `invalid_cvv` |

Flow in `api/payment.php`:
1. Auth check, load booking, confirm it belongs to user and is `pending_payment`.
2. Validate card format server-side (client also validates).
3. `sleep(1)` or `usleep(800000)` to mimic gateway latency (spinner on the front end).
4. Call `$gateway->charge()`.
5. Success → insert `payments` row (`success`, `card_last4`, `TXN-...`), set booking `pending_approval`, add history. Return receipt data.
6. Failure → insert `payments` row (`failed`, reason), booking stays `pending_payment`, return HTTP 422 with message so the user can retry.

Show a "TEST MODE — no real money is charged" banner on the payment page and list the test cards there.

---

## 7. Frontend design

### 7.1 Shared UI

- Top navbar: logo/name, links by role, logout. Mobile: collapses to hamburger.
- Colour scheme: pick a deep blue/navy (trust) with an orange accent (workshop). Keep to 1 stylesheet with CSS variables.
- Components: card, form field with inline error text, button (primary/secondary/danger), status badge, data table, toast/alert, modal, loading spinner.
- Status badge colours: pending = amber, approved/success = green, rejected/failed = red, in progress = blue.
- `app.js` exports a small helper:

```js
async function api(url, options = {}) {
  const res = await fetch(url, { credentials: 'same-origin', ...options });
  const json = await res.json().catch(() => ({ success:false, message:'Invalid server response' }));
  return { ok: res.ok, status: res.status, ...json };
}
```

### 7.2 Customer pages

| Page | What it shows / does |
|---|---|
| `register.php` | Name, email, phone, password + confirm. Client + server validation. Success → login. |
| `login.php` | Email + password. Wrong credentials → error shown (**failure demo**). |
| `customer/dashboard.php` | Welcome, quick stats (vehicles, active bookings), shortcut buttons, latest booking progress. |
| `customer/vehicles.php` | Form (reg no, make, model, year) + table of my vehicles with delete. Duplicate reg number → error. |
| `customer/services.php` | Cards for each service with price, duration, "Book now". |
| `customer/book.php` | Step form: choose vehicle → service (price shown live) → date. When date or service changes, JS calls `/api/slots.php` and renders slot buttons (disabled + "Full" for taken ones). Pick slot → Submit → `/api/book.php` → redirect to payment with `booking_id`. |
| `customer/payment.php` | Order summary (ref, service, vehicle, date/time, amount) + card form. Submit via fetch, spinner, then success redirect to confirmation or inline red error for declines with retry. |
| `customer/confirmation.php` | Booking ref (big), transaction ref, amount, "Print receipt". |
| `customer/track.php` | List of my bookings with badges. Click for a progress stepper (Not started → Received → In progress → Completed → Ready for pickup) and history timeline. Optional: poll `booking_status.php` every 15s. |

### 7.3 `book.js` behaviour (the required async Fetch feature)

```
on change(date | service):
   if (!date || !service) return
   show "Checking availability…"
   res = await api(`/api/slots.php?date=${date}&service_id=${service}`)
   render buttons: available → selectable, full → disabled "Full"
on slot click: store slot_time in hidden input, highlight
on submit: validate all fields → POST /api/book.php (FormData) → 
   success: window.location = `payment.php?booking_id=${data.booking_id}`
   409: show "That slot was just taken", re-run slot check
```
Block past dates with `min` on the date input and reject past dates server-side. Optionally disable Sundays.

### 7.4 Admin pages

| Page | What it does |
|---|---|
| `admin/dashboard.php` | Counts: bookings today, pending approvals, revenue (successful payments), services. |
| `admin/services.php` | Table + add/edit modal + delete (confirm). Price edit here. |
| `admin/bookings.php` | Table with search box + status filter. Row actions: Approve, Reject, View. Approved rows have a service-status dropdown + "Update". All via fetch. |
| `admin/customers.php` | Customers with phone, email, number of vehicles, number of bookings. Expand for their vehicles. |
| `admin/payments.php` | Transaction ref, booking ref, customer, amount, status (success/failed/refunded), date. Total of successful payments. |
| `admin/records.php` | Search service records by booking ref, reg number, customer name, service, date range. |

---

## 8. Security & validation checklist

- Prepared statements everywhere (no string-built SQL).
- `htmlspecialchars()` on every echoed value; JS uses `textContent` not `innerHTML` for user data.
- `session_regenerate_id(true)` on login.
- Role guard at top of every protected page **and** every API file.
- Check ownership: a customer can only book/pay/track their own vehicles and bookings.
- Server-side validation mirrors client: email format, phone digits, password min 8 chars, year range (1980–current year+1), price > 0, date not in past.
- Optional: CSRF token in a hidden field/header for POST requests.

---

## 9. Demo script (successful AND failed operations)

| # | Action | Expected |
|---|---|---|
| 1 | Register with new email | Success |
| 2 | Register with same email | **Fail**: email already used |
| 3 | Login with wrong password | **Fail**: invalid credentials |
| 4 | Add vehicle | Success |
| 5 | Add vehicle with same plate | **Fail**: duplicate |
| 6 | Pick date, view slots (Fetch, no reload) | Slots shown |
| 7 | Book a slot | Booking ref created |
| 8 | Book same slot until capacity hit (use 2nd account) | **Fail**: slot full |
| 9 | Pay with `4000 0000 0000 0002` | **Fail**: declined, booking still unpaid |
| 10 | Pay with `4242 4242 4242 4242` | Success + receipt |
| 11 | Admin approves booking | Customer sees Approved |
| 12 | Admin rejects another paid booking | Refunded record shown |
| 13 | Admin updates status to In progress → Completed | Customer tracking updates |
| 14 | Customer opens an admin URL | **Fail**: 403 / redirect |
| 15 | Admin adds / edits / deletes service, searches records | Success |

Take a screenshot at each step; these feed the Testing and Screenshots sections of the write-up.

---

## 10. Suggested build order and team split

**Order:** (1) DB + seed → (2) `Database`, `User`, `Auth`, register/login → (3) `Vehicle` + vehicles page → (4) `Service` + admin services + customer services page → (5) `Booking` + slots API + book page → (6) payment gateway + payment page → (7) admin bookings/status + tracking → (8) customers/payments/search → (9) styling pass + validation → (10) testing + screenshots.

**Split for 5 members** (everyone must explain their part):

| Member | Owns |
|---|---|
| 1 | Database, SQL file, `Database`, `Auth`, `User`, register/login/logout |
| 2 | `Vehicle`, `Service`, vehicles page, services pages (customer + admin) |
| 3 | `Booking` class, slots + book APIs, booking page, `book.js` (Fetch) |
| 4 | `Payment`, gateway interface + simulated gateway, payment API, payment/confirmation pages |
| 5 | Admin bookings/status/customers/payments/records, tracking page, CSS and UI consistency |

Adjust if your group is a different size. Whoever finishes first takes testing and the write-up diagrams.
