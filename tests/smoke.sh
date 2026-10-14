#!/usr/bin/env bash
# tests/smoke.sh : B1 smoke test for the auth API (register, login, me, logout).
# Usage:  bash tests/smoke.sh [base_url]
# Default base url is http://localhost/ui-vsb  (XAMPP). Needs curl.
# Each line prints PASS or FAIL; the script exits 1 if anything failed.

BASE="${1:-http://localhost/ui-vsb}"
JAR=$(mktemp)            # cookie jar = the "browser session"
JAR2=$(mktemp)
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
call "$JAR" POST api/auth/logout.php;                                 check "logout customer (200)" 200
call "$JAR" GET  api/auth/me.php;                                     check "me after logout (401)" 401

rm -f "$JAR" "$JAR2"
echo "----"; echo "Passed: $PASS   Failed: $FAIL"
[ "$FAIL" -eq 0 ]
