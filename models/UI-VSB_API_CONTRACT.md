# ui-vsb — API Contract

> Single source of truth shared by the frontend and backend. Goes into the repo as `docs/API_CONTRACT.md`. If code and this contract disagree, the code is wrong unless a PR has changed the contract.

## 2.1 Base URL and conventions

- App root (XAMPP): `http://localhost/ui-vsb/`. Pages live under it, API under `/ui-vsb/api/`. **Never hard-code `/ui-vsb/`** in JS. PHP prints `window.APP_BASE` (e.g. `"/ui-vsb/"`) in the page `<head>` and JS builds URLs with it.
- Auth: **PHP session cookie** (`PHPSESSID`). Every `fetch` uses `credentials: 'same-origin'`.
- Request bodies for **POST** are **JSON** (`Content-Type: application/json`), read by PHP from `php://input`. GET uses query strings.
- There are **no** `PUT` or `DELETE` requests. Updates and deletes are `POST` with an `"action"` field.
- JSON keys: `snake_case`. Dates `YYYY-MM-DD`. Times `HH:MM` (24h). Timestamps `YYYY-MM-DD HH:MM:SS`. Money is a JSON **number** in naira (e.g. `45000`), formatted on the client as `₦45,000.00`.
- All responses are `Content-Type: application/json; charset=utf-8`.

## 2.2 Response envelope (every endpoint, success or failure)

```json
{ "success": true,  "message": "Human readable text", "data": { },  "errors": null }
{ "success": false, "message": "Validation failed",    "data": null, "errors": { "email": "Email is already registered" } }
```

- `data` is an object or array on success, `null` on failure (except payment declines, see 2.8).
- `errors` is `null` or an object `{ field_name: "message" }` for form-field errors.

## 2.3 HTTP status codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 400 | Bad request / validation failed (`errors` filled) |
| 401 | Not logged in |
| 403 | Logged in but wrong role / not your record |
| 404 | Not found |
| 405 | Wrong HTTP method |
| 409 | Conflict (duplicate, slot full, booking expired, vehicle in use) |
| 422 | Payment declined |
| 500 | Server error (never leak SQL/stack text; log it) |
| 501 | Not implemented yet (stub) |

The client must handle `401` globally: redirect to `login.php`.

## 2.4 Enums (exact strings)

| Name | Values |
|---|---|
| `role` | `customer`, `admin` |
| `booking_status` | `pending_payment`, `pending_approval`, `approved`, `rejected`, `cancelled` |
| `service_status` | `not_started`, `received`, `in_progress`, `completed`, `ready_for_pickup` |
| `payment.status` | `success`, `failed`, `refunded` |
| `failure reasons` | `card_declined`, `insufficient_funds`, `expired_card`, `invalid_card_number`, `invalid_cvv`, `invalid_expiry` |

Labels shown to users: Pending payment, Awaiting approval, Approved, Rejected, Cancelled; Not started, Received, In progress, Completed, Ready for pickup.

## 2.5 Business rules (both sides must agree)

- **Slots:** Monday to Saturday. Start times `08:00 09:00 10:00 11:00 12:00 13:00 14:00 15:00` (8 slots). Closed Sunday. **Capacity: 2 bookings per slot.** Booking window: today up to 60 days ahead. For today, slots whose start time has already passed are unavailable.
- A booking counts against capacity if `booking_status` is `pending_approval` or `approved`, **or** `pending_payment` and created less than **30 minutes** ago.
- A `pending_payment` booking older than 30 minutes is **expired**: it is set to `cancelled` the next time it is touched and cannot be paid.
- `amount` on a booking is a **snapshot** of the service price at booking time.
- Booking reference format: `VSB-YYYYMMDD-XXXX` (date of booking creation + 4 random uppercase letters/digits). Transaction reference: `TXN-YYYYMMDD-XXXXXX`.
- Lifecycle: `pending_payment` → (successful payment) → `pending_approval` → admin → `approved` or `rejected`. Rejecting a paid booking sets the payment to `refunded`.
- `service_status` starts `not_started`. Admin may change it only when `booking_status = approved`, and only **forward** in the enum order.
- A customer can see/pay/track **only their own** vehicles and bookings.
- Public registration always creates `role = customer`.
- Deleting a vehicle that has bookings → 409. Deleting a service that has bookings → it is **deactivated** (`is_active = 0`) and the response says so.
- Never store full card number or CVV. Only `card_last4`.

## 2.6 Validation rules (client mirrors server; server is the authority)

| Field | Rule |
|---|---|
| `full_name` | 2 to 100 chars |
| `email` | valid email, max 120, unique |
| `phone` | 10 to 15 digits (optional leading `+`) |
| `password` | min 8 chars, at least 1 letter and 1 digit |
| `reg_number` | 3 to 15 chars, letters/digits/space/dash, stored UPPERCASE, unique |
| `make`, `model` | 1 to 50 chars |
| `year` | integer from 1980 to (current year + 1) |
| service `name` | 2 to 100 chars; `price` > 0; `duration_mins` 15 to 480 |
| `date` | `YYYY-MM-DD`, not in the past, not a Sunday, within 60 days |
| `card_number` | 16 digits (spaces ignored), passes Luhn |
| `expiry` | `MM/YY`, not in the past |
| `cvv` | 3 digits |

## 2.7 Shared object shapes

**User** `{ "user_id": 3, "full_name": "Ada Obi", "email": "ada@ui.edu.ng", "phone": "08012345678", "role": "customer" }`

**Vehicle** `{ "vehicle_id": 1, "reg_number": "ABC-123-XY", "make": "Toyota", "model": "Corolla", "year": 2018 }`

**Service** `{ "service_id": 2, "name": "Oil Change", "description": "Engine oil and filter replacement", "price": 15000, "duration_mins": 45, "is_active": true }`

**Booking** (used by every bookings list; fields marked * only appear where noted)
```json
{
  "booking_id": 12,
  "booking_ref": "VSB-20261012-A4K9",
  "booking_date": "2026-10-14",
  "slot_time": "09:00",
  "amount": 15000,
  "booking_status": "pending_approval",
  "service_status": "not_started",
  "notes": null,
  "created_at": "2026-10-12 14:03:22",
  "vehicle":  { "vehicle_id": 1, "reg_number": "ABC-123-XY", "make": "Toyota", "model": "Corolla", "year": 2018 },
  "service":  { "service_id": 2, "name": "Oil Change", "price": 15000 },
  "customer": { "user_id": 3, "full_name": "Ada Obi", "email": "ada@ui.edu.ng", "phone": "08012345678" },
  "payment":  { "transaction_ref": "TXN-20261012-8F3K2A", "status": "success", "amount": 15000, "card_last4": "4242", "paid_at": "2026-10-12 14:05:01" },
  "history":  [ { "status": "pending_payment", "note": "Booking created", "changed_at": "2026-10-12 14:03:22" } ]
}
```
- `customer` appears **only in admin responses**.
- `payment` is `null` when no successful/refunded payment exists.
- `history` appears **only** in single-booking detail (`GET /api/bookings.php?ref=...`) and admin detail.

## 2.8 Endpoints

Legend: 🔓 public, 👤 logged-in customer, 🛠 admin. Every protected endpoint returns 401/403 per 2.3.

### Auth

**🔓 POST `api/auth/register.php`**
Request: `{ "full_name","email","phone","password","confirm_password" }`
201: `data: { "user": User }` (also logs the user in), `message: "Account created"`.
400: `errors` per field. 409: `errors.email = "Email is already registered"`.

**🔓 POST `api/auth/login.php`**
Request: `{ "email","password" }`
200: `data: { "user": User, "redirect": "customer/dashboard.php" | "admin/dashboard.php" }`
401: `message: "Invalid email or password"` (same message for unknown email and wrong password).

**👤🛠 POST `api/auth/logout.php`** → 200 `message: "Logged out"`.

**👤🛠 GET `api/auth/me.php`** → 200 `data: { "user": User }`, 401 if no session.

### Customer

**👤 GET `api/dashboard.php`**
200: `data: { "vehicle_count": 2, "active_bookings": 1, "latest_booking": Booking | null }`

**👤 GET `api/services.php`** (active services only)
200: `data: [ Service, ... ]`

**👤 GET `api/vehicles.php`** → 200 `data: [ Vehicle, ... ]`

**👤 POST `api/vehicles.php`**
- Add: `{ "action":"add", "reg_number","make","model","year" }` → 201 `data: { "vehicle": Vehicle }`. 409 `errors.reg_number = "Registration number already exists"`.
- Delete: `{ "action":"delete", "vehicle_id": 1 }` → 200. 403 if not yours. 409 if it has bookings.

**👤 GET `api/slots.php?date=2026-10-14&service_id=2`** ← **the required async Fetch operation**
200:
```json
{ "success": true, "message": "Slots loaded",
  "data": [ { "time":"08:00","available":true,"remaining":2 },
            { "time":"09:00","available":true,"remaining":1 },
            { "time":"10:00","available":false,"remaining":0 } ] }
```
- Sunday → 200 with `data: []` and `message: "The workshop is closed on Sundays"`.
- Past date / bad date → 400 with `errors.date`.

**👤 POST `api/book.php`**
Request: `{ "vehicle_id":1, "service_id":2, "date":"2026-10-14", "slot_time":"09:00" }`
201: `data: { "booking_id":12, "booking_ref":"VSB-20261012-A4K9", "amount":15000, "expires_at":"2026-10-12 14:33:22" }`, `message: "Booking created. Please complete payment within 30 minutes."`
400 validation; 403 vehicle not yours; 409 `message: "Sorry, that slot was just taken. Please choose another."`

**👤 POST `api/payment.php`**
Request: `{ "booking_id":12, "card_name":"ADA OBI", "card_number":"4242 4242 4242 4242", "expiry":"12/28", "cvv":"123" }`
- 200 success: `data: { "transaction_ref":"TXN-20261012-8F3K2A", "booking_ref":"VSB-20261012-A4K9", "amount":15000, "status":"success", "card_last4":"4242", "paid_at":"2026-10-12 14:05:01" }`
- 422 declined: `{ "success":false, "message":"Your card was declined", "data":{ "reason":"card_declined", "transaction_ref":"TXN-..." }, "errors":null }`
- 400 invalid format (Luhn, expiry, cvv): `errors` per field, **no** payment row written.
- 409 booking not payable (already paid / expired / not yours → 403).

**👤 GET `api/bookings.php`** → 200 `data: [ Booking, ... ]` newest first (own bookings, with `payment`, no `history`).
**👤 GET `api/bookings.php?ref=VSB-...`** → 200 `data: Booking` (with `history`). 404 if not found / not yours.

### Admin

**🛠 GET `api/admin/dashboard.php`**
200: `data: { "bookings_today": 4, "pending_approvals": 3, "total_revenue": 125000, "active_services": 5, "recent_bookings": [ Booking, ... up to 5 ] }`
(`total_revenue` = sum of payments with status `success`.)

**🛠 GET `api/admin/services.php`** → 200 `data: [ Service, ... ]` **including inactive**.
**🛠 POST `api/admin/services.php`**
- `{ "action":"create","name","description","price","duration_mins" }` → 201 `data:{service}`
- `{ "action":"update","service_id", ...same fields..., "is_active":true }` → 200
- `{ "action":"delete","service_id" }` → 200; `message` says "deleted" or "deactivated because it has bookings".

**🛠 GET `api/admin/bookings.php?q=&status=&date_from=&date_to=`**
`q` matches booking_ref, reg_number, customer name, customer email. `status` filters `booking_status`. All optional. 200 `data: [ Booking(with customer), ... ]`.
**🛠 GET `api/admin/bookings.php?ref=VSB-...`** → one Booking with `history`.
**🛠 POST `api/admin/bookings.php`** `{ "booking_id":12, "action":"approve" | "reject", "note":"optional" }` → 200 `data: Booking`. 409 if not `pending_approval`.

**🛠 POST `api/admin/status.php`** `{ "booking_id":12, "service_status":"in_progress", "note":"Engine removed" }` → 200 `data: Booking`. 409 if booking not approved or the move is backward.

**🛠 GET `api/admin/customers.php?q=`** → 200
`data: [ { "user_id":3,"full_name","email","phone","created_at","vehicle_count":2,"booking_count":5,"vehicles":[Vehicle,...] } ]`

**🛠 GET `api/admin/payments.php?status=&date_from=&date_to=`** → 200
`data: { "total_success": 125000, "payments": [ { "payment_id","transaction_ref","booking_ref","customer_name","amount","status","failure_reason","card_last4","paid_at" } ] }`

**🛠 GET `api/admin/records.php?q=&service_id=&date_from=&date_to=&service_status=`**
Search service records. `q` matches booking_ref, reg_number, make, model, customer name. 200 `data: [ Booking(with customer, payment), ... ]`.

## 2.9 Simulated payment rules

| Card (spaces ignored) | Result |
|---|---|
| `4242424242424242` | success |
| `4000000000000002` | 422 `card_declined` |
| `4000000000009995` | 422 `insufficient_funds` |
| any card with expired `expiry` | 422 `expired_card` (still logged as failed) *or* 400 `invalid_expiry` if the date format is bad |
| Luhn fails / wrong length | 400 `errors.card_number`, no payment row |
| CVV not 3 digits | 400 `errors.cvv`, no payment row |
| any other valid-Luhn card | success |

The gateway waits ~1 second to imitate latency. The payment page must show a "TEST MODE — no real money is charged" banner.

## 2.10 Seed accounts (created by `database/ui_vsb.sql`)

| Role | Email | Password |
|---|---|---|
| admin | `admin@ui.edu.ng` | `Admin@123` |
| customer | `customer@ui.edu.ng` | `Customer@123` |

Five seed services: Engine Servicing ₦45,000 (120 min), Oil Change ₦15,000 (45), Brake Inspection ₦10,000 (60), Tyre Replacement ₦30,000 (90), Vehicle Diagnostics ₦12,000 (60).
