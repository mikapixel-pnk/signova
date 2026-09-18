#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"
BACKUP_RUNNER="${ROOT_DIR}/scripts/backup-staging.sh"
ENV_FILE="/etc/signova/migration.env"

MODE="${1:-status}"

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

case "$MODE" in
    status|apply)
        ;;
    *)
        die "Usage: $0 {status|apply}"
        ;;
esac

[[ -f "$ENV_FILE" ]] \
    || die "Missing migration credential file: $ENV_FILE"

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

required_vars=(
    MIGRATION_DB_CONNECTION
    MIGRATION_DB_HOST
    MIGRATION_DB_PORT
    MIGRATION_DB_DATABASE
    MIGRATION_DB_USERNAME
    MIGRATION_DB_PASSWORD
)

for var_name in "${required_vars[@]}"; do
    [[ -n "${!var_name:-}" ]] \
        || die "Required variable is empty: ${var_name}"
done

[[ "$MIGRATION_DB_CONNECTION" == "pgsql_migration" ]] \
    || die "MIGRATION_DB_CONNECTION must be pgsql_migration"

[[ "$MIGRATION_DB_USERNAME" == "signova_migrator" ]] \
    || die "Migration DB user must be signova_migrator"

[[ "$MIGRATION_DB_DATABASE" == "signova_stg" ]] \
    || die "Migration runner is restricted to signova_stg"

cd "$BACKEND_DIR"

# Login tetap signova_migrator, tetapi seluruh koneksi Laravel
# untuk operasi schema harus berjalan sebagai owner role
# signova_migration.
#
# Jangan export PGOPTIONS secara global karena backup runner
# menggunakan kredensial/role yang berbeda.
MIGRATION_PGOPTIONS="${PGOPTIONS:+$PGOPTIONS }-c role=signova_migration"

export APP_CONFIG_CACHE="/tmp/signova-migration-config-$$.php"

cleanup() {
    rm -f "$APP_CONFIG_CACHE"
}

trap cleanup EXIT

printf '\n=== MIGRATION CONNECTION PREFLIGHT ===\n'

PGOPTIONS="$MIGRATION_PGOPTIONS" \
php artisan tinker --execute="
\$connection = DB::connection('pgsql_migration');

\$row = \$connection->selectOne(
    \"
    SELECT
        current_user,
        session_user,
        current_database() AS database_name,
        pg_has_role(
            session_user,
            'signova_migration',
            'MEMBER'
        ) AS member_of_migration
    \"
);

echo 'current_user=' . \$row->current_user . PHP_EOL;
echo 'session_user=' . \$row->session_user . PHP_EOL;
echo 'database=' . \$row->database_name . PHP_EOL;
echo 'member_of_migration='
    . (\$row->member_of_migration ? 'true' : 'false')
    . PHP_EOL;

if (\$row->session_user !== 'signova_migrator') {
    throw new RuntimeException(
        'Unexpected migration session user.'
    );
}

if (\$row->current_user !== 'signova_migration') {
    throw new RuntimeException(
        'Migration owner role was not activated.'
    );
}

if (\$row->database_name !== 'signova_stg') {
    throw new RuntimeException(
        'Unexpected migration database.'
    );
}

if (! \$row->member_of_migration) {
    throw new RuntimeException(
        'signova_migrator is not a member of signova_migration.'
    );
}
"

printf '\n=== MIGRATION STATUS ===\n'

PGOPTIONS="$MIGRATION_PGOPTIONS" \
php artisan migrate:status \
    --database=pgsql_migration

if [[ "$MODE" == "status" ]]; then
    printf '\nStatus completed. No migration was executed.\n'
    exit 0
fi

[[ -x "$BACKUP_RUNNER" ]] \
    || die "Backup runner is not executable: $BACKUP_RUNNER"

printf '\n=== PRE-MIGRATION BACKUP ===\n'

"$BACKUP_RUNNER"

printf '\n=== APPLY MIGRATIONS ===\n'

PGOPTIONS="$MIGRATION_PGOPTIONS" \
php artisan migrate \
    --database=pgsql_migration \
    --force

printf '\n=== POST-MIGRATION STATUS ===\n'

PGOPTIONS="$MIGRATION_PGOPTIONS" \
php artisan migrate:status \
    --database=pgsql_migration

printf '\nMigration apply completed successfully.\n'
