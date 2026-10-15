# Project: Vehicle Service Booking & Payment Platform

University of Ibadan Maintenance Directorate. Customers register vehicles, check slots (Fetch), book, pay (SIMULATED), and track progress. Admin manages services, bookings, status, customers, payments.

This is a university group portfolio project. The grader will demo a WORKING system and ask each member to explain their code. Therefore: simple, readable, well-commented code beats clever code.

## Hard constraints (from the course guidelines)
- Stack: HTML, CSS, vanilla JavaScript, PHP 8 (OOP), MySQL. NO frameworks: no Laravel/Symfony, no React/Vue/jQuery, no Bootstrap/Tailwind, no Composer packages, no npm build step.
- Must demo: a working system; asynchronous communication using the Fetch API for major operations; PHP OOP classes for the domain; real MySQL storage/retrieval; at least one API endpoint; SIMULATED payment only (never real money, never real gateway keys); both successful and failed operations; a submitted SQL file (`database/ui_vsb.sql`).

## Source of truth
- `docs/API_CONTRACT.md` — endpoints, JSON shapes, enums, business rules, validation. Follow it EXACTLY (URLs, field names, status codes, enum strings).
- `docs/SYSTEM_DESIGN.md` — architecture, DB schema, class list, page list, demo script.
- If these two disagree, API_CONTRACT.md wins. If you think the contract is wrong or incomplete, STOP and tell the developer. Never silently deviate or invent endpoints/fields.

## Ownership (do not edit the other side's files)
- BACKEND owns: config/, src/, api/, database/, tests/, includes/bootstrap.php, docs/walkthrough-backend.md
- FRONTEND owns: index.php, register.php, login.php, logout.php, customer/, admin/, assets/, includes/header.php, includes/footer.php, docs/walkthrough-frontend.md
- If a task needs a change in the other side's area, stop and say what is needed.

## Conventions
- Names: PHP classes PascalCase (one per file, filename = class name, in src/); JS variables/functions camelCase; JSON keys and DB columns snake_case; file names lowercase.
- Never hard-code the app folder name. Backend uses constants from config/config.php; frontend uses `window.APP_BASE`.
- Every API response uses the envelope in the contract. Never echo raw PHP errors; set `display_errors` off in API files and log instead.
- Write comments that explain WHY, in plain English. Add a short header comment to every file saying what it is for.
- Keep functions short. No dead code. No TODOs left in finished work.

## Security baseline
- Backend: PDO prepared statements ONLY; `password_hash`/`password_verify`; `session_regenerate_id(true)` on login; role + ownership checks in every protected endpoint; validate everything server-side; store only card_last4.
- Frontend: never put user data into `innerHTML` (use `textContent` or an escape helper); client validation is for UX only; never trust hidden fields.

## Working style
- Do one phase at a time, exactly as asked. Do not start the next phase.
- Before coding a phase, restate your plan in 5–10 bullets and list the files you will create/change.
- After coding, list what you changed, how to test it, and anything you were unsure about.
- Append a short section to your walkthrough doc (what each new file does and the key idea behind it) so the developer can explain it in the demo.
- Do not commit or push unless asked. Never commit config/config.local.php.
