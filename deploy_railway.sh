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
preferred_mysql_service="${MYSQL_SERVICE_NAME:-MySQL-gGuq}"
status_json="$(railway status --json 2>/dev/null || true)"
has_mysql=""
mysql_service=""

if [ -n "$status_json" ]; then
  has_mysql="$(python3 - <<'PY' "$status_json"
import json
import sys

data = json.loads(sys.argv[1])
services = data.get("services", {}).get("edges", [])
names = [edge.get("node", {}).get("name", "") for edge in services]

print("yes" if any(name.lower().startswith("mysql") for name in names) else "")
PY
  )"

  mysql_service="$(python3 - <<'PY' "$status_json" "$preferred_mysql_service"
import json
import sys

data = json.loads(sys.argv[1])
preferred = (sys.argv[2] or "").strip()
services = data.get("services", {}).get("edges", [])
names = [edge.get("node", {}).get("name", "") for edge in services]
mysql_names = [name for name in names if name and name.lower().startswith("mysql")]

preferred_lower = preferred.lower()
for name in mysql_names:
    if name.lower() == preferred_lower:
        print(name)
        raise SystemExit

print(mysql_names[0] if mysql_names else "")
PY
  )"
fi

if [ -z "$has_mysql" ]; then
  set +e
  railway add --database mysql >/dev/null 2>&1
  set -e
  status_json="$(railway status --json 2>/dev/null || true)"
  if [ -n "$status_json" ]; then
    mysql_service="$(python3 - <<'PY' "$status_json" "$preferred_mysql_service"
import json
import sys

data = json.loads(sys.argv[1])
preferred = (sys.argv[2] or "").strip()
services = data.get("services", {}).get("edges", [])
names = [edge.get("node", {}).get("name", "") for edge in services]
mysql_names = [name for name in names if name and name.lower().startswith("mysql")]

preferred_lower = preferred.lower()
for name in mysql_names:
    if name.lower() == preferred_lower:
        print(name)
        raise SystemExit

print(mysql_names[0] if mysql_names else "")
PY
    )"
  fi
fi

echo "==> Ensuring app service is selected"
status_json="$(railway status --json 2>/dev/null || true)"
app_service=""

if [ -n "$status_json" ]; then
  app_service="$(python3 - <<'PY' "$status_json"
import json
import sys

data = json.loads(sys.argv[1])
services = data.get("services", {}).get("edges", [])
names = [edge.get("node", {}).get("name") for edge in services]
names = [name for name in names if name]

for name in names:
    lower = name.lower()
    if not lower.startswith("mysql"):
        print(name)
        break
PY
)"
fi

if [ -z "$app_service" ]; then
  app_service="web"
fi

if ! railway service link "$app_service" >/dev/null 2>&1; then
  railway add --service "$app_service" >/dev/null 2>&1 || true
  railway service link "$app_service"
fi

if [ -n "$mysql_service" ]; then
  echo "==> Linking MySQL variables into app service"
  railway variables set --service "$app_service" \
    MYSQLHOST="\${{${mysql_service}.MYSQLHOST}}" \
    MYSQLPORT="\${{${mysql_service}.MYSQLPORT}}" \
    MYSQLDATABASE="\${{${mysql_service}.MYSQLDATABASE}}" \
    MYSQLUSER="\${{${mysql_service}.MYSQLUSER}}" \
    MYSQLPASSWORD="\${{${mysql_service}.MYSQLPASSWORD}}" \
    MYSQL_URL="\${{${mysql_service}.MYSQL_URL}}" \
    DATABASE_URL="\${{${mysql_service}.MYSQL_URL}}" >/dev/null 2>&1 || true
else
  echo "==> Warning: No MySQL service found to link"
fi

echo "==> Deploying project"
railway up --detach

echo "==> Generating domain"
domain_json="$(railway domain --service "$app_service" --json 2>/dev/null || true)"
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

value = find_domain(data)
if value.startswith("http://") or value.startswith("https://"):
    from urllib.parse import urlparse
    parsed = urlparse(value)
    if parsed.netloc:
        print(parsed.netloc)
    else:
        print(value)
else:
    print(value)
PY
)"
fi

if [ -z "$domain" ]; then
  domain_output="$(railway domain --service "$app_service" 2>/dev/null || true)"
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
  if [[ "$domain" == http://* || "$domain" == https://* ]]; then
    echo "Project URL: $domain"
  else
    echo "Project URL: https://$domain"
  fi
else
  echo "Could not determine domain automatically."
  echo "Run: railway domain"
fi
