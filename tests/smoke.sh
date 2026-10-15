#!/usr/bin/env bash
# tests/smoke.sh : Smoke test suite for B1 (auth), B2 (vehicles, services), B3 (slots, book, bookings), and B5 (admin API, workflows, dashboards).
# Usage:  bash tests/smoke.sh [base_url]
# Default base url is http://localhost/ui-vsb  (XAMPP). Needs curl.
# Each line prints PASS or FAIL; the script exits 1 if anything failed.

BASE="${1:-http://localhost/ui-vsb}"
PHP_EXEC="/c/xampp/php/php.exe"
if ! command -v "$PHP_EXEC" &>/dev/null && command -v php &>/dev/null; then
  PHP_EXEC="php"
fi

JAR=$(mktemp)            # customer 1 session cookie jar
JAR2=$(mktemp)           # admin session cookie jar
EMAIL="smoke$(date +%s)@test.com"
PASS=0; FAIL=0

# call <jar> <METHOD> <path> [json body]  -> sets $CODE and $BODY
call() {
  local jar="$1" method="$2" path="$3" body="${4:-}"
  local out
  if [ -n "$body" ]; then
    out=$(curl -s -w '\n%{http_code}' -b "$jar" -c "$jar" -X "$method" \
          -H 'Content-Type: application/json' -d "$body" "$BASE/$path")
  else
    out=$(curl -s -w '\n%{http_code}' -b "$jar" -c "$jar" -X "$method" "$BASE/$path")
  fi
  CODE=$(echo "$out" | tail -n1)
  BODY=$(echo "$out" | sed '$d')
}

# check <label> <expected status> [text the body must contain]
check() {
  local label="$1" want="$2" needle="${3:-}"
  if [ "$CODE" = "$want" ] && { [ -z "$needle" ] || echo "$BODY" | grep -q -- "$needle"; }; then
    echo "PASS  $label"; PASS=$((PASS+1))
  else
    echo "FAIL  $label (got $CODE, wanted $want $needle)"; echo "      $BODY" | head -c 300; echo
    FAIL=$((FAIL+1))
  fi
}

echo "=== PHASE B1: AUTH SMOKE TESTS ==="
GOOD="{\"full_name\":\"Smoke Tester\",\"email\":\"$EMAIL\",\"phone\":\"08011112222\",\"password\":\"Passw0rd1\",\"confirm_password\":\"Passw0rd1\"}"

call "$JAR" POST api/auth/register.php "$GOOD";                       check "register success (201)" 201 '"role":"customer"'
call "$JAR" GET  api/auth/me.php;                                     check "me right after register (200)" 200 "$EMAIL"
call "$JAR" POST api/auth/logout.php;                                 check "logout (200)" 200 'Logged out'
call "$JAR" POST api/auth/register.php "$GOOD";                       check "register duplicate email (409)" 409 'already registered'
call "$JAR" POST api/auth/register.php "{\"full_name\":\"A\",\"email\":\"x$EMAIL\",\"phone\":\"08011112222\",\"password\":\"abc\",\"confirm_password\":\"abc\"}"
                                                                      check "register weak password (400, errors.password)" 400 '"password"'
call "$JAR" POST api/auth/register.php "{\"full_name\":\"Role Hacker\",\"email\":\"h$EMAIL\",\"phone\":\"08011112222\",\"password\":\"Passw0rd1\",\"confirm_password\":\"Passw0rd1\",\"role\":\"admin\"}"
                                                                      check "register ignores role=admin (customer)" 201 '"role":"customer"'
call "$JAR" POST api/auth/logout.php >/dev/null
call "$JAR" POST api/auth/login.php "{\"email\":\"$EMAIL\",\"password\":\"WrongPass1\"}"
                                                                      check "login wrong password (401)" 401 'Invalid email or password'
call "$JAR" POST api/auth/login.php '{"email":"nobody@nowhere.com","password":"Passw0rd1"}'
                                                                      check "login unknown email gives same 401 message" 401 'Invalid email or password'
call "$JAR" POST api/auth/login.php "{\"email\":\"$EMAIL\",\"password\":\"Passw0rd1\"}"
                                                                      check "login success (200, customer redirect)" 200 'customer/dashboard.php'
call "$JAR" GET  api/auth/me.php;                                     check "me with cookie (200)" 200 "$EMAIL"
call "$JAR2" GET api/auth/me.php;                                     check "me without cookie (401)" 401
call "$JAR2" POST api/auth/login.php '{"email":"admin@ui.edu.ng","password":"Admin@123"}'
                                                                      check "seed admin login (200, admin redirect)" 200 'admin/dashboard.php'
call "$JAR" GET  api/auth/login.php;                                  check "wrong method on login (405)" 405
call "$JAR" POST api/auth/login.php 'not json';                       check "bad JSON body (400)" 400

echo ""
echo "=== PHASE B2: VEHICLES & SERVICES ==="
# Customer views active services catalogue
call "$JAR" GET api/services.php;                                     check "customer gets active services (200)" 200 'Oil Change'

# Customer vehicles
RAND=$((1000 + RANDOM % 9000))
PLATE="SMK-$RAND-AB"
call "$JAR" GET api/vehicles.php;                                     check "customer gets vehicles list (200)" 200
call "$JAR" POST api/vehicles.php "{\"action\":\"add\",\"reg_number\":\"$PLATE\",\"make\":\"Toyota\",\"model\":\"Corolla\",\"year\":2020}"
                                                                      check "add vehicle OK (201)" 201 "$PLATE"
VEH_ID=$(echo "$BODY" | grep -o '"vehicle_id":[0-9]*' | head -n1 | cut -d':' -f2)

call "$JAR" POST api/vehicles.php "{\"action\":\"add\",\"reg_number\":\"$PLATE\",\"make\":\"Honda\",\"model\":\"Civic\",\"year\":2021}"
                                                                      check "duplicate reg_number returns 409" 409 'already exists'
call "$JAR" POST api/vehicles.php "{\"action\":\"add\",\"reg_number\":\"BAD-1\",\"make\":\"Honda\",\"model\":\"Civic\",\"year\":1970}"
                                                                      check "invalid year returns 400" 400 '"year"'

# Admin services management & role guards
call "$JAR" GET api/admin/services.php;                               check "customer calling admin/services gets 403" 403
call "$JAR2" GET api/admin/services.php;                              check "admin gets all services (200)" 200 'duration_mins'
SVC_NAME="Custom Service $RAND"
call "$JAR2" POST api/admin/services.php "{\"action\":\"create\",\"name\":\"$SVC_NAME\",\"description\":\"Smoke test service\",\"price\":25000,\"duration_mins\":60}"
                                                                      check "admin create service (201)" 201 "$SVC_NAME"
SVC_ID=$(echo "$BODY" | grep -o '"service_id":[0-9]*' | head -n1 | cut -d':' -f2)

call "$JAR2" POST api/admin/services.php "{\"action\":\"update\",\"service_id\":$SVC_ID,\"name\":\"$SVC_NAME Updated\",\"price\":27000,\"duration_mins\":75,\"is_active\":true}"
                                                                      check "admin update service (200)" 200 '27000'

echo ""
echo "=== PHASE B3: BOOKING ENGINE & SLOTS ==="
WEEKDAY=$(date -d "+1 day" +%Y-%m-%d)
SUNDAY=$(date -d "+3 days" +%Y-%m-%d)

call "$JAR" GET "api/slots.php?date=2020-01-01";                      check "slots past date returns 400" 400 'past'
call "$JAR" GET "api/slots.php?date=$SUNDAY";                         check "slots for Sunday returns closed (200)" 200 'closed on Sundays'
call "$JAR" GET "api/slots.php?date=$WEEKDAY";                        check "slots for weekday returns 8 slots (200)" 200 '08:00'

# Attempt to book with someone else's vehicle (seed customer's vehicle_id=1, but logged in as smoke customer)
call "$JAR" POST api/book.php "{\"vehicle_id\":1,\"service_id\":$SVC_ID,\"date\":\"$WEEKDAY\",\"slot_time\":\"11:00\"}"
                                                                      check "booking someone else vehicle returns 403" 403 'not own'

# Customer books slot 11:00
call "$JAR" POST api/book.php "{\"vehicle_id\":$VEH_ID,\"service_id\":$SVC_ID,\"date\":\"$WEEKDAY\",\"slot_time\":\"11:00\",\"notes\":\"Smoke booking 1\"}"
                                                                      check "first booking for slot returns 201" 201 'VSB-'
BOOKING_REF=$(echo "$BODY" | grep -o '"booking_ref":"[^"]*"' | head -n1 | cut -d'"' -f4)
BOOKING_ID=$(echo "$BODY" | grep -o '"booking_id":[0-9]*' | head -n1 | cut -d':' -f2)

# Customer books second bay for slot 11:00
call "$JAR" POST api/book.php "{\"vehicle_id\":$VEH_ID,\"service_id\":$SVC_ID,\"date\":\"$WEEKDAY\",\"slot_time\":\"11:00\"}"
                                                                      check "second booking for slot returns 201 (slot full)" 201 'VSB-'

# Customer attempts third booking for slot 11:00 (slot capacity 2 exceeded)
call "$JAR" POST api/book.php "{\"vehicle_id\":$VEH_ID,\"service_id\":$SVC_ID,\"date\":\"$WEEKDAY\",\"slot_time\":\"11:00\"}"
                                                                      check "third booking returns 409 slot full" 409 'just taken'

# Customer attempts to delete vehicle that now has bookings -> 409
call "$JAR" POST api/vehicles.php "{\"action\":\"delete\",\"vehicle_id\":$VEH_ID}"
                                                                      check "deleting vehicle with bookings returns 409" 409 'cannot be deleted'

# Admin deletes service that has bookings -> deactivates (200)
call "$JAR2" POST api/admin/services.php "{\"action\":\"delete\",\"service_id\":$SVC_ID}"
                                                                      check "service with bookings is deactivated (200)" 200 'deactivated because it has bookings'

# Customer retrieves bookings list
call "$JAR" GET api/bookings.php;                                     check "customer bookings list returns 200" 200 "$BOOKING_REF"

# Customer retrieves single booking detail by reference (includes history)
call "$JAR" GET "api/bookings.php?ref=$BOOKING_REF";                  check "customer booking detail by ref returns 200" 200 'Booking created'

# Customer attempts to view non-existent reference
call "$JAR" GET "api/bookings.php?ref=VSB-99999999-FAKE";             check "unknown booking ref returns 404" 404

echo ""
echo "=== PHASE B5: ADMIN API & DASHBOARDS ==="
# Customer dashboard
call "$JAR" GET api/dashboard.php;                                    check "customer dashboard returns 200" 200 'vehicle_count'

# Role enforcement: customer cannot call admin endpoints
call "$JAR" GET api/admin/dashboard.php;                              check "customer calling admin/dashboard gets 403" 403
call "$JAR" GET api/admin/customers.php;                              check "customer calling admin/customers gets 403" 403
call "$JAR" GET api/admin/payments.php;                               check "customer calling admin/payments gets 403" 403
call "$JAR" GET api/admin/records.php;                                check "customer calling admin/records gets 403" 403

# Admin dashboard
call "$JAR2" GET api/admin/dashboard.php;                             check "admin dashboard returns 200" 200 'total_revenue'

# Admin customers overview
call "$JAR2" GET api/admin/customers.php;                             check "admin customers list returns 200" 200 'vehicles'

# Admin payments report
call "$JAR2" GET api/admin/payments.php;                              check "admin payments report returns 200" 200 'total_success'

# Admin records search
call "$JAR2" GET "api/admin/records.php?q=Smoke";                     check "admin service records search returns 200" 200 'service'

# Admin bookings list
call "$JAR2" GET "api/admin/bookings.php?q=$BOOKING_REF";             check "admin bookings list returns filtered booking" 200 "$BOOKING_REF"
call "$JAR2" GET "api/admin/bookings.php?ref=$BOOKING_REF";          check "admin booking detail returns history" 200 'history'

# Admin workflow: cannot approve a pending_payment booking -> 409
call "$JAR2" POST api/admin/bookings.php "{\"booking_id\":$BOOKING_ID,\"action\":\"approve\"}"
                                                                      check "approve pending_payment returns 409" 409 'awaiting approval'

# Simulate booking payment received: transition booking status to pending_approval in DB for workflow test
"$PHP_EXEC" -r "require 'includes/bootstrap.php'; Database::getInstance()->getConnection()->exec(\"UPDATE bookings SET booking_status = 'pending_approval' WHERE booking_id = $BOOKING_ID\");"

# Admin approves booking -> 200
call "$JAR2" POST api/admin/bookings.php "{\"booking_id\":$BOOKING_ID,\"action\":\"approve\",\"note\":\"Approved via smoke test\"}"
                                                                      check "admin approves booking returns 200" 200 'approved'

# Admin advances service status stepper: received -> in_progress
call "$JAR2" POST api/admin/status.php "{\"booking_id\":$BOOKING_ID,\"service_status\":\"received\"}"
                                                                      check "advance service status to received (200)" 200 'received'
call "$JAR2" POST api/admin/status.php "{\"booking_id\":$BOOKING_ID,\"service_status\":\"in_progress\"}"
                                                                      check "advance service status to in_progress (200)" 200 'in_progress'

# Admin attempts backward service status -> 409
call "$JAR2" POST api/admin/status.php "{\"booking_id\":$BOOKING_ID,\"service_status\":\"received\"}"
                                                                      check "backward service status move returns 409" 409 'only move forward'

# Cleanup created test booking and service
"$PHP_EXEC" -r "require 'includes/bootstrap.php'; \$pdo = Database::getInstance()->getConnection(); \$pdo->exec(\"DELETE FROM status_history WHERE booking_id IN ($BOOKING_ID, $BOOKING_ID+1); DELETE FROM bookings WHERE booking_id IN ($BOOKING_ID, $BOOKING_ID+1); DELETE FROM vehicles WHERE vehicle_id = $VEH_ID; DELETE FROM services WHERE service_id = $SVC_ID;\");"

call "$JAR" POST api/auth/logout.php >/dev/null
call "$JAR2" POST api/auth/logout.php >/dev/null

rm -f "$JAR" "$JAR2"
echo ""
echo "=========================================="
echo "Passed: $PASS   Failed: $FAIL"
echo "=========================================="
[ "$FAIL" -eq 0 ]
