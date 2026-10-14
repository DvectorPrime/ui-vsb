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
