# ui-vsb — Frontend Agent Prompts
## Vehicle Service Booking & Payment Platform (PHP + MySQL + vanilla JS)

Two developers, two AI agents, one GitHub repo named **ui-vsb**. This file is for the **FRONTEND developer**.

Files in the pack:

| File | Goes where | Used by |
|---|---|---|
| `UI-VSB_BACKEND_PROMPTS.md` | stays with the backend dev | Backend dev |
| `UI-VSB_FRONTEND_PROMPTS.md` | stays with the frontend dev | Frontend dev |
| `UI-VSB_API_CONTRACT.md` | repo as `docs/API_CONTRACT.md` | Both agents (single source of truth) |
| `UI-VSB_CLAUDE.md` | repo root as `CLAUDE.md` (and `AGENTS.md`) | Both agents (auto-read rules) |
| `UI-VSB_SYSTEM_DESIGN.md` | repo as `docs/SYSTEM_DESIGN.md` | Both agents |
| `UI-VSB_WRITEUP.md` | not in repo (report draft) | Whoever writes the report |

> **Contract note.** The API contract refines the API list in the System Design file (login/register/me/services/dashboard endpoints were added; writes use `POST` + `action` instead of `PUT`/`DELETE`; `booking_status.php` became `bookings.php`). **Where they differ, the contract wins.**

---

# 0. How to use this pack

## 0.1 The order of work

```
HUMAN (backend dev)  → creates GitHub repo, invites frontend dev (the backend prompts file, "Before B0")
BACKEND AGENT: B0    → scaffolds repo, docs, stubs, pushes to GitHub        (≈ 30–45 min)
FRONTEND DEV         → clones repo, runs F0 (orientation only, no code)
BACKEND B1 ║ FRONTEND F1 → (parallel) core backend / design system
BACKEND B2 ║ FRONTEND F2 → vehicles+services / auth pages            ← SYNC 1: login works end-to-end
BACKEND B3 ║ FRONTEND F3, F4 → booking+slots / customer pages+booking ← SYNC 2: Fetch slot check works
BACKEND B4 ║ FRONTEND F5 → payment / payment+confirmation+tracking   ← SYNC 3: pay success+fail works
BACKEND B5 ║ FRONTEND F6 → admin API / admin pages                   ← SYNC 4: admin approves, customer sees it
BOTH: B6 + F7        → hardening, polish, demo script, screenshots   ← SYNC 5: final demo rehearsal
```

## 0.2 Why they won't block each other

- Everything the frontend needs from the backend is already defined in `docs/API_CONTRACT.md`, with exact URLs, field names, and sample JSON.
- B0 creates **stub endpoints** for every route, returning HTTP 501 with the correct JSON envelope, so nothing 404s while real code lands.
- The frontend builds against **mock data** (`assets/js/mocks.js`) behind a single switch (`USE_MOCK` in `assets/js/config.js`). Flip it to `false` when the real endpoint is merged.
- Folders are **owned by one dev** (Section 1), so Git conflicts are rare.

## 0.3 How a dev should drive their agent

1. Open the repo folder in your agent (Claude Code, Cursor, Copilot agent, etc.).
2. Paste **one phase prompt at a time**. Never paste all phases at once.
3. When the agent says it is done, **run the checks listed under "You verify"** yourself. Don't trust "done".
4. Read the agent's `docs/walkthrough-*.md` update. If you can't explain a file, ask the agent to explain it (Section 4.2). **Every member must explain their part in the demo.**
5. Commit, push, open a PR to `develop`, let the other dev review, merge.
6. Only then start the next phase.

---

# 1. Ownership and Git rules

## 1.1 Folder ownership

| Path | Owner | Notes |
|---|---|---|
| `config/`, `src/`, `api/`, `database/`, `tests/`, `includes/bootstrap.php` | **Backend** | Frontend never edits these. If you need a change, ask the backend dev (or change `docs/API_CONTRACT.md` via PR with both approving). |
| `index.php`, `register.php`, `login.php`, `logout.php`, `customer/`, `admin/`, `assets/`, `includes/header.php`, `includes/footer.php` | **Frontend** | Backend only touches `logout.php` if needed. |
| `docs/API_CONTRACT.md`, `CLAUDE.md` | **Shared** | Change only by PR approved by both. |
| `docs/walkthrough-backend.md` | Backend | |
| `docs/walkthrough-frontend.md` | Frontend | |
| `README.md` | Backend writes it, Frontend reviews | |

## 1.2 Branches

```
main      → always demo-able. Only merged from develop at sync points.
develop   → integration branch. Both devs merge PRs here.
be/<topic> → backend feature branches   e.g. be/auth, be/booking-slots
fe/<topic> → frontend feature branches  e.g. fe/design-system, fe/booking-page
```

- Branch from `develop`: `git checkout develop && git pull && git checkout -b be/auth`
- Small commits: `feat(auth): add login endpoint`, `fix(slots): exclude expired holds`, `style(ui): badge colours`
- PR into `develop`, **the other dev reviews**, squash-merge.
- `git pull origin develop` into your branch **before** you start every phase.
- Never commit `config/config.local.php`, passwords, or `.env`.

## 1.3 Sync meeting (10 minutes) at each SYNC point

1. Backend merges and tells frontend which endpoints are now real.
2. Frontend flips `USE_MOCK` for those endpoints and tests in the browser.
3. Any contract mismatch → fix the **contract first**, then both code.

---

# 2. FRONTEND PROMPTS

## Before F0 — human steps (frontend dev)

1. Wait for the backend dev to push B0, then:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/<owner>/ui-vsb.git ui-vsb
   cd ui-vsb
   git checkout develop
   git checkout -b fe/design-system
   ```
   The folder **must** be named `ui-vsb` so `http://localhost/ui-vsb/` works.
2. Start Apache + MySQL in XAMPP. Import `database/ui_vsb.sql` when B1 lands (until then you use mocks).
3. Copy `config/config.local.example.php` to `config/config.local.php` and set DB credentials (only matters once real endpoints exist).
4. Open the folder in your AI agent and paste F0.

**Before B1 lands**, you can view logged-in pages by setting `DEV_BYPASS_AUTH => true` in your local `config.local.php` and opening `http://localhost/ui-vsb/customer/dashboard.php?as=customer` (or `admin/dashboard.php?as=admin`). Never commit that setting.

## F0 — Orientation (read-only, no code)  *(frontend)*

````text
You are the FRONTEND agent for "ui-vsb", a PHP + MySQL + vanilla JS university group project. Another developer and agent own the backend. This phase is orientation ONLY: do not create or edit any files.

Read: CLAUDE.md, docs/API_CONTRACT.md (entire), docs/SYSTEM_DESIGN.md (sections 7 and 9), and look at the existing folder structure and the placeholder files in customer/, admin/, includes/ and assets/.

Then reply with:
1. A list of every page I will build (path + one-line purpose + which API endpoints it calls).
2. The list of files I own and the files I must NOT edit.
3. How the frontend knows the base URL (APP_BASE), how auth/401 should be handled, and how USE_MOCK works.
4. Any ambiguity, contradiction or missing endpoint/field you notice in the contract that would block UI work (be specific; do not invent answers).
5. The component list you think the design system needs.
Keep it under 60 lines. Wait for my go-ahead.
````

## F1 — Design system, layout, JS foundations  *(frontend)*

````text
Phase F1 (FRONTEND). Read CLAUDE.md and docs/API_CONTRACT.md sections 2.1–2.4. Plan first (files + bullets) and wait for my ok.

GOAL: a small, clean, hand-written design system and the JS foundations every page will use. No CSS frameworks, no JS frameworks, no external CDNs (the demo may be offline); use the system font stack.

1. assets/css/style.css — CSS variables on :root:
   --navy:#0f2a4a; --navy-700:#16365e; --blue:#2563eb; --orange:#f59e0b (accent); --bg:#f4f6fa; --card:#ffffff; --text:#1f2937; --muted:#6b7280; --border:#e5e7eb; --success:#16a34a; --danger:#dc2626; --warning:#d97706; --info:#2563eb; radius 10px; soft shadow; spacing scale 4/8/12/16/24/32. Mobile-first, breakpoint at 768px.
   Components with clear class names: .container, .navbar (+ hamburger on mobile), .card, .grid (responsive 1/2/3/4 cols), .btn (.btn-primary .btn-secondary .btn-danger .btn-sm, disabled + loading state), form elements (.form-group, label, input, select, textarea, .field-error text, .is-invalid), .table (responsive wrapper, hover rows), .badge variants for statuses (pending=amber, approved/success/completed=green, rejected/failed/cancelled=red, in_progress/received=blue, not_started=grey, ready_for_pickup=teal), .toast (top-right, auto-dismiss), .modal (overlay + dialog, ESC to close), .spinner, .empty-state, .stepper (5-step horizontal progress for service_status; vertical on mobile), .timeline (history list), .stat-card, .slot-grid and .slot-btn (available / selected / full disabled), .banner-test-mode (orange stripe "TEST MODE — no real money is charged"), .alert (success/error/info).
   Accessible: visible focus rings, sufficient contrast, labels tied to inputs, buttons are <button>.
2. includes/header.php and includes/footer.php — I own these now. Header prints `window.APP_BASE`, loads config.js, app.js (defer) and a per-page extra script (via a $pageScripts array set before including the header). Navbar is role-aware using the PHP session (not JS): guest = Home/Login/Register; customer = Dashboard/My Vehicles/Services/Book/My Bookings/Logout; admin = Dashboard/Services/Bookings/Customers/Payments/Records/Logout. Highlight the active page. Logout is a button that calls the logout endpoint via fetch then redirects. Keep `$pageTitle` support. Keep the existing bootstrap include working.
3. assets/js/config.js — `window.VSB_CONFIG = { USE_MOCK: true, MOCK_DELAY_MS: 400 }`.
4. assets/js/app.js — plain functions on a single global namespace `VSB` (no modules, no build):
   - VSB.url(path) -> APP_BASE + path
   - VSB.api(path, {method, body, query}) -> returns the parsed envelope plus {ok,status}; sends JSON bodies, credentials same-origin; on network failure returns {success:false,message:'Network error...'}; on HTTP 401 redirects to login.php; when VSB_CONFIG.USE_MOCK is true it resolves from VSB.mocks (see 5) after MOCK_DELAY_MS instead of calling fetch. A per-endpoint override map `VSB_CONFIG.REAL = { 'api/auth/login.php': true, ... }` lets the dev switch individual endpoints to the real backend while the rest stay mocked.
   - VSB.toast(message,type), VSB.showFieldErrors(form, errors) / VSB.clearErrors(form), VSB.setLoading(button,bool)
   - VSB.formatMoney(n) -> "₦45,000.00", VSB.formatDate('2026-10-14') -> "Wed 14 Oct 2026", VSB.formatTime('09:00') -> "9:00 AM", VSB.statusBadge(status) -> returns a DOM element (uses textContent), VSB.el(tag, attrs, children) tiny DOM builder, VSB.escapeHtml.
   - Validators mirroring contract 2.6: VSB.validate.email/phone/password/regNumber/year/luhn/expiry/cvv returning error string or ''.
5. assets/js/mocks.js — `VSB.mocks` implementing every endpoint in the contract with realistic in-memory data matching the contract shapes EXACTLY (5 seed services, 2 vehicles, ~6 bookings across statuses, payments, customers) and simple stateful behaviour (adding a vehicle adds to the list; booking returns a ref; payment 4242 succeeds, 0002 returns 422 with reason; admin approve changes status). Include both success and failure responses so every UI state can be built before the backend exists.
6. Create a temporary style guide page `customer/_styleguide.php` (guarded for dev only, delete before final) that renders every component so I can review it visually.

Do not build real feature pages yet. Test by opening the style guide. Tell me the URL to open and what to look at. Update docs/walkthrough-frontend.md.
````

**You verify (F1):** open the style guide at desktop and phone width (browser dev tools). Everything legible, no horizontal scroll, buttons have loading state, toasts work.

## F2 — Landing, register, login  *(frontend)*

````text
Phase F2 (FRONTEND). Read CLAUDE.md, docs/API_CONTRACT.md (Auth endpoints, validation 2.6, envelope 2.2/2.3). Plan first, then wait for my ok.

Build:
1. index.php — landing page: hero (title "Book your vehicle service online", subtitle for UI staff, CTA buttons Register / Login), "How it works" in 4 steps (Register vehicle → Pick service & slot → Pay → Track progress), a services preview strip (static text is fine, it must not need login), footer. If the user is already logged in, the CTA goes to their dashboard.
2. register.php + assets/js/auth.js — form: full name, email, phone, password, confirm password; show/hide password toggle; live client validation using VSB.validate; on submit POST api/auth/register.php; show field errors from `errors` on 400/409; on success toast and go to customer/dashboard.php.
3. login.php — email + password; POST api/auth/login.php; on 401 show an inline alert "Invalid email or password" (not just a toast); on success follow `data.redirect`. Show the two seed accounts in a small collapsible "Demo accounts" box (dev convenience; mark it with a class so we can remove it for the final submission).
4. Loading states on submit buttons, prevent double-submit, Enter key works, autofocus first field, proper input types/autocomplete attributes.
5. Add USE_MOCK/REAL wiring: while USE_MOCK is true, these pages work against mocks; when the backend dev says auth is real, set VSB_CONFIG.REAL for the four auth endpoints.

Checks you must do and report: register invalid -> every error visible; duplicate email (mock + real) shows email error; wrong password shows alert; success redirects; mobile layout fine. Update docs/walkthrough-frontend.md.
````

**You verify (F2):** switch the four auth endpoints to REAL when B1 is merged, and log in with both seed accounts. Wrong password must show the red alert (this is a demo failure case).

## F3 — Customer dashboard, vehicles, services  *(frontend)*

````text
Phase F3 (FRONTEND). Read CLAUDE.md and docs/API_CONTRACT.md (dashboard, services, vehicles, shapes 2.7). Plan first, then wait.

Build (all data loaded with fetch via VSB.api, never inline PHP data):
1. customer/dashboard.php — greeting from /api/auth/me.php, three stat cards (vehicles, active bookings, next/latest booking), latest booking card with badge + mini stepper, quick-action buttons (Add vehicle, Book a service, Track). Skeleton/spinner while loading; friendly empty state if nothing yet.
2. customer/vehicles.php + customer/vehicles.js (put it in assets/js/vehicles.js) — add-vehicle form (reg number auto-uppercase, make, model, year select 1980..current+1) + table/cards of my vehicles with Delete (confirm modal). Handle 409 duplicate plate (field error) and 409 vehicle-has-bookings (toast). Update the list without a page reload. Empty state with an arrow pointing to the form.
3. customer/services.php + assets/js/services.js — grid of service cards (name, description, price formatted ₦, duration "45 min", "Book now" button linking to customer/book.php?service_id=ID). Loading + error + empty states.
4. Every page: handle fetch failure with a retry button; never use innerHTML with server data; keep JS in assets/js files, not inline.

Test with mocks, then with REAL endpoints once the backend dev confirms B2 is merged. Report what you tested including the error paths. Update docs/walkthrough-frontend.md.
````

## F4 — Booking page with Fetch slot checking  *(frontend)* — the showpiece

````text
Phase F4 (FRONTEND). Read CLAUDE.md and docs/API_CONTRACT.md (slots, book, rules 2.5, validation 2.6). Plan first, then wait.

Build customer/book.php + assets/js/book.js. This is the page that proves "asynchronous communication using the Fetch API", so make it solid and make the async behaviour obvious.

Layout (stacked on mobile, 2 columns on desktop — form left, live summary right):
1. Step 1 Vehicle: select from my vehicles (GET api/vehicles.php). If none: message + button to add a vehicle.
2. Step 2 Service: select (GET api/services.php); preselect from ?service_id=. Show price and duration immediately under the select.
3. Step 3 Date: <input type=date> with min=today and max=today+60 days; Sundays show a hint "Workshop closed on Sundays" (and the server also rejects them).
4. Step 4 Slots: whenever service OR date changes (and both are set) call GET api/slots.php?date=&service_id= with fetch WITHOUT reloading the page. Show a spinner "Checking availability…", then render a .slot-grid of buttons: available slots show time (9:00 AM) and "2 left"/"1 left"; full slots are disabled with "Full". Selecting a slot highlights it and stores slot_time. If data is empty with a message (Sunday) show that message. Guard against race conditions: ignore stale responses if the user changed the date again while a request was in flight (use a request counter or AbortController). Debounce is not needed, but cancel the previous request.
5. Summary panel (live): vehicle, service, date, time, amount; "Confirm booking" button disabled until all four are chosen.
6. Submit: POST api/book.php {vehicle_id, service_id, date, slot_time}; loading state; on 201 redirect to customer/payment.php?booking_id=ID; on 409 show an error alert "That slot was just taken" and automatically re-fetch the slots; on 400 show field errors; on 403/other show toast.
7. Add a small "Last checked at HH:MM:SS" line under the slot grid and a Refresh slots link — useful for the live demo.
8. Everything keyboard accessible; slot buttons are real <button>s with aria-pressed.

Use mocks first (make the mock return one full slot and make book return 409 for 12:00 so the failure path can be seen), then switch slots/book to REAL once the backend dev confirms B3 is merged. Then do a real test of the double-booking failure with two browsers/accounts. Update docs/walkthrough-frontend.md including a short explanation of how fetch, async/await and the DOM update work in this page (the grader may ask).
````

**You verify (F4):** open DevTools → Network while changing the date: you see the `slots.php` request and JSON, and the page does not reload. Fill a slot to capacity from another account and watch it flip to "Full".

## F5 — Payment, confirmation, tracking  *(frontend)*

````text
Phase F5 (FRONTEND). Read CLAUDE.md and docs/API_CONTRACT.md (payment, bookings, enums, simulated rules 2.9, Booking shape). Plan first, then wait.

Build:
1. customer/payment.php + assets/js/payment.js
   - Reads booking_id from the query string, loads GET api/bookings.php (find the booking in the list, or the contract's ?ref= route if you have the ref) and shows an order summary (ref, service, vehicle, date/time, amount). If the booking is not pending_payment show a message + link to My Bookings.
   - A visible .banner-test-mode, and a collapsible "Test cards" help box listing 4242 4242 4242 4242 (success), 4000 0000 0000 0002 (declined), 4000 0000 0000 9995 (insufficient funds).
   - Card form: name on card, card number (auto-format groups of 4, digits only, card-type hint optional), expiry (auto-insert slash, MM/YY), CVV (3 digits, type=password). Client validation using VSB.validate incl. Luhn.
   - 30-minute hold: show a countdown computed from created_at (+30 min) returned by the booking (or from `expires_at` kept in sessionStorage from the book step). When it reaches 0, disable the form and show "Booking expired — please book again".
   - Submit: POST api/payment.php; button loading + overlay text "Contacting payment service…" (the backend waits ~1s); on 200 -> redirect to customer/confirmation.php?ref=BOOKING_REF; on 422 -> red alert with human text for each reason (card_declined: "Your card was declined", insufficient_funds: "Insufficient funds", expired_card: "Card has expired") and keep the form so the user can retry; on 400 -> field errors; on 409 -> alert + link to My Bookings.
   - Never log or store card data in JS (no console.log of the form values, no localStorage).
2. customer/confirmation.php + assets/js/confirmation.js — loads GET api/bookings.php?ref= ; big success tick, booking reference in a prominent copyable box (copy button), transaction ref, amount paid, card last 4, date/time, service/vehicle, next-step text ("Your booking is awaiting approval"). Buttons: Print receipt (window.print with a clean @media print style), Track my booking, Back to dashboard.
3. customer/track.php + assets/js/track.js — list of my bookings (cards or table) with badges for booking_status; a status filter; click a booking to expand/open a detail panel or modal that shows: the 5-step .stepper for service_status (only meaningful when approved; otherwise show the booking_status message such as "Waiting for payment" with a Pay now button, "Awaiting approval", "Rejected — payment refunded"), the history .timeline from GET api/bookings.php?ref=, and payment info. Auto-refresh the open detail every 15 s (clear the timer when closed). Pay-now button for pending_payment bookings goes to payment.php.
4. Keep all error/empty/loading states.

Use mocks first (include a declined and a success path), then REAL when the backend dev says B4 is merged. Test success AND failure; screenshot-worthy states: declined alert, success confirmation, stepper at "In progress". Update docs/walkthrough-frontend.md.
````

## F6 — Admin pages  *(frontend)*

````text
Phase F6 (FRONTEND). Read CLAUDE.md and docs/API_CONTRACT.md (all Admin endpoints, shapes, lifecycle rules 2.5). Plan first, then wait. Admin pages must be guarded for role admin (Auth::requireRole('admin') at the top of each page, already in the placeholders — keep it).

Use a consistent admin layout: page title, filter bar, data table (responsive: becomes stacked cards on mobile), actions column. Reuse existing components; add new CSS only in style.css under an "Admin" section.

1. admin/dashboard.php + assets/js/admin.js (shared admin helpers; page-specific code may live in admin-<page>.js files if admin.js gets long) — four .stat-cards (bookings today, pending approvals, total revenue in ₦, active services) + "Recent bookings" table. Pending approvals card links to admin/bookings.php?status=pending_approval.
2. admin/services.php — table of ALL services (inactive shown greyed with an "Inactive" badge); "Add service" and Edit open a modal form (name, description, price, duration, active toggle); Delete uses a confirm modal and displays the server message ("deleted" vs "deactivated because it has bookings"). Inline validation per contract.
3. admin/bookings.php — filter bar: search box (q), status select, date_from, date_to, Clear; table: ref, customer, vehicle (reg + make/model), service, date/time, amount, payment badge, booking_status badge, service_status. Row actions: View (modal with full detail + history timeline via ?ref=), Approve / Reject (only on pending_approval; confirm modal with optional note), and for approved bookings a service-status <select> limited to the allowed forward steps + optional note + "Update" button. Update the row in place from the returned Booking without a full reload. Show server 409 messages in a toast.
4. admin/customers.php — search by name/email; rows with name, email, phone, vehicle_count, booking_count; expanding a row shows the customer's vehicles.
5. admin/payments.php — status filter + date range; table of payments with status badges (success/failed/refunded) and failure reason; a summary tile "Total successful payments".
6. admin/records.php — "Search service records": search box (reg number, make, model, customer, ref), service select, service_status select, date range; results table; result count; empty state "No records match your search"; debounce the search input (300 ms) and cancel stale requests.
7. All filters sync to the URL query string so a filtered view can be reloaded/shared (nice to have, implement if time allows).

Use mocks, then switch to REAL after the backend dev confirms B5. Test: approve, reject, status forward, status backward blocked, search no-results, customer visiting an admin URL being redirected. Update docs/walkthrough-frontend.md.
````

## F7 — Polish, responsive, accessibility, demo rehearsal  *(frontend)*

````text
Phase F7 (FRONTEND). Read CLAUDE.md and docs/SYSTEM_DESIGN.md section 9 (demo script). Plan first, then wait.

1. Set VSB_CONFIG.USE_MOCK=false and clear REAL overrides. Remove customer/_styleguide.php and the "Demo accounts" box on the login page (or hide it behind APP_ENV==='dev'). Remove every console.log and any leftover TODO.
2. Walk through EVERY page at 360px, 768px and 1280px widths: no horizontal scroll, tap targets at least 40px, tables readable, modals fit the screen.
3. Consistency pass: same spacing, headings, button styles, empty/loading/error states on every page; page <title> set; favicon (simple inline SVG data URI or file in assets/img); consistent currency/date/time formatting; no raw enum strings shown to users (always labels).
4. Accessibility pass: labels, alt text, focus order, ESC closes modals, aria-live on toasts and alerts, colour not the only status indicator (badges have text).
5. Security pass: grep assets/js for innerHTML/outerHTML/insertAdjacentHTML/document.write and make sure no server-supplied value reaches them unescaped.
6. Run the full demo script in docs/SYSTEM_DESIGN.md section 9 against the REAL backend (use the browser; I will do the final run too) and report PASS/FAIL per step with details for any failure (do not edit backend files; report to me).
7. Produce docs/screenshot-checklist.md: the exact list of screens/states to capture for the report (matching Figures 9.x, 11.2, 12.2, 12.3, 13.x in UI-VSB_WRITEUP.md), the URL, the account to use, and the data state needed for each.
8. Finish docs/walkthrough-frontend.md: for each page, 2–4 sentences of what it does, which API it calls and the key JS idea; a 60-second script for explaining the Fetch slot checking; likely examiner questions with short answers (what is fetch, async/await vs promises, why no page reload, how are errors handled, why textContent instead of innerHTML, how does the page know the API URL).
````

---

# 3. Integration checkpoints and final checklist

## 3.1 Sync tests (do these together, 10–15 min each)

| Sync | After | Test together |
|---|---|---|
| **1** | B2 + F2/F3 | Register → login → add vehicle → list services in the **real** browser against the **real** API. Wrong password shows alert. |
| **2** | B3 + F4 | Book a slot. Open DevTools Network to see `slots.php`. Fill a slot from account 2; account 1 sees "Full" and gets a 409 message on submit. |
| **3** | B4 + F5 | Pay with `4000 0000 0000 0002` (declined, retry) then `4242 4242 4242 4242` (success). Check the `payments` table shows both. |
| **4** | B5 + F6 | Admin approves; customer's track page shows Approved; admin sets In progress → Completed; stepper updates. Admin rejects another → refunded. |
| **5** | B6 + F7 | Fresh DB import → full demo script (System Design §9) → screenshots per `docs/screenshot-checklist.md`. |

## 3.2 Contract mismatch protocol

1. Don't "fix it on your side." Stop.
2. Edit `docs/API_CONTRACT.md` in a PR labelled `contract`, both devs approve.
3. Both agents get the prompt in Section 4.6 so they re-read the contract.

## 3.3 Final submission checklist

- [ ] `develop` merged to `main`; the repo tag `v1.0` created
- [ ] Fresh clone + import `database/ui_vsb.sql` + copy `config.local.php` → runs with **no edits**
- [ ] `DEV_BYPASS_AUTH` is `false`; no styleguide page; no demo-accounts box (or dev-only)
- [ ] Course checklist: HTML/CSS/JS/PHP/MySQL used; OOP classes; Fetch used for slots + booking + payment; API endpoints exist; data stored in MySQL; payment simulated; success and failure demos done; SQL file included
- [ ] Both `walkthrough-*.md` read by their owners; each member can explain their part
- [ ] Screenshots captured for the write-up (Figures 9.x to 13.x)
- [ ] Zip of the project + `database/ui_vsb.sql` ready (exclude `.git`, `config.local.php`)
- [ ] Demo rehearsed once end-to-end on the machine that will present

---

# 4. Utility prompts (either dev)

## 4.1 Review my code (peer review before a PR)

````text
Act as a strict code reviewer for this PHP/JS university project. Review ONLY my changes on this branch (git diff develop...HEAD). Check: (1) does it follow docs/API_CONTRACT.md exactly (URLs, fields, status codes, enums)? (2) security: SQL injection, XSS, missing role/ownership checks, leaked errors, card data handling; (3) readability: would a second-year student be able to explain it? (4) bugs and edge cases; (5) anything touching files owned by the other developer (see CLAUDE.md). Output a table: severity (blocker/major/minor), file:line, problem, suggested fix. Do not change any files.
````

## 4.2 Explain this so I can present it

````text
I must explain this part of the system to my lecturer. Explain <file or feature> to me as if I'm a second-year CS student: what it does, step-by-step what happens at runtime (give an example request/response), why it is written this way, what OOP/Fetch/SQL concepts it demonstrates, and 5 questions an examiner might ask with short model answers. Then ask me 3 questions to check I understood. Do not change any files.
````

## 4.3 Debug

````text
Bug: <what you did, what you expected, what happened>. Evidence: <error message / network response / screenshot text>. First, diagnose: list the 3 most likely causes ranked, and the exact check (command, query or console line) that would confirm each. Wait for me to run the checks before changing code. When we find the cause, make the smallest possible fix and tell me how to verify it.
````

## 4.4 "Check this against the contract"

````text
Compare <endpoint or page> with docs/API_CONTRACT.md. List every difference in: URL, method, request fields, response fields/types, status codes, error messages, enums, validation rules. Output a table of mismatches only. Do not fix anything yet.
````

## 4.5 Prepare a PR description

````text
Write a pull-request description for my branch vs develop: what changed (bullets), which contract endpoints/pages are affected, how to test (exact steps and accounts), screenshots/outputs I should attach, and anything the reviewer should look at closely. Keep it under 25 lines.
````

## 4.6 Re-sync after the contract changed

````text
docs/API_CONTRACT.md changed. Run `git diff <old-commit> HEAD -- docs/API_CONTRACT.md` (or read the PR). List what changed, then list every file in MY area (see CLAUDE.md ownership) that must change as a result. Propose the changes, wait for my ok, then apply them and update my walkthrough doc.
````

## 4.7 Fresh-clone smoke test (final day)

````text
Simulate a new teammate setting up from scratch. Follow README.md literally, step by step, in a fresh clone, and report every place the instructions are wrong, incomplete or ambiguous. Then drop the database, re-import database/ui_vsb.sql, and run tests/run_all and the demo script (docs/SYSTEM_DESIGN.md section 9). Report PASS/FAIL per step. Fix README problems only; report anything else.
````
