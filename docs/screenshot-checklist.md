# UI-VSB Screenshot Checklist & Capture Guide
**University of Ibadan — Maintenance Directorate Vehicle Service Booking Platform**
*Cross-referenced with `models/UI-VSB_WRITEUP.md` (Sections 9, 11, 12, and 13)*

---

## 1. General Screenshot Standards for the Write-up
- **Standard Width:** Set your browser window to **1280px** (or fullscreen 1080p desktop) at 100% zoom.
- **Visual Cropping:** Crop out extraneous OS taskbars and bookmark bars. Ensure the URL bar is visible *only* when demonstrating routing or access control (e.g., Figures 13.4).
- **Realistic Data:** Use the pre-seeded University of Ibadan data (`UI-MECH-01`, `OYO-784-UI`, Dr. Michael Adeyemi). Avoid placeholder texts like "test test" or "foo bar".
- **Color Scheme:** Ensure the primary navy (`#12304f`) and gold (`#c9a227`) visual hierarchy is crisp and clean.

---

## 2. Master Figure Directory & Capture Steps

### Section 9: Customer Interface Screenshots

| Figure | Caption | URL | Account | Data State / Instructions | What Examiner Looks For |
|---|---|---|---|---|---|
| **Figure 9.1** | Registration page | `http://localhost/ui-vsb/register.php` | Guest (Unauthenticated) | Trigger client validation by submitting an invalid phone number or short password (&lt; 8 chars), showing red inline error highlights. | Form labels, input grouping, live feedback, link back to login. |
| **Figure 9.2** | Login page | `http://localhost/ui-vsb/login.php` | Guest (Unauthenticated) | Default clean state. Notice the UI banner with University seal and the collapsible **Demo Accounts Quick-Fill** helper. | Polished branding, email and password inputs, role-switching helpers. |
| **Figure 9.3** | Customer dashboard | `http://localhost/ui-vsb/customer/dashboard.php` | `customer@ui.edu.ng` / `password123` | Active user session. Seed data provides Dr. Adeyemi with 2 registered vehicles and active booking metrics. | Stat cards (Registered Vehicles, Active Bookings, Completed Services), next appointment banner, quick action buttons. |
| **Figure 9.4** | Vehicle registration page with vehicle list | `http://localhost/ui-vsb/customer/vehicles.php` | `customer@ui.edu.ng` / `password123` | Shows list with `UI-MECH-01` (Toyota Hilux) and `OYO-784-UI` (Toyota Camry). Click "+ Add Vehicle" to show the modal with auto-uppercase license plate formatting. | Clean grid/table of vehicles, vehicle badges, "+ Add Vehicle" button and modal trigger. |
| **Figure 9.5** | Services page showing prices | `http://localhost/ui-vsb/customer/services.php` | `customer@ui.edu.ng` or Guest | Clean service catalog loaded from MySQL with formatted Naira (`₦`) currency. | 6 active workshop services (Oil Change, Brake Inspection, Engine Tune-up, AC, Wheel Alignment, Full Inspection), descriptions, prices, "Book This Service" CTA buttons. |
| **Figure 9.6** | Booking page with available slots loaded by Fetch | `http://localhost/ui-vsb/customer/book.php` | `customer@ui.edu.ng` / `password123` | Select vehicle and service, pick a date with booked slots (e.g. `2026-10-15` or `2026-10-20`). Shows 8 time slots: some "Available (2 left)", some "1 slot left", and at least one "Full (0 left)" marked disabled. | Live Fetch slot grid, slot badges with capacity counts, dynamic summary card updating in real time without page reload. |
| **Figure 9.7** | Payment page with test-mode banner | `http://localhost/ui-vsb/customer/payment.php?booking_id=1` | `customer@ui.edu.ng` / `password123` | Open payment page for an unpaid booking (or use `booking_id=1`). Notice the 30-minute hold countdown timer and the **Test Mode Banner** with test card simulation quick-fill buttons. | Amber test mode banner, 30-minute countdown timer, test card buttons (`4242` success, `0002` decline, `9995` insufficient), card number formatting. |
| **Figure 9.8** | Payment confirmation / receipt with booking reference | `http://localhost/ui-vsb/customer/confirmation.php?ref=VSB-20261010-A101` | `customer@ui.edu.ng` / `password123` | Valid reference `VSB-20261010-A101` (Toyota Hilux Routine Oil Change). | Green success badge, booking reference `VSB-YYYYMMDD-XXXX`, transaction reference `TXN-...`, payment card summary (`**** 4242`), Print Receipt button. |
| **Figure 9.9** | Vehicle tracking page with progress stepper | `http://localhost/ui-vsb/customer/track.php` | `customer@ui.edu.ng` / `password123` | Booking `VSB-20261010-A101` currently in `in_progress` or `ready_for_pickup`. | 5-step visual stepper (Not Started → Vehicle Received → In Progress → Service Completed → Ready for Pickup) with completed steps in emerald, active step pulsating, and timestamped audit logs. |

---

### Section 9 (Cont.): Administrator Interface Screenshots

| Figure | Caption | URL | Account | Data State / Instructions | What Examiner Looks For |
|---|---|---|---|---|---|
| **Figure 9.10** | Admin dashboard | `http://localhost/ui-vsb/admin/dashboard.php` | `admin@ui.edu.ng` / `admin123` | Authenticated as Administrator. Shows workshop KPIs and recent activity feed. | 4 KPI summary cards (Total Revenue, Pending Approvals, Bookings Today, Active Services), quick action links, Recent Bookings preview table. |
| **Figure 9.11** | Manage services (add/edit modal) | `http://localhost/ui-vsb/admin/services.php` | `admin@ui.edu.ng` / `admin123` | Click "Edit" on a service (e.g. Brake Pad Replacement) or "+ Add New Service" to display the modal overlay. | Master service table with status badges (Active/Inactive), modal overlay with Name, Description, Price (₦), Duration, and Active checkbox. |
| **Figure 9.12** | Bookings list with approve/reject and status update | `http://localhost/ui-vsb/admin/bookings.php` | `admin@ui.edu.ng` / `admin123` | Filter master bookings table by status (e.g. `pending_approval` or `approved`). Click "View Details" to show modal with Approve/Reject buttons and the 5-step forward status updater. | Actionable table with reference, vehicle, customer, date/slot, status badge, View Details modal showing transition buttons and refund notices. |
| **Figure 9.13** | Customers page | `http://localhost/ui-vsb/admin/customers.php` | `admin@ui.edu.ng` / `admin123` | Admin customers directory. Demonstrates anti-N+1 batch query showing nested vehicle lists under each customer. | Customer table showing Name, Email, Phone, Vehicle Count, Booking Count, and nested vehicle badges (e.g. `UI-MECH-01`, `OYO-784-UI`). |
| **Figure 9.14** | Payment records page | `http://localhost/ui-vsb/admin/payments.php` | `admin@ui.edu.ng` / `admin123` | Financial audit ledger. Shows successful payments, declined attempts, and refunded transactions. | Top financial summary cards (Total Collected Revenue, Success Count, Refund Count), tabular audit trail with TXN Ref, Amount, Card Last 4, Gateway Status. |
| **Figure 9.15** | Search service records | `http://localhost/ui-vsb/admin/records.php` | `admin@ui.edu.ng` / `admin123` | Enter "Toyota" or "UI-MECH-01" in the live search bar. Shows instant results via 300ms debounced Fetch. | Dynamic search input, responsive results table with Vehicle Plate, Model, Customer, Service Name, Completion Date, and Workshop Notes. |

---

### Section 9 (Cont.): Database & Storage Proof (MySQL / phpMyAdmin)

| Figure | Caption | Tool / Screen | Command / Query | What Examiner Looks For |
|---|---|---|---|---|
| **Figure 9.16** | List of tables in `ui_vsb_db` | phpMyAdmin or Terminal | `SHOW TABLES IN ui_vsb_db;` | The 6 core relational tables: `users`, `vehicles`, `services`, `bookings`, `payments`, `booking_history`. Proves clean normalization and foreign keys. |
| **Figure 9.17** | `bookings` table with real stored rows | phpMyAdmin or Terminal | `SELECT booking_id, booking_ref, user_id, vehicle_id, booking_date, booking_time, status, service_status FROM bookings LIMIT 5;` | Stored relational records with standard reference strings (`VSB-YYYYMMDD-XXXX`), foreign keys, and matching status enums. Proves persistent storage in MySQL. |
| **Figure 9.18** | `payments` table showing both success and failed rows | phpMyAdmin or Terminal | `SELECT payment_id, booking_id, transaction_ref, amount, status, card_last4, failure_reason FROM payments;` | Demonstrates that both `success` transactions and `failed` attempts (with `failure_reason = 'card_declined'`) are audited. Proves security rule: no full PAN or CVV stored. |

---

### Section 11: Fetch API Implementation Proof

| Figure | Caption | URL / Tool | Action | What Examiner Looks For |
|---|---|---|---|---|
| **Figure 11.2** | Browser DevTools → Network tab showing `slots.php` | Chrome / Edge DevTools on `http://localhost/ui-vsb/customer/book.php` | Open DevTools (`F12`) &rarr; **Network** tab &rarr; Filter `Fetch/XHR`. Select a date in the date picker. Click the `slots.php?date=...` request to show Request URL, HTTP 200, and the JSON response payload (`{"success":true,"data":{"date":"...","slots":[...]}}`). | Hard proof that time slot availability is queried asynchronously via vanilla `fetch()` without reloading the browser page. |

---

### Section 12: Simulated Payment Gateway Workflow Proof

| Figure | Caption | URL | Test Input | What Examiner Looks For |
|---|---|---|---|---|
| **Figure 12.2** | Failed payment message | `http://localhost/ui-vsb/customer/payment.php?booking_id=...` | Click "**Decline Test (0002)**" &rarr; Click "**Authorize Payment**". | Red error alert displaying: *"Payment declined: card_declined. Please check your card details or try another card."* The page remains on the payment screen allowing immediate retry. |
| **Figure 12.3** | Successful payment confirmation | `http://localhost/ui-vsb/customer/payment.php?booking_id=...` | Click "**Success Card (4242)**" &rarr; Click "**Authorize Payment**". | Browser transitions smoothly to `confirmation.php` showing the emerald checkmark, booking reference, receipt card, and print button. |

---

### Section 13: Formal Test Cases Verification Proof

| Figure | Test ID | Caption | URL / Action | Expected Result Shown in Screenshot |
|---|---|---|---|---|
| **Figure 13.1** | **T11** | Successful booking and booking reference | `http://localhost/ui-vsb/customer/book.php` | Complete booking form &rarr; Redirects to `customer/payment.php?booking_id=...` with booking reference clearly displayed in the summary pane. |
| **Figure 13.2** | **T12** | Slot-full error modal / notification | `http://localhost/ui-vsb/customer/book.php` | Attempt booking a slot with 0 remaining capacity or simulate race collision. Red error alert/toast: *"The selected time slot is now fully booked. Please choose another slot."* Slot list auto-refreshes. |
| **Figure 13.3** | **T14** | Declined payment | `http://localhost/ui-vsb/customer/payment.php` | Submit card ending in `0002`. Displays HTTP 422 error banner with specific decline message; booking remains unpaid. |
| **Figure 13.4** | **T22** | Access denied for customer on admin page | `http://localhost/ui-vsb/admin/dashboard.php` while logged in as `customer@ui.edu.ng` | Immediate redirect to `http://localhost/ui-vsb/customer/dashboard.php` (or login) with session safety; customer is strictly blocked from administrative endpoints. |
| **Figure 13.5** | **T05** | Failed login | `http://localhost/ui-vsb/login.php` | Enter `customer@ui.edu.ng` with an incorrect password (`wrongpass99`). Crimson error alert: *"Invalid email or password. Please verify your credentials."* Password field cleared. |

---

## 3. Quick 5-Minute Capture Sequence (Recommended Order)

1. **Section 9 & 13 Database Proofs First:**
   - Open MySQL client or phpMyAdmin:
     - Run `SHOW TABLES IN ui_vsb_db;` &rarr; Capture **Figure 9.16**.
     - Run `SELECT * FROM bookings LIMIT 5;` &rarr; Capture **Figure 9.17**.
     - Run `SELECT * FROM payments LIMIT 5;` &rarr; Capture **Figure 9.18**.
2. **Public & Authentication Screens:**
   - Go to `http://localhost/ui-vsb/login.php` &rarr; Capture **Figure 9.2**.
   - Enter wrong password &rarr; Capture **Figure 13.5** (Failed Login).
   - Go to `http://localhost/ui-vsb/register.php` &rarr; Submit invalid details &rarr; Capture **Figure 9.1**.
   - Go to `http://localhost/ui-vsb/customer/services.php` &rarr; Capture **Figure 9.5**.
3. **Customer Booking & DevTools Proof:**
   - Log in as `customer@ui.edu.ng` / `password123`.
   - On `customer/dashboard.php` &rarr; Capture **Figure 9.3**.
   - On `customer/vehicles.php` &rarr; Click "+ Add Vehicle" &rarr; Capture **Figure 9.4**.
   - On `customer/book.php` &rarr; Open DevTools Network tab (`F12`), pick date &rarr; Capture **Figure 11.2** (Network JSON payload).
   - Close DevTools &rarr; Capture **Figure 9.6** (Booking page with slots).
   - Click "Book Appointment" &rarr; Capture **Figure 13.1** (Booking reference created).
4. **Payment Gateway Demonstration:**
   - On `customer/payment.php` &rarr; Capture **Figure 9.7** (Test banner & 30-min hold timer).
   - Click "Decline Test (0002)" &rarr; Authorize &rarr; Capture **Figure 12.2** / **Figure 13.3** (Payment decline).
   - Click "Success Card (4242)" &rarr; Authorize &rarr; Capture **Figure 12.3** & **Figure 9.8** (`confirmation.php`).
   - Go to `customer/track.php` &rarr; Capture **Figure 9.9** (Visual stepper).
5. **Security & Admin Portal:**
   - While still logged in as customer, navigate to `http://localhost/ui-vsb/admin/dashboard.php` &rarr; Capture **Figure 13.4** (Access denied redirect).
   - Log out, log in as `admin@ui.edu.ng` / `admin123`.
   - On `admin/dashboard.php` &rarr; Capture **Figure 9.10**.
   - On `admin/services.php` &rarr; Click "Edit" on a service &rarr; Capture **Figure 9.11**.
   - On `admin/bookings.php` &rarr; Click "View Details" &rarr; Capture **Figure 9.12**.
   - On `admin/customers.php` &rarr; Capture **Figure 9.13**.
   - On `admin/payments.php` &rarr; Capture **Figure 9.14**.
   - On `admin/records.php` &rarr; Search "Toyota" &rarr; Capture **Figure 9.15**.
