# Frontend Walkthrough & Examiner Defense Guide
### UI-VSB: Vehicle Service Booking & Payment Platform
University of Ibadan Maintenance Directorate

---

## 1. Frontend Architecture & Page Directory

Strict adherence to the course constraints: **Vanilla HTML5, CSS3 with CSS variables, and vanilla ECMAScript**. Zero external JavaScript frameworks (no React, Vue, jQuery) and zero CSS frameworks (no Bootstrap, Tailwind).

### Public & Authentication Pages
| File | What it does | API Endpoints Called | Key JS / Architectural Idea |
|---|---|---|---|
| `index.php` | Landing page featuring a hero CTA, 4-step workflow, and live catalog preview. | `GET api/services.php` | Role-aware navigation; dynamic DOM hydration of featured services via asynchronous Fetch. |
| `login.php` | User authentication form with collapsible "Demo Accounts" quick-fill buttons. | `POST api/auth/login.php` | Intercepts submission, submits JSON payload via Fetch, manages button loading states, handles unified failure alert ("Invalid email or password"). |
| `register.php` | Customer registration form (name, email, phone, password, confirmation). | `POST api/auth/register.php` | Pre-submission client validation with `VSB.validate`; maps server 400/409 duplicate email errors to input elements. |
| `logout.php` | Terminates server-side PHP session and clears session cookie. | `POST api/auth/logout.php` | Secure session teardown followed by redirection to login screen. |

### Customer Portal Pages (`customer/`)
| File | What it does | API Endpoints Called | Key JS / Architectural Idea |
|---|---|---|---|
| `customer/dashboard.php` | Customer control center: KPI metrics, next appointment banner, mini-stepper, quick actions. | `GET api/dashboard.php`<br>`GET api/bookings.php` | Aggregates multiple API responses simultaneously using `Promise.all` for fast initial rendering. |
| `customer/vehicles.php` | Manage registered vehicles; add vehicle modal and deletion confirmation. | `GET api/vehicles.php`<br>`POST api/vehicles.php` | Modal-driven lifecycle; auto-uppercase transformation on plate inputs; handles 409 conflict when deleting booked vehicles. |
| `customer/services.php` | Full workshop maintenance catalog with pricing and duration. | `GET api/services.php` | Responsive card grid with `formatMoney` currency localization and direct booking shortcuts. |
| `customer/book.php` | **Course Showpiece**: 4-step appointment booking form with real-time bay availability. | `GET api/vehicles.php`<br>`GET api/services.php`<br>`GET api/slots.php`<br>`POST api/book.php` | Asynchronous slot checking without page reload; race-condition cancellation (`inFlightReqId`); live summary calculation; 409 slot-conflict auto-recovery. |
| `customer/payment.php` | 30-minute hold reservation checkout with simulated card gateway. | `GET api/bookings.php`<br>`POST api/payment.php` | Client-side **Luhn algorithm** verification; live 30-min countdown timer; test-card simulation pills; graceful HTTP 422 decline recovery. |
| `customer/confirmation.php` | Printable payment confirmation and appointment receipt. | `GET api/bookings.php?ref=` | Prominent copyable reference; print layout stylesheet (`@media print`); links directly to tracking. |
| `customer/track.php` | Real-time vehicle repair tracker with 5-step stepper and immutable audit trail. | `GET api/bookings.php`<br>`GET api/bookings.php?ref=` | Visual 5-step service stepper; interactive modal timeline; 15-second auto-polling to reflect technician bay progress. |

### Admin Portal Pages (`admin/`)
| File | What it does | API Endpoints Called | Key JS / Architectural Idea |
|---|---|---|---|
| `admin/dashboard.php` | Administrative workshop overview: revenue totals, pending approvals, bookings today. | `GET api/admin/dashboard.php` | High-level KPI computation and recent booking review with direct links. |
| `admin/services.php` | Manage workshop repair services; add/edit modal and soft-deactivation. | `GET api/admin/services.php`<br>`POST api/admin/services.php` | Differentiates true deletion from soft deactivation (`is_active = 0`) when past bookings exist. |
| `admin/bookings.php` | Master bookings control table: search, date/status filters, approval, rejection, stepper progression. | `GET api/admin/bookings.php`<br>`POST api/admin/bookings.php`<br>`POST api/admin/status.php` | In-place row updates without page reload; enforces strict forward-only service stepper; rejection triggers automated customer refund. |
| `admin/customers.php` | Searchable customer directory with aggregated vehicle & booking statistics. | `GET api/admin/customers.php` | Anti-N+1 frontend display: customer profile rows with nested vehicle tags and 300ms debounced live search. |
| `admin/payments.php` | Financial audit ledger displaying all successful, failed, and refunded transactions. | `GET api/admin/payments.php` | Filterable audit report showing transaction references, failure reasons, and total successful revenue. |
| `admin/records.php` | University fleet maintenance historical search engine. | `GET api/admin/records.php` | Multi-attribute filtering (query, service, workflow status) with live 300ms debounced input. |

---

## 2. "How to Explain the Fetch Slot Checking in 60 Seconds" (Demo Script)

> "In `customer/book.php`, our goal was to eliminate the clunky full-page reloads common in traditional web applications. We implemented asynchronous communication using JavaScript's native **Fetch API**.
>
> Whenever the customer picks a service or date, the `change` event listener fires and calls `checkSlots()`. This function executes an asynchronous HTTP GET request to `api/slots.php` with the selected date and service parameters.
>
> While the request is in flight, the DOM renders a loading spinner. When the JSON envelope arrives from the backend, our client code reads the slot capacity array and renders individual `<button>` elements for each workshop slot. Available slots show remaining bay capacity ('2 bays left' or '1 bay left'), while exhausted slots are disabled with 'Full'.
>
> To prevent race conditions if the user rapidly switches dates, we assign an incrementing `inFlightReqId` to each request and ignore any response that arrives out of sequence. Furthermore, if another customer books the last remaining bay at the exact same millisecond, the backend returns **HTTP 409 Conflict**, which our client catches to immediately display a user-friendly alert and re-fetch the slots automatically."

---

## 3. Likely Examiner Questions & Model Answers

1. **What is the Fetch API and how does it differ from older techniques like XMLHttpRequest?**
   * *Answer*: `fetch()` is modern JavaScript's native Promise-based interface for making asynchronous network requests. Unlike `XMLHttpRequest`, which required callback nesting and complex event listeners, Fetch provides a clean, chainable Promise interface that integrates cleanly with `async/await` syntax.

2. **Why does the slot checking operation not trigger a full page reload?**
   * *Answer*: Because `fetch()` operates asynchronously in the background. The JavaScript execution thread makes the HTTP request, receives the JSON data payload, and dynamically manipulates the DOM (`slotsContainer.innerHTML = ...`) without the browser unloading or refreshing the page document.

3. **Why do we use `textContent` instead of `innerHTML` for displaying user-submitted data?**
   * *Answer*: Directly assigning untrusted user input to `innerHTML` introduces Cross-Site Scripting (XSS) vulnerabilities, as malicious `<script>` tags or inline event handlers could execute in the browser. Using `textContent` (or our escaping helper `VSB.escapeHtml`) ensures all characters are treated strictly as string literals.

4. **How does the frontend client know the correct base URL when communicating with the backend?**
   * *Answer*: `includes/header.php` reads the PHP constant `BASE_URL` and embeds it into `window.APP_BASE`. In JavaScript, `VSB.url(path)` prepends this base path, ensuring that whether the app is running in a subfolder (`/ui-vsb/`) or root (`/`), all Fetch calls resolve to the correct endpoint.

5. **How does client-side validation interact with server-side validation?**
   * *Answer*: Client-side validation is purely for User Experience (UX)—it gives instant feedback before a network call. However, client-side checks can be bypassed, so the backend rigorously validates every parameter (Luhn check, integer constraints, role/ownership guards) on the server.
