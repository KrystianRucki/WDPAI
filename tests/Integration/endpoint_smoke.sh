#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
TMP_BODY="${TMP_BODY:-/tmp/reevio_endpoint_smoke_body.txt}"

check_status() {
  local path="$1"
  local expected="$2"
  local status

  status="$(curl -sS -o "$TMP_BODY" -w "%{http_code}" "$BASE_URL$path")"

  if [[ "$status" != "$expected" ]]; then
    echo "FAIL $path expected $expected got $status" >&2
    sed -n '1,20p' "$TMP_BODY" >&2 || true
    exit 1
  fi

  echo "OK   $path -> $status"
}

check_status "/login" "200"
check_status "/offline-page" "200"
check_status "/definitely-missing-route" "404"
check_status "/bad-request" "400"

api_status="$(curl -sS -H 'Accept: application/json' -o "$TMP_BODY" -w "%{http_code}" "$BASE_URL/definitely-missing-route")"
if [[ "$api_status" != "404" ]]; then
  echo "FAIL JSON 404 expected 404 got $api_status" >&2
  exit 1
fi

if ! grep -q '"status":404' "$TMP_BODY"; then
  echo "FAIL JSON 404 body does not contain status=404" >&2
  cat "$TMP_BODY" >&2
  exit 1
fi

echo "OK   JSON 404 body"
