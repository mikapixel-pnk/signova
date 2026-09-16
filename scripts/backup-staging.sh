#!/usr/bin/env bash
set -Eeuo pipefail

ENV_FILE="/etc/signova/backup.env"

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[[ -f "$ENV_FILE" ]] \
    || die "Missing backup credential file: $ENV_FILE"

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

required_vars=(
    BACKUP_DB_HOST
    BACKUP_DB_PORT
    BACKUP_DB_DATABASE
    BACKUP_DB_USERNAME
    BACKUP_DB_PASSWORD
    BACKUP_DIR
)

for var_name in "${required_vars[@]}"; do
    [[ -n "${!var_name:-}" ]] \
        || die "Required variable is empty: ${var_name}"
done

[[ "$BACKUP_DB_DATABASE" == "signova_stg" ]] \
    || die "Backup runner is restricted to signova_stg"

[[ "$BACKUP_DB_USERNAME" == "signova_backup" ]] \
    || die "Backup DB user must be signova_backup"

export PGPASSWORD="$BACKUP_DB_PASSWORD"

cleanup() {
    unset PGPASSWORD
}

trap cleanup EXIT

printf '\n=== BACKUP CONNECTION PREFLIGHT ===\n'

identity="$(
    psql \
      -h "$BACKUP_DB_HOST" \
      -p "$BACKUP_DB_PORT" \
      -U "$BACKUP_DB_USERNAME" \
      -d "$BACKUP_DB_DATABASE" \
      -At \
      -v ON_ERROR_STOP=1 \
      -c "
SELECT
    current_user || '|' ||
    current_database();
"
)"

[[ "$identity" == "signova_backup|signova_stg" ]] \
    || die "Unexpected backup database identity: $identity"

printf 'identity=%s\n' "$identity"

DAY_DIR="$(
    date +'%Y/%m/%d'
)"

STAMP="$(
    date +'%Y%m%d-%H%M%S'
)"

TARGET_DIR="${BACKUP_DIR}/${DAY_DIR}"
BASENAME="signova_stg-${STAMP}"

mkdir -p "$TARGET_DIR"

TMP_DUMP="${TARGET_DIR}/.${BASENAME}.dump.tmp"
FINAL_DUMP="${TARGET_DIR}/${BASENAME}.dump"
FINAL_SHA="${FINAL_DUMP}.sha256"
FINAL_META="${TARGET_DIR}/${BASENAME}.metadata.txt"

cleanup_files() {
    rm -f "$TMP_DUMP"
}

trap 'cleanup_files; cleanup' EXIT

printf '\n=== CREATE BACKUP ===\n'

pg_dump \
  -h "$BACKUP_DB_HOST" \
  -p "$BACKUP_DB_PORT" \
  -U "$BACKUP_DB_USERNAME" \
  -d "$BACKUP_DB_DATABASE" \
  -Fc \
  -f "$TMP_DUMP"

[[ -s "$TMP_DUMP" ]] \
    || die "Backup dump is empty"

printf '\n=== VERIFY BACKUP ===\n'

pg_restore \
  --list \
  "$TMP_DUMP" \
  >/dev/null

mv \
  "$TMP_DUMP" \
  "$FINAL_DUMP"

sha256sum \
  "$FINAL_DUMP" \
  > "$FINAL_SHA"

{
    printf 'created_at=%s\n' "$(date -Is)"
    printf 'database=%s\n' "$BACKUP_DB_DATABASE"
    printf 'database_user=%s\n' "$BACKUP_DB_USERNAME"
    printf 'host=%s\n' "$BACKUP_DB_HOST"
    printf 'format=postgresql-custom\n'
    printf 'pg_dump=%s\n' "$(pg_dump --version)"
    printf 'size_bytes=%s\n' "$(stat -c '%s' "$FINAL_DUMP")"
    printf 'sha256=%s\n' "$(sha256sum "$FINAL_DUMP" | awk '{print $1}')"
} > "$FINAL_META"

chmod \
  0640 \
  "$FINAL_DUMP" \
  "$FINAL_SHA" \
  "$FINAL_META"

printf '\n=== BACKUP COMPLETE ===\n'
printf 'dump=%s\n' "$FINAL_DUMP"
printf 'sha256=%s\n' "$FINAL_SHA"
printf 'metadata=%s\n' "$FINAL_META"
