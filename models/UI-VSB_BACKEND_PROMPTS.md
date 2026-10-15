# ui-vsb — Backend Agent Prompts
## Vehicle Service Booking & Payment Platform (PHP + MySQL + vanilla JS)

Two developers, two AI agents, one GitHub repo named **ui-vsb**. This file is for the **BACKEND developer**.

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
HUMAN (backend dev)  → creates GitHub repo, invites frontend dev (Section 2, "Before B0")
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

# 2. BACKEND PROMPTS

## Before B0 — human steps (backend dev, ~10 min)

1. Create an **empty private** GitHub repo, e.g. `ui-vsb` (no README/.gitignore, the agent will add them).
2. Settings → Collaborators → invite the frontend dev (write access).
3. Make sure XAMPP is installed, Apache + MySQL running, and the project folder will be `C:\xampp\htdocs\ui-vsb` (Windows) or `/opt/lampp/htdocs/ui-vsb` (Linux). Clone the empty repo **as** `ui-vsb` so the URL is `http://localhost/ui-vsb/`:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/<you>/ui-vsb.git ui-vsb
   cd ui-vsb
   ```
4. Copy these three files into the repo folder root (the agent moves them in B0): `UI-VSB_SYSTEM_DESIGN.md`, `UI-VSB_API_CONTRACT.md`, `UI-VSB_CLAUDE.md`.
5. Open the `ui-vsb` folder in your AI agent and paste prompt B0.

## B0 — Repo setup, scaffolding, stubs  *(backend dev)*

````text
You are the BACKEND agent for "ui-vsb", a PHP + MySQL university group project. This repo (empty apart from three markdown files in the root) will be shared by two developers, each with their own AI agent. Your job in this phase is ONLY to create a clean foundation so both can work in parallel without conflicts. Do not implement real business logic yet.

STEP 1 — Read before doing anything
- Read UI-VSB_SYSTEM_DESIGN.md fully.
- Read UI-VSB_API_CONTRACT.md and UI-VSB_CLAUDE.md fully.
Summarise back to me in under 15 bullets: the stack, the folder ownership, the endpoint list, and the booking/payment lifecycle. Wait for my "ok" before creating files.

STEP 2 — Create documentation files
- docs/SYSTEM_DESIGN.md  = UI-VSB_SYSTEM_DESIGN.md moved and renamed (then add a one-line note at the top: "Where this differs from docs/API_CONTRACT.md, the contract wins.").
- docs/API_CONTRACT.md   = UI-VSB_API_CONTRACT.md moved and renamed, content unchanged.
- CLAUDE.md              = UI-VSB_CLAUDE.md moved and renamed, content unchanged. Also copy it to AGENTS.md.
- docs/walkthrough-backend.md and docs/walkthrough-frontend.md = headings only for now ("# Backend walkthrough" etc. with a short "Files and what they do" table skeleton).

STEP 3 — Create this exact folder structure (use a .gitkeep in otherwise-empty folders):
ui-vsb/
  index.php register.php login.php logout.php
  config/            config.php  config.local.example.php
  includes/          bootstrap.php  header.php  footer.php
  src/               Database.php User.php Vehicle.php Service.php Booking.php Payment.php
                     PaymentGatewayInterface.php SimulatedPaymentGateway.php Auth.php ApiResponse.php
  api/               slots.php book.php payment.php bookings.php vehicles.php services.php dashboard.php
    auth/            register.php login.php logout.php me.php
    admin/           dashboard.php services.php bookings.php status.php customers.php payments.php records.php
  customer/          dashboard.php vehicles.php services.php book.php payment.php confirmation.php track.php
  admin/             dashboard.php services.php bookings.php customers.php payments.php records.php
  assets/css/        style.css
  assets/js/         config.js app.js mocks.js book.js payment.js track.js admin.js
  assets/img/        .gitkeep
  database/          ui_vsb.sql
  tests/             .gitkeep
  docs/

STEP 4 — Stubs (so nothing 404s)
- Every file under api/ must be a stub that: sends JSON headers, then returns HTTP 501 with the contract envelope: {"success":false,"message":"Not implemented yet: <endpoint name>","data":null,"errors":null}. Use a tiny shared helper (create src/ApiResponse.php for real now: static methods success($data,$message,$code=200), error($message,$code,$errors=null,$data=null), and a notImplemented($name)).
- Every class file in src/ gets a class (or interface) skeleton with a header comment and the method names from docs/SYSTEM_DESIGN.md section 4.1 (empty bodies throwing a RuntimeException('Not implemented') is fine). Database.php IS to be implemented now (singleton PDO, utf8mb4, exceptions on, emulate prepares off, reads constants from config).
- Every PHP page in customer/, admin/, root (index, register, login, logout) is a minimal placeholder: it includes includes/bootstrap.php, includes/header.php, shows an <h1> with the page name, and includes includes/footer.php. Pages for logged-in users must call Auth::requireRole(...) (stub for now). The FRONTEND dev will replace the content, so keep them tiny.
- assets/js/config.js: `window.VSB_CONFIG = { USE_MOCK: true };`  (frontend will own this).
- assets/js/app.js, mocks.js, book.js, payment.js, track.js, admin.js, assets/css/style.css: one-line header comments only.

STEP 5 — Config and bootstrap
- config/config.php: defines constants with sensible defaults, reading overrides from config/config.local.php if it exists: DB_HOST, DB_NAME (ui_vsb_db), DB_USER (root), DB_PASS (''), BASE_URL (auto-detected from the script path, e.g. "/ui-vsb/"), SLOT_CAPACITY=2, SLOT_TIMES (array 08:00..15:00), BOOKING_HOLD_MINUTES=30, BOOKING_WINDOW_DAYS=60, APP_ENV ('dev'), DEV_BYPASS_AUTH=false.
- config/config.local.example.php: example showing overrides. config/config.local.php must be gitignored.
- includes/bootstrap.php: starts the session safely, sets timezone Africa/Lagos, requires config, registers an spl_autoload_register that loads classes from src/, turns off display_errors for api/ requests but logs them.
- includes/header.php: minimal HTML5 <head> that prints `<script>window.APP_BASE = "<?= BASE_URL ?>";</script>`, links assets/css/style.css, loads assets/js/config.js and assets/js/app.js (defer), and a bare <nav> placeholder. includes/footer.php closes the page. (Frontend will redesign these.)
- Add a DEV_BYPASS_AUTH feature in Auth::requireRole(): ONLY if APP_ENV==='dev' AND DEV_BYPASS_AUTH===true AND a ?as=customer|admin query param is present, fake a session user of that role so the frontend dev can view pages before login exists. Default false. Comment clearly that it must be removed/disabled before submission.

STEP 6 — Repo hygiene
- .gitignore: config/config.local.php, .env, .DS_Store, Thumbs.db, *.log, node_modules/, vendor/, .idea/, .vscode/ (keep .vscode/settings.json out too), uploads/, *.zip.
- README.md: project title, what it is, stack, prerequisites (XAMPP, PHP 8+), SETUP steps (clone as `ui-vsb` into htdocs, start Apache+MySQL, create DB by importing database/ui_vsb.sql in phpMyAdmin, copy config/config.local.example.php to config.local.php and set DB creds, open http://localhost/ui-vsb/), seed logins (from the contract), team + roles table (leave names blank), branch workflow summary, folder ownership table, and a "Running the checks" section (leave for later).
- database/ui_vsb.sql: for now just the CREATE DATABASE/USE line and a comment "schema coming in B1".

STEP 7 — Verify and report
- Run `php -l` on every .php file you created and report any syntax errors.
- If PHP + a web server are available, hit one stub endpoint (e.g. api/slots.php) and show that it returns the 501 JSON envelope.
- Print the final tree.
- Do NOT run git push. Instead print the exact git commands for me to run to: create branches `main` and `develop`, make the first commit "chore: project scaffold, docs and API stubs", and push both branches.

Remember: no real business logic in this phase, follow the folder structure exactly, and do not invent endpoints.
````

**You verify (B0):** `php -l` clean; open `http://localhost/ui-vsb/` and `http://localhost/ui-vsb/api/slots.php` (shows 501 JSON); tree matches; `config.local.php` is NOT tracked. Then run the git commands, push `main` and `develop`, and **tell the frontend dev to clone**. Protect `main` on GitHub (Settings → Branches → require PR).

## B1 — Database schema + core + authentication  *(backend)*

````text
Phase B1 (BACKEND). Read CLAUDE.md, docs/API_CONTRACT.md (sections 2.1 to 2.7, Auth endpoints, 2.10) and docs/SYSTEM_DESIGN.md sections 3 and 4. First write your plan (files + bullets) and wait for my ok.

GOAL: a working database and working register/login/logout/me, and the Auth guard.

1. database/ui_vsb.sql — full, import-ready, idempotent script (DROP TABLE IF EXISTS in dependency order at the top is fine for dev) using the schema in docs/SYSTEM_DESIGN.md section 3.2 exactly (users, vehicles, services, bookings, payments, status_history; InnoDB; FKs; the idx_slot index). Insert the 5 seed services and the two seed accounts from the contract (2.10). Generate REAL bcrypt hashes with `php -r "echo password_hash('Admin@123', PASSWORD_DEFAULT);"` and embed them so the import works with no extra step. Add a few demo vehicles for the demo customer. Add COMMENTS above each table.
2. src/Database.php finished (if not already) — singleton, getConnection().
3. src/ApiResponse.php — finished; sets headers, status code, JSON encodes with JSON_UNESCAPED_UNICODE, and exits.
4. src/Auth.php — static methods: login(array $user) (regenerate session id, store user_id, role, full_name), logout(), isLoggedIn(), currentUser(), requireLogin() and requireRole($role) that behave differently for pages vs API: for API requests (path contains /api/) return a 401/403 JSON envelope; for pages redirect to login.php (not logged in) or to the user's own dashboard (wrong role). Keep the DEV_BYPASS_AUTH logic from B0.
5. src/User.php — register(array $data) with ALL validation from contract 2.6 returning field errors, duplicate-email check, password_hash, role forced to 'customer'; login($email,$password) (generic error message for both unknown email and bad password); findById; getAllCustomers is later.
6. api/auth/register.php, login.php, logout.php, me.php exactly per the contract (JSON input via php://input, method checks returning 405, envelope, status codes, `redirect` value on login).
7. Pages: make login.php/register.php redirect already-logged-in users to their dashboard (tiny change; frontend will build the UI). Make Auth::requireRole work on customer/* and admin/* placeholder pages.

TESTS: create tests/api_smoke.php or tests/smoke.sh (curl with a cookie jar) that exercises: register success; register duplicate email (409); register weak password (400 with errors.password); login wrong password (401); login success; me with cookie (200); me without cookie (401); logout. Print PASS/FAIL per line. Run it and show me the output.

RULES: prepared statements only; never reveal whether an email exists on login; no output before headers; php -l all files; update docs/walkthrough-backend.md with a section for B1 (each file + the key idea in 2–3 sentences, plain English).
````

**You verify (B1):** Import `database/ui_vsb.sql` in phpMyAdmin → 6 tables + seeds. Run the smoke test. Log in as admin in a REST client or the smoke script. Check password column is a `$2y$` hash. Commit → PR → merge → **tell frontend: auth is real.**

## B2 — Vehicles + services  *(backend)*

````text
Phase B2 (BACKEND). Read CLAUDE.md and docs/API_CONTRACT.md (Customer: services, vehicles; Admin: services; rules in 2.5 and 2.6). Plan first, wait for my ok.

Implement:
1. src/Vehicle.php: add($userId,$data) with validation and duplicate reg_number detection (store UPPERCASE), getByUser($userId), belongsTo($vehicleId,$userId), hasBookings($vehicleId), delete($vehicleId,$userId).
2. src/Service.php: getAllActive(), getAll() (admin, includes inactive), getById(), create(), update(), delete() — delete deactivates (is_active=0) when the service has bookings, otherwise really deletes; return which one happened so the message can say so. Cast price to float, is_active to bool in the returned arrays so JSON matches the contract shapes.
3. api/vehicles.php (GET list, POST action=add|delete), api/services.php (GET active), api/admin/services.php (GET all, POST action=create|update|delete). Role checks: customer endpoints require customer; admin endpoints require admin. Ownership check on vehicle delete (403) and has-bookings check (409).
4. Add tests to your smoke test: add vehicle OK; duplicate plate 409; invalid year 400; delete vehicle OK; customer calling admin/services.php gets 403; admin create/update/delete service; services list excludes inactive for customers.

Return objects EXACTLY as the contract shapes (Vehicle, Service). Update docs/walkthrough-backend.md. Show me test output.
````

**You verify (B2):** run smoke tests; look at tables in phpMyAdmin after actions; confirm a customer token cannot call `/api/admin/*`.

## B3 — Booking engine + Fetch slots  *(backend)*  — the most important phase

````text
Phase B3 (BACKEND). Read CLAUDE.md and docs/API_CONTRACT.md (slots, book, bookings, Booking object 2.7, business rules 2.5). Also docs/SYSTEM_DESIGN.md sections 3.3 and 4.2/4.3. Plan first, wait for my ok.

Implement src/Booking.php and the endpoints:

1. Booking::getAvailableSlots($date): generate SLOT_TIMES; closed on Sundays (endpoint returns data [] + message); for today mark past start times unavailable; count active bookings per slot using the rule in 2.5 (pending_approval, approved, and pending_payment younger than BOOKING_HOLD_MINUTES). Return [{time, available, remaining}].
2. Booking::create(): validation per 2.6/2.5 (vehicle belongs to user, service active, date valid and in window, slot_time in SLOT_TIMES). Use a DB TRANSACTION and re-check capacity inside it with a locking read (SELECT ... FOR UPDATE) so two users cannot take the last bay. If full -> rollback and return the 409 conflict. Insert with amount = current service price, status pending_payment, service_status not_started, unique booking_ref (retry on collision), write a status_history row "Booking created".
3. Expiry: add Booking::expireStale() (or inline) that sets pending_payment bookings older than the hold to cancelled with a history row; call it at the start of slots.php, book.php, bookings.php, payment (later).
4. Booking::getByUser($userId) and getByRef($ref,$userId): return the Booking shape from the contract (vehicle, service nested objects; payment = null for now unless a success/refunded payment exists; history only in the detail call).
5. Endpoints: api/slots.php (GET), api/book.php (POST), api/bookings.php (GET list, GET ?ref=). Customer role required. Proper status codes.
6. Add Booking::addHistory($bookingId,$status,$note,$userId).

TESTS to add: slots for a future weekday returns 8 slots; slots for a Sunday returns []; past date 400; book OK returns booking_ref in the right format; fill a slot to capacity using the 2 accounts or by inserting rows, then third booking returns 409; booking someone else's vehicle returns 403; bookings list contains the new booking; ?ref= returns history.
Also write a small CONCURRENCY note in the walkthrough explaining why the transaction + FOR UPDATE prevents double-booking.
Show me test output and update docs/walkthrough-backend.md.
````

**You verify (B3):** In phpMyAdmin check `bookings` and `status_history` rows. Try booking the same slot 3 times; third must fail with 409. Merge, then **tell frontend: slots/book/bookings are real** (SYNC 2).

## B4 — Simulated payment  *(backend)*

````text
Phase B4 (BACKEND). Read CLAUDE.md and docs/API_CONTRACT.md (payment endpoint, 2.9 simulated rules, Booking lifecycle 2.5) and docs/SYSTEM_DESIGN.md section 6. Plan first, wait for my ok.

Implement:
1. src/PaymentGatewayInterface.php: `charge(array $card, float $amount): array` returning ['success'=>bool,'reason'=>?string,'transaction_ref'=>string].
2. src/SimulatedPaymentGateway.php implementing it with the test-card table in contract 2.9 (4242... success; ...0002 card_declined; ...9995 insufficient_funds; expired expiry -> expired_card; any other Luhn-valid card success). usleep(~900000) to imitate latency. Generate transaction_ref TXN-YYYYMMDD-XXXXXX. NO network calls, NO real gateway, NO external keys.
3. src/Payment.php: record(), getByBooking(), getAll() (for later), refund($bookingId).
4. api/payment.php (POST, customer): steps: expireStale; load booking; 403 if not yours; 409 if not pending_payment (message says already paid or expired); validate format server-side (Luhn, 16 digits, MM/YY, cvv 3 digits) -> 400 with field errors and NO payment row; call the gateway via the interface (type-hint the interface so it is swappable); on success: transaction — insert payments row (status success, card_last4 only), set booking_status pending_approval, addHistory "Payment received"; on decline: insert payments row (status failed, failure_reason), keep booking pending_payment, return 422 with data.reason + transaction_ref per contract. NEVER store the full card number or CVV anywhere, including logs.
5. Update Booking::getByUser/getByRef so `payment` reflects the latest success/refunded payment (null otherwise).
6. Add tests: pay with 4242 -> 200, booking becomes pending_approval; pay with ...0002 -> 422 and booking still pending_payment and a failed row exists; ...9995 -> 422 insufficient_funds; bad Luhn -> 400 and NO new payments row; expired expiry -> 422 expired_card; paying again after success -> 409; paying another user's booking -> 403; paying an expired booking (backdate created_at in the test) -> 409.
Also grep the repo/logs to prove the full card number and CVV are not stored. Update docs/walkthrough-backend.md, include a short explanation of why we use an interface here (polymorphism).
````

**You verify (B4):** `payments` table shows both `success` and `failed` rows with only `card_last4`. Merge → SYNC 3.

## B5 — Admin API + dashboards  *(backend)*

````text
Phase B5 (BACKEND). Read CLAUDE.md and docs/API_CONTRACT.md (all Admin endpoints, customer dashboard, Booking object, 2.5 lifecycle rules). Plan first, wait for my ok.

Implement (admin role required, 403 otherwise):
1. Booking::getAll(array $filters) with filters q (booking_ref, reg_number, customer name/email), status, date_from, date_to — all optional, parameterised LIKE queries. Include customer and payment in each Booking. Also getByRefAdmin() with history.
2. Booking::approve($id,$adminId,$note) and reject(): only from pending_approval (else 409). Reject sets the successful payment to refunded (Payment::refund) and logs history. Approve logs history.
3. Booking::updateServiceStatus($id,$newStatus,$note,$adminId): only when approved, only forward in order not_started < received < in_progress < completed < ready_for_pickup; else 409. Logs history with the label.
4. User::getAllCustomers($q) with vehicle_count, booking_count and the vehicles array (avoid N+1 where reasonable).
5. Payment::getAll($filters) + total of successful payments.
6. Endpoints: api/admin/bookings.php (GET list/detail, POST approve|reject), api/admin/status.php, api/admin/customers.php, api/admin/payments.php, api/admin/records.php (search service records per contract), api/admin/dashboard.php (bookings_today, pending_approvals, total_revenue, active_services, recent_bookings), and the customer api/dashboard.php (vehicle_count, active_bookings = bookings not rejected/cancelled/completed-and-picked-up, latest_booking).
7. Tests: admin list; filter by status and by q; approve; reject refunds payment; approve a non-pending -> 409; status forward OK; status backward -> 409; status on non-approved -> 409; customer calling any admin endpoint -> 403; dashboard numbers match DB counts.
Update docs/walkthrough-backend.md. Show test output.
````

**You verify (B5):** reject a paid booking → `payments.status = refunded`. Merge → SYNC 4.

## B6 — Hardening, demo data, final SQL, docs  *(backend)*

````text
Phase B6 (BACKEND). Read CLAUDE.md, docs/API_CONTRACT.md, docs/SYSTEM_DESIGN.md section 8 (security) and 9 (demo script). Plan first, wait for my ok.

1. Security review of every file under api/ and src/: prepared statements only (grep for string-built SQL), method checks, role + ownership on every endpoint, no stack traces or SQL in responses, display_errors off, session cookie flags (httponly, samesite=Lax), session_regenerate_id on login, no card data stored or logged. Fix anything found and list what you changed.
2. Make sure DEV_BYPASS_AUTH is false and APP_ENV default is documented; add a clear README line that these must be off for the demo.
3. Make database/ui_vsb.sql the final submission file: schema + seed services + 2 accounts + a realistic demo dataset (about 6 customers' worth is not needed; 3 customers, 5 vehicles, ~10 bookings in different statuses on future and recent dates, with matching payments including 2 failed attempts and 1 refunded, and status_history rows) so screenshots of admin pages look real. Verify the file imports cleanly into a fresh empty database with no errors (drop and re-import, run the smoke tests against it).
4. Add tests/run_all.(php|sh) that runs every earlier test and prints a summary; run it and show the output.
5. Finish README.md (setup, seed logins, how to reset the database, how to run tests, folder ownership, API summary table).
6. Finish docs/walkthrough-backend.md: for EACH class in src/ and each api file give 2–4 plain-English sentences, plus a short "How to explain the booking/payment flow in 60 seconds" script, and a list of likely examiner questions with short answers (OOP concepts used, why PDO prepared statements, how double booking is prevented, why interface for the gateway, how sessions work).
7. Print a final checklist of the course requirements (HTML/CSS/JS/PHP/MySQL, OOP, Fetch, API endpoint, MySQL storage, simulated payment, success + failure demos, SQL file) and tick what the backend satisfies.
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
