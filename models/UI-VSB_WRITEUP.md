# Vehicle Service Booking and Payment Platform (ui-vsb)
### University of Ibadan Maintenance Directorate

**Course:** ______  **Department:** ______  **Date:** ______

| S/N | Name | Matric No. | Part worked on |
|---|---|---|---|
| 1 | | | |
| 2 | | | |
| 3 | | | |
| 4 | | | |
| 5 | | | |

> **How to use this file:** Text is drafted. Blocks that look like `🖼️ DRAW THIS` mark where you must create an image (the mermaid code under it is a starting sketch you can paste into mermaid.live, draw.io or Lucidchart). Blocks marked `📸 SCREENSHOT` are for real screenshots from the running system. Replace every `[ ... ]` with your own details and delete these instructions before submitting.

---

## Table of Contents
1. Introduction
2. Problem Statement
3. Objectives
4. System Requirements
5. Use-Case Diagram
6. System Architecture
7. Database / ER Diagram
8. Database Tables
9. Important Interface Screenshots
10. Explanation of PHP OOP Implementation
11. Explanation of API / Fetch Communication
12. Payment Workflow
13. Testing
14. Conclusion

---

## 1. Introduction

The University of Ibadan Maintenance Directorate runs a vehicle workshop that services and repairs university vehicles. Appointments have so far been arranged by visiting the workshop or calling, which is slow and hard to track.

This project is the **Vehicle Service Booking and Payment Platform**, a web application that lets staff register online, add their vehicles, choose a service, check available appointment times, book, pay a service fee through a simulated payment system, and follow the progress of their vehicle. Workshop administrators manage services, prices, bookings, payments and service status from an admin panel.

The system is built with HTML, CSS and JavaScript on the front end, PHP (object-oriented) on the back end, and MySQL for storage. Asynchronous communication using the JavaScript Fetch API is used for slot checking, booking and payment.

## 2. Problem Statement

The current manual process has these problems:

- Customers must travel to the workshop or phone to book, which wastes time.
- There is no live view of free appointment slots, so bookings clash or customers wait.
- Payment is handled manually, and records are easy to lose or dispute.
- Customers cannot see whether their vehicle has been received, is being worked on, or is ready.
- Paper records make it hard for the workshop to search past services or see customer and payment history.

A web-based system is needed to automate booking, payment and tracking, and to store the records in a database.

## 3. Objectives

**Main objective:** To design and implement a web-based vehicle service booking and payment platform for the University of Ibadan Maintenance Directorate.

**Specific objectives:**
1. Allow customers to register, log in and manage their vehicle details.
2. Display available services with their prices.
3. Check available appointment slots in real time using the Fetch API.
4. Allow customers to submit a booking and receive a unique booking reference.
5. Provide a simulated payment workflow with a payment confirmation.
6. Let customers track the status of their vehicle after submission.
7. Give administrators tools to manage services and prices, approve or reject bookings, update service status, and view customer and payment records.
8. Store and retrieve all data using a MySQL database.

## 4. System Requirements

### 4.1 Functional requirements

**Customer**
| ID | Requirement |
|---|---|
| FR1 | Register an account and log in/out |
| FR2 | Add, view and delete own vehicles (reg number, make, model, year) |
| FR3 | View services and their costs |
| FR4 | Select a vehicle, service and preferred date |
| FR5 | Check available slots asynchronously |
| FR6 | Submit a booking and receive a booking reference |
| FR7 | Pay via simulated payment and receive confirmation |
| FR8 | Track booking and vehicle service progress |

**Administrator**
| ID | Requirement |
|---|---|
| FR9 | Add, edit, delete services and set prices |
| FR10 | View all bookings and search/filter them |
| FR11 | Approve or reject bookings |
| FR12 | Update vehicle service status |
| FR13 | View customer information |
| FR14 | View payment records |
| FR15 | Search service records |

### 4.2 Non-functional requirements
- **Security:** hashed passwords, prepared statements, role-based access, session protection.
- **Usability:** simple, responsive interface that works on desktop and mobile browsers.
- **Performance:** slot availability returned in under a second on a local server.
- **Reliability:** transactions prevent two users booking the last slot at the same time.
- **Maintainability:** modular OOP code, one class per domain concept.
- **Payment safety:** only simulated payments; full card numbers and CVV are never stored.

### 4.3 Software requirements
| Item | Used |
|---|---|
| Front end | HTML5, CSS3, JavaScript (ES6, Fetch API) |
| Back end | PHP 8.x |
| Database | MySQL / MariaDB |
| Server | Apache via XAMPP/WAMP |
| Tools | VS Code, phpMyAdmin, Git, browser (Chrome/Edge) |

### 4.4 Hardware requirements
Any computer with at least 4 GB RAM, 2 GHz processor and 1 GB free disk space; a network connection for team collaboration.

## 5. Use-Case Diagram

**Actors:** Customer, Workshop Administrator, Payment Gateway (simulated, secondary actor).

**Customer use cases:** Register, Login, Manage Vehicles, View Services, Check Available Slots, Book Service, Make Payment, View Confirmation, Track Vehicle Status.
**Admin use cases:** Login, Manage Services, Set Prices, View Bookings, Approve/Reject Booking, Update Service Status, View Customers, View Payments, Search Records.

> 🖼️ **DRAW THIS — Figure 5.1: Use-case diagram**
> Draw a system boundary box titled "Vehicle Service Booking System". Put **Customer** on the left and **Administrator** on the right as stick figures; put **Payment Gateway (Simulated)** on the far right. Ovals inside the box for each use case above. Draw `<<include>>` from *Book Service* to *Check Available Slots* and from *Make Payment* to *Payment Gateway*; `<<include>>` from *Book Service*/*Track Status* to *Login*. Link actors to their use cases with plain lines.
>
> ```mermaid
> flowchart LR
>   C([Customer]):::a --> UC1(Register)
>   C --> UC2(Login)
>   C --> UC3(Manage Vehicles)
>   C --> UC4(View Services)
>   C --> UC5(Check Slots - Fetch)
>   C --> UC6(Book Service)
>   C --> UC7(Make Payment)
>   C --> UC8(Track Vehicle Status)
>   A([Administrator]):::a --> UC2
>   A --> UC9(Manage Services and Prices)
>   A --> UC10(Approve / Reject Bookings)
>   A --> UC11(Update Service Status)
>   A --> UC12(View Customers)
>   A --> UC13(View Payments)
>   A --> UC14(Search Service Records)
>   UC6 -. include .-> UC5
>   UC7 -. include .-> PG([Payment Gateway - Simulated])
>   classDef a fill:#fff,stroke:#333
> ```

## 6. System Architecture

The system uses a **three-tier (client–server) architecture**:

1. **Presentation layer** — HTML, CSS and JavaScript in the browser. Pages are rendered by PHP; dynamic actions (slots, booking, payment, admin updates) use `fetch()` to call the API.
2. **Application layer** — PHP on Apache. API endpoint files handle requests and delegate to OOP classes (`User`, `Vehicle`, `Service`, `Booking`, `Payment`, `Auth`, `SimulatedPaymentGateway`).
3. **Data layer** — MySQL database accessed through PDO prepared statements.

Requests flow: Browser → (HTTP / Fetch, JSON) → PHP API endpoint → PHP class → PDO → MySQL → back as JSON.

> 🖼️ **DRAW THIS — Figure 6.1: System architecture diagram**
> Three horizontal layers. Top: "Browser (Customer / Admin)" with HTML, CSS, JavaScript boxes. Middle: "Apache + PHP" containing "API endpoints (/api/*.php)" above "OOP Classes (User, Vehicle, Service, Booking, Payment, Auth)" and a side box "Simulated Payment Gateway". Bottom: "MySQL Database". Arrows: browser ↔ API labelled "HTTP / Fetch (JSON)"; classes ↔ database labelled "PDO"; Payment class ↔ Simulated Gateway.
>
> ```mermaid
> flowchart TB
>   subgraph Client[Presentation layer - Browser]
>     H[HTML pages] --- J[JavaScript Fetch] --- S[CSS]
>   end
>   subgraph Server[Application layer - Apache + PHP]
>     API[API endpoints /api/*.php] --> CL[OOP classes: User, Vehicle, Service, Booking, Payment, Auth]
>     CL --> GW[SimulatedPaymentGateway]
>   end
>   subgraph Data[Data layer]
>     DB[(MySQL ui_vsb_db)]
>   end
>   J -- "HTTP / JSON" --> API
>   CL -- "PDO prepared statements" --> DB
> ```

> 🖼️ **DRAW THIS (optional) — Figure 6.2: Booking sequence diagram**
> Lifelines: Customer, Browser (JS), slots.php, Booking class, MySQL. Steps: select date → fetch slots → query bookings → return slot list → pick slot → POST book.php → transaction + insert → return booking ref.

## 7. Database / ER Diagram

The database `ui_vsb_db` has six tables: `users`, `vehicles`, `services`, `bookings`, `payments` and `status_history`. A user (customer) owns many vehicles and makes many bookings. Each booking is for one vehicle and one service, can have payment attempts, and has a history of status changes.

> 🖼️ **DRAW THIS — Figure 7.1: Entity-Relationship diagram**
> One box per table with attributes; underline PKs, mark FKs. Crow's-foot relationships as below. You can also export from phpMyAdmin → Designer once the tables exist, which gives you a real diagram.
>
> ```mermaid
> erDiagram
>   USERS ||--o{ VEHICLES : owns
>   USERS ||--o{ BOOKINGS : makes
>   VEHICLES ||--o{ BOOKINGS : "is serviced in"
>   SERVICES ||--o{ BOOKINGS : "is booked as"
>   BOOKINGS ||--o{ PAYMENTS : "is paid by"
>   BOOKINGS ||--o{ STATUS_HISTORY : logs
>   USERS ||--o{ STATUS_HISTORY : "changed by"
>   USERS { int user_id PK
>     string full_name
>     string email
>     string phone
>     string password_hash
>     enum role }
>   VEHICLES { int vehicle_id PK
>     int user_id FK
>     string reg_number
>     string make
>     string model
>     year year }
>   SERVICES { int service_id PK
>     string name
>     text description
>     decimal price
>     int duration_mins
>     bool is_active }
>   BOOKINGS { int booking_id PK
>     string booking_ref
>     int user_id FK
>     int vehicle_id FK
>     int service_id FK
>     date booking_date
>     time slot_time
>     decimal amount
>     enum booking_status
>     enum service_status }
>   PAYMENTS { int payment_id PK
>     int booking_id FK
>     string transaction_ref
>     decimal amount
>     string card_last4
>     enum status
>     string failure_reason }
>   STATUS_HISTORY { int history_id PK
>     int booking_id FK
>     string status
>     string note
>     int changed_by FK }
> ```

## 8. Database Tables

> 📌 Keep these as Word tables. If lecturers want images, take a phpMyAdmin screenshot of each table's **Structure** tab (see Section 9 list).

### 8.1 `users`
| Column | Type | Constraints | Description |
|---|---|---|---|
| user_id | INT | PK, AUTO_INCREMENT | Unique user id |
| full_name | VARCHAR(100) | NOT NULL | Full name |
| email | VARCHAR(120) | UNIQUE, NOT NULL | Login email |
| phone | VARCHAR(20) | NOT NULL | Phone number |
| password_hash | VARCHAR(255) | NOT NULL | Hashed password |
| role | ENUM('customer','admin') | NOT NULL | User role |
| created_at | TIMESTAMP | DEFAULT NOW | Registration time |

### 8.2 `vehicles`
| Column | Type | Constraints | Description |
|---|---|---|---|
| vehicle_id | INT | PK, AUTO_INCREMENT | Unique vehicle id |
| user_id | INT | FK → users | Owner |
| reg_number | VARCHAR(20) | UNIQUE, NOT NULL | Registration number |
| make | VARCHAR(50) | NOT NULL | Manufacturer |
| model | VARCHAR(50) | NOT NULL | Model |
| year | YEAR | NOT NULL | Year of manufacture |
| created_at | TIMESTAMP | DEFAULT NOW | Date added |

### 8.3 `services`
| Column | Type | Constraints | Description |
|---|---|---|---|
| service_id | INT | PK, AUTO_INCREMENT | Unique service id |
| name | VARCHAR(100) | NOT NULL | Service name |
| description | TEXT | | What the service includes |
| price | DECIMAL(10,2) | NOT NULL | Fee in naira |
| duration_mins | INT | NOT NULL | Estimated duration |
| is_active | TINYINT(1) | DEFAULT 1 | Shown to customers if 1 |
| created_at | TIMESTAMP | DEFAULT NOW | Created time |

### 8.4 `bookings`
| Column | Type | Constraints | Description |
|---|---|---|---|
| booking_id | INT | PK, AUTO_INCREMENT | Unique id |
| booking_ref | VARCHAR(20) | UNIQUE, NOT NULL | e.g. VSB-20261006-A4K9 |
| user_id | INT | FK → users | Customer |
| vehicle_id | INT | FK → vehicles | Vehicle |
| service_id | INT | FK → services | Service |
| booking_date | DATE | NOT NULL | Appointment date |
| slot_time | TIME | NOT NULL | Appointment time |
| amount | DECIMAL(10,2) | NOT NULL | Price at booking time |
| booking_status | ENUM | NOT NULL | pending_payment, pending_approval, approved, rejected, cancelled |
| service_status | ENUM | NOT NULL | not_started, received, in_progress, completed, ready_for_pickup |
| notes | VARCHAR(255) | NULL | Optional notes |
| created_at | TIMESTAMP | DEFAULT NOW | Created time |

### 8.5 `payments`
| Column | Type | Constraints | Description |
|---|---|---|---|
| payment_id | INT | PK, AUTO_INCREMENT | Unique id |
| booking_id | INT | FK → bookings | Booking paid for |
| transaction_ref | VARCHAR(30) | UNIQUE, NOT NULL | e.g. TXN-20261006-8F3K2A |
| amount | DECIMAL(10,2) | NOT NULL | Amount charged |
| method | VARCHAR(20) | DEFAULT 'test_card' | Payment method |
| card_last4 | CHAR(4) | NULL | Last four card digits only |
| status | ENUM('success','failed','refunded') | NOT NULL | Outcome |
| failure_reason | VARCHAR(100) | NULL | Reason if failed |
| paid_at | TIMESTAMP | DEFAULT NOW | Time of attempt |

### 8.6 `status_history`
| Column | Type | Constraints | Description |
|---|---|---|---|
| history_id | INT | PK, AUTO_INCREMENT | Unique id |
| booking_id | INT | FK → bookings | Related booking |
| status | VARCHAR(30) | NOT NULL | Status recorded |
| note | VARCHAR(255) | NULL | Optional note |
| changed_by | INT | FK → users | Who made the change |
| changed_at | TIMESTAMP | DEFAULT NOW | When |

## 9. Important Interface Screenshots

> Take all screenshots at the same browser width, with realistic data (not "test test"). Crop out the browser bar unless the URL matters. Caption each as shown.

**Customer interface**

> 📸 **SCREENSHOT — Figure 9.1: Registration page** (show validation messages if possible)
>
> 📸 **SCREENSHOT — Figure 9.2: Login page**
>
> 📸 **SCREENSHOT — Figure 9.3: Customer dashboard**
>
> 📸 **SCREENSHOT — Figure 9.4: Vehicle registration page with vehicle list**
>
> 📸 **SCREENSHOT — Figure 9.5: Services page showing prices**
>
> 📸 **SCREENSHOT — Figure 9.6: Booking page with available slots loaded by Fetch** (include a "Full" slot)
>
> 📸 **SCREENSHOT — Figure 9.7: Payment page with test-mode banner**
>
> 📸 **SCREENSHOT — Figure 9.8: Payment confirmation / receipt with booking reference**
>
> 📸 **SCREENSHOT — Figure 9.9: Vehicle tracking page with progress stepper**

**Administrator interface**

> 📸 **SCREENSHOT — Figure 9.10: Admin dashboard**
>
> 📸 **SCREENSHOT — Figure 9.11: Manage services (add/edit modal)**
>
> 📸 **SCREENSHOT — Figure 9.12: Bookings list with approve/reject and status update**
>
> 📸 **SCREENSHOT — Figure 9.13: Customers page**
>
> 📸 **SCREENSHOT — Figure 9.14: Payment records page**
>
> 📸 **SCREENSHOT — Figure 9.15: Search service records**

**Database (phpMyAdmin)**

> 📸 **SCREENSHOT — Figure 9.16: List of tables in `ui_vsb_db`**
>
> 📸 **SCREENSHOT — Figure 9.17: `bookings` table with real stored rows** (proves data is stored in MySQL)
>
> 📸 **SCREENSHOT — Figure 9.18: `payments` table showing both success and failed rows**

## 10. Explanation of PHP OOP Implementation

The back end is organised into classes in the `src/` folder, loaded with an autoloader (`spl_autoload_register`). Each class represents one concept of the problem domain and hides its database logic behind methods.

| Class | Role |
|---|---|
| `Database` | Singleton that creates one PDO connection shared across the app. |
| `User` | Registers users (hashing passwords), logs them in, lists customers for the admin. |
| `Vehicle` | Adds, lists, checks ownership of and deletes vehicles. |
| `Service` | Retrieves, creates, updates and deletes services and prices. |
| `Booking` | Generates slots, checks availability, creates bookings in a transaction, approves/rejects, updates service status, writes history. |
| `Payment` | Records, retrieves and refunds payments. |
| `PaymentGatewayInterface` | Declares `charge()`, the contract every gateway must follow. |
| `SimulatedPaymentGateway` | Implements the interface using test-card rules; no real money moves. |
| `Auth` | Starts sessions, protects pages and API endpoints by role. |
| `ApiResponse` | Produces consistent JSON responses with correct HTTP codes. |

**OOP concepts demonstrated**
- **Encapsulation:** database connection and internal logic are private; other code only uses public methods.
- **Abstraction and interface:** `PaymentGatewayInterface` lets the payment code depend on a contract, not a specific gateway.
- **Polymorphism:** `SimulatedPaymentGateway` can be replaced by a real gateway class without changing the `Payment` flow.
- **Singleton pattern:** `Database` ensures a single connection.
- **Separation of concerns:** API files only receive requests and return JSON; classes do the business logic and data access.

> 📌 Paste a short code snippet here (about 10–15 lines) — e.g. the `Database` singleton, and the `charge()` method — as a code block or screenshot.
>
> 🖼️ **DRAW THIS — Figure 10.1: UML class diagram**
> Boxes with name / attributes / methods for `Database`, `User`, `Vehicle`, `Service`, `Booking`, `Payment`, `Auth`, `PaymentGatewayInterface`, `SimulatedPaymentGateway`. Dashed arrow with hollow head from `SimulatedPaymentGateway` to `PaymentGatewayInterface` (implements). Association lines from `Booking` to `User`, `Vehicle`, `Service`; from `Payment` to `Booking` and to `PaymentGatewayInterface`; all data classes use `Database`.
>
> ```mermaid
> classDiagram
>   class Database { -PDO pdo; +getInstance() Database; +getConnection() PDO }
>   class User { +register(data); +login(email,pw); +getAllCustomers() }
>   class Vehicle { +add(userId,data); +getByUser(userId); +delete(id) }
>   class Service { +getAllActive(); +create(data); +update(id,data); +delete(id) }
>   class Booking { +getAvailableSlots(date); +create(data); +approve(id); +reject(id); +updateServiceStatus(id,status) }
>   class Payment { +record(data); +getAll(); +refund(bookingId) }
>   class PaymentGatewayInterface { <<interface>> +charge(card,amount) }
>   class SimulatedPaymentGateway { +charge(card,amount) }
>   class Auth { +requireLogin(); +requireRole(role) }
>   PaymentGatewayInterface <|.. SimulatedPaymentGateway
>   Booking --> User
>   Booking --> Vehicle
>   Booking --> Service
>   Payment --> Booking
>   Payment --> PaymentGatewayInterface
>   User ..> Database
>   Vehicle ..> Database
>   Service ..> Database
>   Booking ..> Database
>   Payment ..> Database
> ```

## 11. Explanation of API / Fetch Communication

The **Fetch API** (`fetch()` in JavaScript) lets the page send a request to the server in the background and receive JSON back, without reloading the page. In this system three major operations use it: **slot checking**, **booking submission** and **payment**. Admin actions (approve/reject, status update) also use it.

### 11.1 API endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/slots.php?date=&service_id=` | Returns available slots for a date |
| POST | `/api/book.php` | Creates a booking, returns booking reference |
| POST | `/api/payment.php` | Processes simulated payment |
| GET | `/api/booking_status.php` | Returns booking and service progress |
| GET/POST/DELETE | `/api/vehicles.php` | Manages vehicles |
| POST/PUT/DELETE | `/api/admin/services.php` | Admin service management |
| GET/POST | `/api/admin/bookings.php` | Admin list, search, approve/reject |
| POST | `/api/admin/status.php` | Admin updates service status |
| GET | `/api/admin/customers.php` | Admin views customers |
| GET | `/api/admin/payments.php` | Admin views payments |

All endpoints return JSON in the form `{ "success": true|false, "message": "...", "data": ... }` with suitable HTTP codes (200, 201, 400, 401, 403, 409, 422).

### 11.2 Slot-checking flow (main Fetch operation)
1. Customer picks a service and a date on the booking page.
2. JavaScript sends `fetch('/api/slots.php?date=2026-10-12&service_id=3')`.
3. The PHP endpoint calls `Booking::getAvailableSlots()`, which counts existing bookings per time slot in MySQL.
4. The server returns JSON listing each slot as available or full.
5. JavaScript builds the slot buttons on the page, with no page reload; full slots are disabled.

> 📌 Paste a short snippet of the `fetch()` call in `book.js` and the JSON response below.
>
> ```json
> { "success": true, "message": "Slots loaded",
>   "data": [ {"time":"08:00","available":true,"remaining":2},
>             {"time":"09:00","available":true,"remaining":1},
>             {"time":"10:00","available":false,"remaining":0} ] }
> ```
>
> 🖼️ **DRAW THIS — Figure 11.1: Fetch request/response sequence**
> Browser (JS) → `GET /api/slots.php` → PHP endpoint → `Booking` class → MySQL; return arrows with JSON, then "render slot buttons".
>
> 📸 **SCREENSHOT — Figure 11.2: Browser DevTools → Network tab** showing the `slots.php` request and JSON response (great proof for the demo).

## 12. Payment Workflow

Payment is **simulated**: no real card is charged and no real bank is contacted. A `SimulatedPaymentGateway` class mimics a gateway's behaviour with test cards.

**Steps**
1. After the booking is created (status `pending_payment`), the customer is taken to the payment page showing the order summary and amount.
2. The customer enters test card details. Client-side checks validate the format.
3. JavaScript sends the details to `/api/payment.php` using `fetch()`; a loading indicator is shown.
4. The server checks the user owns the booking and that it is unpaid, and validates the card (length, Luhn check, expiry, CVV).
5. The `SimulatedPaymentGateway` returns success or a failure reason.
6. **Success:** a `payments` row (`success`) is saved with a transaction reference and the last four digits; the booking moves to `pending_approval`; the customer sees a confirmation with booking reference and receipt.
7. **Failure:** a `payments` row (`failed`) is saved with the reason; the booking stays unpaid; the customer sees an error and may retry.
8. The administrator later approves the booking, or rejects it, in which case the payment is marked `refunded` (simulated).

**Test cards used**

| Card number | Result |
|---|---|
| 4242 4242 4242 4242 | Successful payment |
| 4000 0000 0000 0002 | Declined (card_declined) |
| 4000 0000 0000 9995 | Declined (insufficient_funds) |
| Past expiry date | Declined (expired_card) |

> 🖼️ **DRAW THIS — Figure 12.1: Payment workflow flowchart**
> Start → Booking created → Open payment page → Enter test card → Validate card? (No → show error → back to enter card) → Gateway charge? (Declined → save failed payment → show error → retry) / (Approved → save successful payment → booking = pending approval → show confirmation) → Admin decision? (Approve → approved) / (Reject → refund) → End.
>
> ```mermaid
> flowchart TD
>   A([Booking created - pending payment]) --> B[Open payment page]
>   B --> C[Enter test card details]
>   C --> D{Card valid?}
>   D -- No --> E[Show validation error] --> C
>   D -- Yes --> F[Send to /api/payment.php]
>   F --> G{Simulated gateway result}
>   G -- Declined --> H[Save payment as FAILED] --> I[Show error, allow retry] --> C
>   G -- Approved --> J[Save payment as SUCCESS + TXN ref]
>   J --> K[Booking = pending approval]
>   K --> L[Show confirmation + booking ref]
>   L --> M{Admin decision}
>   M -- Approve --> N([Booking approved])
>   M -- Reject --> O[Payment marked REFUNDED] --> P([Booking rejected])
> ```
>
> 📸 **SCREENSHOT — Figure 12.2: Failed payment message**
> 📸 **SCREENSHOT — Figure 12.3: Successful payment confirmation**

## 13. Testing

Testing was done manually on a local XAMPP server. Both successful and failed operations were tested.

### 13.1 Test cases

| ID | Feature | Input / action | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| T01 | Register | Valid details | Account created | | ☐ Pass ☐ Fail |
| T02 | Register | Email already used | Error: email exists | | |
| T03 | Register | Password under 8 chars | Validation error | | |
| T04 | Login | Correct credentials | Redirect to dashboard | | |
| T05 | Login | Wrong password | Error, no login | | |
| T06 | Add vehicle | Valid details | Saved and listed | | |
| T07 | Add vehicle | Duplicate reg number | Error shown | | |
| T08 | View services | Open services page | Services and prices from DB | | |
| T09 | Check slots (Fetch) | Select date | Slots load without reload | | |
| T10 | Check slots | Date in the past | Rejected | | |
| T11 | Book service | Valid selection | Booking ref generated, saved | | |
| T12 | Book service | Slot already full | Error 409, slots refresh | | |
| T13 | Payment | Card 4242… | Success, receipt, status pending approval | | |
| T14 | Payment | Card 4000 0000 0000 0002 | Declined, booking unpaid, failed row stored | | |
| T15 | Payment | Expired card | Declined with reason | | |
| T16 | Payment | Invalid card number | Rejected with validation error | | |
| T17 | Admin approve | Approve paid booking | Status approved | | |
| T18 | Admin reject | Reject paid booking | Rejected, payment refunded | | |
| T19 | Update status | Set to In progress | Customer tracking updates | | |
| T20 | Service CRUD | Add / edit / delete service | DB updated | | |
| T21 | Search records | Search by reg number | Matching records | | |
| T22 | Access control | Customer opens admin URL | Access denied / redirect | | |
| T23 | Access control | Logged-out user opens dashboard | Redirect to login | | |
| T24 | Security | `' OR 1=1 --` in login | No bypass (prepared statements) | | |

### 13.2 Test screenshots
> 📸 **SCREENSHOT — Figure 13.1:** Successful booking and booking reference (T11)
> 📸 **SCREENSHOT — Figure 13.2:** Slot-full error (T12)
> 📸 **SCREENSHOT — Figure 13.3:** Declined payment (T14)
> 📸 **SCREENSHOT — Figure 13.4:** Access denied for customer on admin page (T22)
> 📸 **SCREENSHOT — Figure 13.5:** Failed login (T05)

### 13.3 Summary
Summarise results in 2–3 sentences, e.g. "All 24 test cases were executed; [x] passed. Issues found during testing included [..], which were fixed by [..]."

## 14. Conclusion

The Vehicle Service Booking and Payment Platform replaces phone calls and walk-ins with an online process in which customers register vehicles, check real-time availability, book, pay through a simulated gateway and track progress, while administrators manage services, bookings, payments and records. The project meets the requirements of the portfolio: it uses HTML, CSS, JavaScript, PHP and MySQL; PHP is organised with object-oriented classes; the Fetch API is used for slot checking, booking and payment; real data is stored in and retrieved from MySQL; and payment is fully simulated.

**Limitations:** payment is simulated and not connected to a real gateway; notifications by SMS or email are not included; the slot model uses fixed one-hour slots with a fixed bay capacity.

**Future work:** integrate a real gateway (e.g. Paystack in test mode, then live), send email/SMS reminders, add customer cancellation and rescheduling, generate PDF receipts, add reporting dashboards, and build a mobile app.

---

## References
- PHP Manual — https://www.php.net/manual/
- MDN Web Docs: Fetch API — https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API
- MySQL Reference Manual — https://dev.mysql.com/doc/
- [Add course materials / textbooks used]

## Appendix (optional)
- A. Full SQL file (`ui_vsb.sql`)
- B. Key source code listings
- C. Setup instructions (copy folder to `htdocs`, import SQL in phpMyAdmin, open `http://localhost/ui-vsb`)
- D. Default admin login: `admin@ui.edu.ng` / `[password]`
