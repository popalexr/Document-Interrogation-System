#!/usr/bin/env bash
set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "Run this script as root (sudo)." >&2
    exit 1
fi

PROJECT_ROOT="${1:-}"
SERVICE_USER="${2:-}"
PHP_PATH="${3:-$(command -v php || true)}"
SERVICE_GROUP="${4:-$SERVICE_USER}"

if [[ -z "$PROJECT_ROOT" || -z "$SERVICE_USER" ]]; then
    echo "Usage: sudo $0 /absolute/project/path service-user [php-path] [service-group]" >&2
    exit 1
fi

PROJECT_ROOT="$(realpath "$PROJECT_ROOT")"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

if [[ ! -x "$PROJECT_ROOT/Application/MCP_Server/.venv/bin/python" ]]; then
    echo "Missing executable: $PROJECT_ROOT/Application/MCP_Server/.venv/bin/python" >&2
    exit 1
fi

if [[ ! -x "$PHP_PATH" ]]; then
    echo "PHP executable not found: $PHP_PATH" >&2
    exit 1
fi

escape_sed_replacement() {
    printf '%s' "$1" | sed 's/[&|]/\\&/g'
}

ROOT_ESCAPED="$(escape_sed_replacement "$PROJECT_ROOT")"
USER_ESCAPED="$(escape_sed_replacement "$SERVICE_USER")"
GROUP_ESCAPED="$(escape_sed_replacement "$SERVICE_GROUP")"
PHP_ESCAPED="$(escape_sed_replacement "$PHP_PATH")"

for service in document-interrogation-mcp document-interrogation-queue; do
    sed \
        -e "s|__PROJECT_ROOT__|$ROOT_ESCAPED|g" \
        -e "s|__SERVICE_USER__|$USER_ESCAPED|g" \
        -e "s|__SERVICE_GROUP__|$GROUP_ESCAPED|g" \
        -e "s|__PHP_PATH__|$PHP_ESCAPED|g" \
        "$SCRIPT_DIR/$service.service" > "/etc/systemd/system/$service.service"
done

systemctl daemon-reload
systemctl enable --now document-interrogation-mcp.service document-interrogation-queue.service
systemctl --no-pager --full status document-interrogation-mcp.service document-interrogation-queue.service || true
