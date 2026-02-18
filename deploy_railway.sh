#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

echo "==> Checking Railway CLI"
if ! command -v railway >/dev/null 2>&1; then
  echo "Railway CLI not found. Installing..."
  if command -v npm >/dev/null 2>&1; then
    npm i -g @railway/cli
  else
    echo "npm not found. Install Node.js + npm, then run: npm i -g @railway/cli"
    exit 1
  fi
fi

if ! command -v railway >/dev/null 2>&1; then
  echo "Railway CLI is still not available. Install with: npm i -g @railway/cli"
  exit 1
fi

echo "==> Checking Railway login"
if ! railway whoami >/dev/null 2>&1; then
  echo "Login required. Using browserless login..."
  railway login --browserless
fi

echo "==> Linking Railway project"
if ! railway status >/dev/null 2>&1; then
  railway init
fi

echo "==> Adding MySQL service (if missing)"
set +e
railway add --database mysql >/dev/null 2>&1
set -e

echo "==> Deploying project"
railway up --detach

echo "==> Generating domain"
domain_json="$(railway domain --json 2>/dev/null || true)"
domain=""

if [ -n "$domain_json" ]; then
  domain="$(python3 - <<'PY' "$domain_json"
import json
import sys

data = json.loads(sys.argv[1])

def find_domain(obj):
    if isinstance(obj, dict):
        for key in ("domain", "url", "hostname"):
            value = obj.get(key)
            if isinstance(value, str) and value:
                return value
        for value in obj.values():
            found = find_domain(value)
            if found:
                return found
    elif isinstance(obj, list):
        for item in obj:
            found = find_domain(item)
            if found:
                return found
    return ""

print(find_domain(data))
PY
)"
fi

if [ -z "$domain" ]; then
  domain_output="$(railway domain 2>/dev/null || true)"
  domain="$(python3 - <<'PY' "$domain_output"
import re
import sys

text = sys.argv[1]
match = re.search(r"([A-Za-z0-9.-]+\.up\.railway\.app)", text)
print(match.group(1) if match else "")
PY
)"
fi

if [ -n "$domain" ]; then
  echo "Project URL: https://$domain"
else
  echo "Could not determine domain automatically."
  echo "Run: railway domain"
fi
