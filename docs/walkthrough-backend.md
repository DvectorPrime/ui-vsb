# Backend walkthrough

## B1: Database, core classes and authentication

### Files and what they do
| File | What it does |
|---|---|
| `database/ui_vsb.sql` | Creates the database and 6 tables, then inserts 5 services, 2 accounts and 3 demo vehicles. Safe to re-import (drops tables first). |
| `config/config.php` | All settings (DB login, slot rules, `BASE_URL`). `config.local.php` overrides them per machine. |
| `includes/bootstrap.php` | Loaded first by every page and API file: config, timezone, class autoloader, safe session. |
| `src/Database.php` | One shared PDO connection (singleton). |
| `src/ApiResponse.php` | Sends every JSON answer in the same envelope and stops. |
| `src/Auth.php` | Login state in the session, plus guards for pages and APIs. |
| `src/User.php` | Registers and logs in users. |
| `api/auth/*.php` | `register`, `login`, `logout`, `me`. They only handle HTTP and call the classes. |
| `tests/smoke.sh` | Calls the auth API with curl and prints PASS/FAIL per check. |

### Key ideas (say these in the demo)
- **Singleton (`Database`)**: the constructor is private, so the only way in is `getInstance()`. The whole request shares one connection.
- **Prepared statements**: SQL and user data are sent separately, so typed input can never change the query (stops SQL injection). `ATTR_EMULATE_PREPARES` is off so the database itself does this.
- **Password safety**: `password_hash()` stores a salted bcrypt hash; `password_verify()` checks it. The real password is never saved.
- **Same login error for both failures**: "Invalid email or password" whether the email is unknown or the password is wrong, so attackers cannot find which emails exist.
- **`session_regenerate_id(true)` on login**: gives a fresh session id so an id planted earlier is useless (session fixation).
- **Role is never taken from the request**: `User::register()` always inserts `'customer'`, so nobody can sign up as admin.
- **One guard, two behaviours**: `Auth::requireRole()` returns a 401/403 JSON for `/api/` paths and a redirect for pages.
- **Separation of concerns**: classes do data work; `api/*.php` only read the request, call a class and send a response.

### Likely examiner questions
1. *Why PDO prepared statements?* They keep data separate from SQL, which stops injection.
2. *How do sessions work?* PHP stores data on the server and gives the browser a `PHPSESSID` cookie; each request carries it back.
3. *Why a singleton for the database?* One connection is cheaper than opening one per class.
4. *Why hash passwords?* If the table leaks, passwords are not readable.
5. *What does `httponly` do on the cookie?* JavaScript cannot read it, which limits XSS damage.

### Dev switch
`DEV_BYPASS_AUTH` in `config/config.php` must be `false` for the demo and submission.

---

## B2: Vehicles and services

### Files and what they do
| File | What it does |
|---|---|
| `src/Vehicle.php` | Validates registration plates (uppercase), checks ownership (`belongsTo`), verifies booking dependencies, and prevents deletion if booked. |
| `src/Service.php` | Catalogue domain model: casts types (`price` float, `is_active` bool), filters active items for customers, and implements soft deactivation when bookings exist. |
| `api/vehicles.php` | Customer endpoint for listing (`GET`), adding (`POST action=add`), and deleting (`POST action=delete`) vehicles with role and ownership checks. |
| `api/services.php` | Public/customer catalogue endpoint (`GET`) returning active services only. |
| `api/admin/services.php` | Admin catalogue endpoint (`GET` all services including inactive, `POST action=create|update|delete`). |

### Key ideas (say these in the demo)
- **Ownership verification**: `belongsTo($vehicleId, $userId)` prevents an attacker from altering or deleting another customer's vehicle even if they guess the vehicle ID.
- **Referential integrity vs. Soft Deactivation**:
  - A vehicle with bookings cannot be deleted (returns HTTP 409) to preserve booking references and historical tracking.
  - A service with past bookings is never hard-deleted; it is soft-deactivated (`is_active = 0`). This ensures old booking records still point to valid service metadata while hiding the service from new customers.
- **Strict Data Types**: MySQL returns decimals and booleans as strings by default in PDO; `Service::toPublic` explicitly casts `price` to `float` and `is_active` to `bool` so the JSON response adheres strictly to the API Contract.

### Likely examiner questions
1. *Why soft delete / deactivate services instead of deleting them?* Deleting a service would break historical booking records or cause foreign key violations. Setting `is_active = 0` hides it from future customers while preserving history.
2. *How do you prevent Insecure Direct Object References (IDOR) on vehicles?* We never rely only on `vehicle_id` from the request. We always cross-reference `user_id` from the secure server-side session using `belongsTo()`.

---

## B3: Booking engine, slot calculations and concurrency

### Files and what they do
| File | What it does |
|---|---|
| `src/Booking.php` | Core booking domain class: calculates available slots, handles pessimistic locking (`FOR UPDATE`), generates booking references, expires stale unpaid holds, and records status history. |
| `api/slots.php` | The required asynchronous Fetch endpoint: returns real-time slot availability (8 slots, Mon-Sat) or closed status on Sundays. |
| `api/book.php` | Places a temporary booking hold on a workshop bay with concurrency locking and snapshot pricing. |
| `api/bookings.php` | Customer endpoint for retrieving personal bookings list (`GET`) or single booking details with audit trail (`GET ?ref=...`). |
| `tests/test_b2_b3.php` | Automated PHP test suite for vehicles, services, slots, capacity exhaustion, and hold expiry. |

### Concurrency Note: How Double-Booking is Prevented
In high-concurrency systems, two customers might check a slot at the same second and both attempt to claim the last remaining bay (a classic Time-of-Check to Time-of-Use / TOCTOU race condition).
1. We wrap the booking process inside an ACID **Database Transaction** (`$pdo->beginTransaction()`).
2. Inside the transaction, we issue a locking read:
   ```sql
   SELECT booking_id, booking_status, created_at
   FROM bookings
   WHERE booking_date = :bdate AND slot_time = :stime
   FOR UPDATE
   ```
3. MySQL InnoDB places exclusive locks on the index records (or next-key/gap locks) for that `(booking_date, slot_time)`. Any other concurrent booking request for that slot must wait until the current transaction commits or rolls back.
4. The capacity count is evaluated inside this lock. If `activeCount >= SLOT_CAPACITY` (2), the transaction rolls back and immediately responds with **HTTP 409 Conflict** (`"Sorry, that slot was just taken. Please choose another."`).
5. Only if capacity is available is the new booking inserted, history written, and the transaction committed.

### Key ideas (say these in the demo)
- **Snapshot Pricing**: `amount` is stored directly on the booking row at the moment of booking (`bookings.amount`). If the workshop admin later increases service prices, existing bookings maintain the price agreed upon when booked.
- **Ephemeral 30-Minute Holds**: A slot is held in `pending_payment` for up to 30 minutes (`BOOKING_HOLD_MINUTES`). `Booking::expireStale()` automatically cancels unpaid holds older than 30 minutes, immediately releasing the bay back into workshop availability.
- **Audit Trail (`status_history`)**: Every status change (created, approved, completed, expired) logs an immutable entry with timestamp, status, and note for customer tracking.

### Likely examiner questions
1. *What happens if two people click "Book" for the last bay at the exact same millisecond?* The database transaction with `SELECT ... FOR UPDATE` serializes the requests. The first request grabs the lock, books the bay, and commits. The second request then evaluates capacity, sees 0 remaining bays, rolls back, and receives an HTTP 409 Conflict response.
2. *Why store amount on the booking if the service already has a price?* Services change prices over time. A booking represents a financial contract at a specific point in time; snapshotting the price prevents past bookings from showing altered amounts.
3. *How do unpaid bookings stop blocking bays indefinitely?* Any booking in `pending_payment` older than 30 minutes is automatically marked `cancelled` via `expireStale()`, freeing the bay for other customers.

---

## B5: Admin API, workflows, and dashboards

### Files and what they do
| File | What it does |
|---|---|
| `src/Payment.php` | Queries payment logs, aggregates total successful revenue, and handles refund operations on booking rejection. |
| `api/dashboard.php` | Customer dashboard metrics endpoint (`GET`): returns vehicle count, active bookings count, and latest booking. |
| `api/admin/dashboard.php` | Admin KPI dashboard endpoint (`GET`): computes bookings today, pending approvals, total revenue, active services, and 5 recent bookings. |
| `api/admin/bookings.php` | Admin booking management: filter by search query `q`, status, dates (`GET`), lookup with audit trail (`GET ?ref=...`), and `POST action=approve|reject`. |
| `api/admin/status.php` | Advances workshop service status stepper strictly forward (`POST`). |
| `api/admin/customers.php` | Administrative customer overview with aggregate vehicle and booking counts and attached vehicles list (`GET ?q=...`). |
| `api/admin/payments.php` | Payment audit log report with status/date filters and total revenue summary (`GET`). |
| `api/admin/records.php` | Historical vehicle service records search by registration, customer, or service ID (`GET`). |
| `tests/test_b5.php` | Automated PHP test suite for workflow state transitions, payment auto-refund, and anti-N+1 customer queries. |

### Key ideas (say these in the demo)
- **Strict Forward-Only Service Stepper**: The workshop workflow follows a strict sequential progression:
  `not_started` (0) → `received` (1) → `in_progress` (2) → `completed` (3) → `ready_for_pickup` (4).
  The server assigns integer order indices to each status. Attempting to update to a backward or identical status returns **HTTP 409 Conflict**. Status can only be updated for bookings that have already been `approved`.
- **Automated Refund on Rejection**: When an admin rejects a booking from `pending_approval`, the system automatically invokes `Payment::refund()` inside the same business operation. This updates any successful payment for that booking to `status = 'refunded'` and logs the event to `status_history`.
- **Anti-N+1 Query Optimization (`User::getAllCustomers`)**: When listing customers with their associated vehicles, naive code runs 1 query for customers plus $N$ queries for each customer's vehicles ($N+1$). We avoid this by running 1 query for customers and 1 batch query (`WHERE user_id IN (...)`) for all vehicles, merging them in memory.
- **Role-Based Access Control (RBAC)**: All administrative endpoints under `api/admin/` call `Auth::requireRole('admin')`, immediately returning HTTP 403 Forbidden if a logged-in customer attempts to inspect or call them.

### Likely examiner questions
1. *How do you prevent an admin from skipping statuses or moving backwards in the service workflow?* We enforce an ordered status dictionary (`not_started: 0, received: 1, in_progress: 2, completed: 3, ready_for_pickup: 4`). If the target status index is $\le$ the current status index, the request is rejected with HTTP 409.
2. *What happens to customer funds if an admin rejects their booking?* Rejection automatically triggers `Payment::refund()`, updating the payment record to `refunded`, releasing the workshop bay, and recording a status history audit entry.
3. *How do you avoid performance bottlenecks when loading customers with their vehicles?* Instead of querying vehicles in a loop for each customer, we extract all customer IDs and fetch all vehicles in a single batch query, grouping them in memory.



---

## B4: Simulated Payment & Gateway Polymorphism

### Files and what they do
| File | What it does |
|---|---|
| `src/PaymentGatewayInterface.php` | Defines the contract `charge(array $card, float $amount): array` for swappable payment processors. |
| `src/SimulatedPaymentGateway.php` | Implements the interface, evaluates test cards per contract 2.9 (4242 success, 0002 decline, 9995 insufficient funds, expired check, Luhn check), and simulates ~900ms realistic network latency without external keys or network calls. |
| `src/Payment.php` | Records payment attempts (both success and failed), looks up payments by booking, aggregates successful revenue, and handles refund status updates. |
| `api/payment.php` | Customer endpoint (`POST`) for charging a booking hold. Validates 16 digits, Luhn checksum, expiry, and CVV; ensures only `card_last4` is persisted; transitions booking to `pending_approval` on success or logs failed audit rows. |
| `tests/test_b4.php` | Automated test suite verifying card test matrices, Luhn validation, double-payment prevention, IDOR protection, hold expiry, and database privacy auditing. |

### Key ideas (say these in the demo)
- **Polymorphism via Interface (`PaymentGatewayInterface`)**: The booking engine does not couple itself to a specific bank or gateway. It depends on `PaymentGatewayInterface`. In production, a `PaystackGateway` or `FlutterwaveGateway` could be swapped in without modifying any booking code (Dependency Inversion Principle).
- **PCI-DSS Compliance (Zero Card Storage)**: We NEVER store the 16-digit card number or 3-digit CVV in our database, session, or error logs. Only the non-sensitive `card_last4` is kept for customer receipt identification.
- **Failures are Recorded for Auditing**: Unlike invalid format requests (which return HTTP 400 with no database write), gateway declines (0002, 9995, expired) write a row with `status = 'failed'` and the decline reason, allowing workshop admins to see payment attempt histories in the audit reports.

### Likely examiner questions
1. *Why use an interface for the payment gateway?* Interfaces decouple high-level business logic from low-level payment providers. This allows the system to use `SimulatedPaymentGateway` in demo environments and seamlessly swap to a real bank gateway without altering `api/payment.php`.
2. *Why does a bad Luhn return HTTP 400 but a declined card returns HTTP 422?* HTTP 400 Bad Request indicates a client syntax/formatting mistake (e.g., typing a digit wrong). HTTP 422 Unprocessable Content indicates valid syntax, but the payment gateway declined the transaction due to business rules (e.g., insufficient balance).
3. *How do you ensure credit card information is not compromised?* Full card numbers and CVVs exist only in memory during the request. Only `card_last4` is written to MySQL, eliminating data breach liability.

---

## B6: Architecture Summary, Demo Scripts & Final Audit

### Comprehensive Class Directory (`src/`)
* **`ApiResponse`**: Formats all HTTP API outputs into the standardized JSON envelope (`success`, `message`, `data`, `errors`), sets HTTP status codes and headers, and halts script execution.
* **`Auth`**: Manages session state, handles login credential registration, and provides dual-mode guards (`requireLogin`, `requireRole`) that return JSON 401/403 for API routes and HTTP redirects for UI pages.
* **`Booking`**: Central domain model that orchestrates slot generation, concurrency locking (`SELECT ... FOR UPDATE`), 30-minute hold expiry, admin status advancement, and dashboard metrics.
* **`Database`**: Singleton PDO connection manager ensuring exactly one persistent MySQL connection per PHP process with prepared statement emulation disabled.
* **`Payment`**: Handles recording of payment transactions, query filtering for administrative reports, revenue summation, and automated refund management.
* **`PaymentGatewayInterface`**: Abstract contract enforcing gateway interoperability and polymorphism across all payment providers.
* **`Service`**: Catalogue domain model managing workshop repair services, typecasting prices to floats, and providing soft-deactivation when historical bookings exist.
* **`SimulatedPaymentGateway`**: Concrete implementation of the gateway interface providing realistic test card behavior, Luhn validation, and latency simulation.
* **`User`**: Account domain model handling customer registration with server-side validation, secure login with bcrypt password verification, and optimized anti-N+1 customer vehicle loading.
* **`Vehicle`**: Vehicle domain model managing customer car registrations, uppercase plate formatting, ownership validation (`belongsTo`), and booking referential integrity.

### API Endpoints Directory (`api/`)
* **`api/auth/register.php`**: Creates new customer accounts with role forced to `customer` and automatically establishes an authenticated session.
* **`api/auth/login.php`**: Authenticates users with constant-time bcrypt verification and returns role-specific dashboard redirects.
* **`api/auth/logout.php`**: Destroys server sessions and clears session cookies.
* **`api/auth/me.php`**: Returns the currently authenticated user profile for frontend UI hydration.
* **`api/services.php`**: Returns all active workshop services for customer booking catalogues.
* **`api/vehicles.php`**: Provides customer vehicle listing, registration (`action=add`), and safe deletion (`action=delete`).
* **`api/slots.php`**: Asynchronous Fetch API endpoint delivering real-time bay capacity counts for any chosen date.
* **`api/book.php`**: Atomically claims a workshop bay for 30 minutes with snapshot pricing and concurrency locking.
* **`api/payment.php`**: Charges simulated cards, validates Luhn checksums, and transitions bookings to `pending_approval`.
* **`api/bookings.php`**: Returns customer booking histories or single booking detail with full audit history (`?ref=`).
* **`api/dashboard.php`**: Aggregates customer KPI metrics (vehicle count, active bookings, next appointment).
* **`api/admin/dashboard.php`**: Computes workshop administrative KPIs (bookings today, pending approvals, total revenue, active services).
* **`api/admin/services.php`**: Provides admin catalogue management (create, update, delete/deactivate).
* **`api/admin/bookings.php`**: Filterable administrative booking table with approve/reject workflow actions.
* **`api/admin/status.php`**: Advances workshop service status strictly forward along the 5-step stepper.
* **`api/admin/customers.php`**: Searchable customer overview with aggregated vehicle and booking statistics.
* **`api/admin/payments.php`**: Financial audit log of all successful, failed, and refunded payment transactions.
* **`api/admin/records.php`**: Search engine for historical vehicle maintenance logs across customer and registration filters.

---

### "How to Explain the Booking & Payment Flow in 60 Seconds" (Demo Script)

> "When a customer selects a date and service on our booking page, our frontend makes an asynchronous **Fetch** call to `api/slots.php`. The backend computes real-time bay capacity by counting active bookings and expiring any unpaid holds older than 30 minutes.
>
> When the customer clicks 'Book', `api/book.php` initiates an **ACID transaction** with `SELECT ... FOR UPDATE` in MySQL. This places an InnoDB exclusive lock on the chosen slot. If another customer tries to claim the same bay at the same second, the second transaction is blocked until capacity is evaluated. If the bay is full, it rolls back and returns **HTTP 409 Conflict**. If available, it places a temporary 30-minute hold with snapshot pricing.
>
> Next, the customer enters card details on `api/payment.php`. The backend validates the card using the **Luhn algorithm**, and delegates charging to our **SimulatedPaymentGateway** via `PaymentGatewayInterface`. Only the last 4 digits are saved to MySQL—card numbers and CVVs are never persisted. A successful payment advances the booking to `pending_approval`, while a declined card records a failed attempt for audit tracking."

---

### Course Requirements Compliance Checklist

| Requirement | Satisfied? | Implementation Details |
|---|:---:|---|
| **HTML, CSS, vanilla JS, PHP 8 (OOP), MySQL** | ✅ Yes | Zero frameworks. PHP 8 strict types, OOP classes, InnoDB MySQL. |
| **No Frameworks** | ✅ Yes | No Laravel, React, Vue, Bootstrap, Tailwind, Composer, or npm. |
| **Asynchronous Fetch API Communication** | ✅ Yes | `api/slots.php`, `api/book.php`, `api/payment.php` designed for Fetch. |
| **PHP OOP Domain Classes** | ✅ Yes | 10 classes in `src/` (`Booking`, `User`, `Payment`, etc.). |
| **Real MySQL Storage & Retrieval** | ✅ Yes | `ui_vsb_db` with 6 relational tables, FKs, transactions, indexes. |
| **At least one API Endpoint** | ✅ Yes | 17 RESTful JSON endpoints with standard envelopes. |
| **Simulated Payment Only** | ✅ Yes | `SimulatedPaymentGateway` with test card matrices and simulated latency. |
| **Demonstrates Successful & Failed Operations**| ✅ Yes | Duplicate plate (409), bad password (401), slot full (409), card declined (422), insufficient funds (422), hold expired (409), backward stepper (409). |
| **Submitted SQL File (`database/ui_vsb.sql`)** | ✅ Yes | Idempotent, drops tables, sets schema, includes rich demo data. |
