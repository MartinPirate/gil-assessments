#!/bin/bash
# SQL Server cannot run its data files on the platform volume: the mount is a
# network-backed zvol whose IO alignment the engine will not accept — it comes
# up on "misaligned log IOs" and sqlpal dies on errno 22 no matter which
# write-through flags are set (verified 24 Aug 2026; the writethrough=0 fix
# only ever *looked* like it worked because the volume had silently stopped
# mounting). So durability is inverted: the engine runs on local disk, where
# it is stable, and the volume holds backups instead.
#
#   - on boot, the newest backup on the volume is restored automatically,
#     so a restart heals itself without anyone redeploying the app
#   - a loop takes a fresh backup every few minutes; the loss window is
#     BACKUP_INTERVAL_SECONDS at worst
set -e

DB_NAME="${DB_DATABASE:-gil_assessment}"
BACKUP_DIR=/var/opt/mssql/backup
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}.bak"
BACKUP_INTERVAL_SECONDS="${BACKUP_INTERVAL_SECONDS:-300}"
SQLCMD=(/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "${MSSQL_SA_PASSWORD}" -C -b)

mkdir -p /var/opt/mssql/data /var/opt/mssql/log /var/opt/mssql/secrets "${BACKUP_DIR}"

# The engine reads the *host* when sizing itself — 48 processors and 399 GB
# that the container is not entitled to — so the buffer pool is capped to
# something the service can actually be given.
cat > /var/opt/mssql/mssql.conf <<'CONF'
[memory]
memorylimitmb = 2048
CONF

chown -R mssql:root /var/opt/mssql
chmod -R 0770 /var/opt/mssql

export HOME=/var/opt/mssql

/opt/mssql/bin/sqlservr &
SQLSERVR_PID=$!

# Restore and backup both need a running engine, so everything below waits on
# it first. Failures in this half must never kill the engine itself — the app
# can still recreate the database from seeds — hence no `set -e` semantics
# past this point: each step reports and carries on.
(
    set +e
    for _ in $(seq 1 60); do
        "${SQLCMD[@]}" -Q "SELECT 1" >/dev/null 2>&1 && break
        sleep 5
    done
    if ! "${SQLCMD[@]}" -Q "SELECT 1" >/dev/null 2>&1; then
        echo "backup agent: engine never became ready; giving up" >&2
        exit 1
    fi

    # Restore only when the database is missing and a backup exists. If the
    # app booted first and already created it, the live copy wins.
    HAS_DB=$("${SQLCMD[@]}" -h -1 -W -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM sys.databases WHERE name = '${DB_NAME}'" 2>/dev/null | tr -dc '0-9')
    if [ "${HAS_DB}" = "0" ] && [ -f "${BACKUP_FILE}" ]; then
        echo "restoring ${DB_NAME} from ${BACKUP_FILE}" >&2
        if "${SQLCMD[@]}" -Q "RESTORE DATABASE [${DB_NAME}] FROM DISK = N'${BACKUP_FILE}' WITH REPLACE, RECOVERY" >&2; then
            echo "restore of ${DB_NAME} complete" >&2
        else
            echo "restore of ${DB_NAME} FAILED; the app will reseed from scratch" >&2
        fi
    fi

    # WITH INIT overwrites in place, and the copy+move keeps a torn write from
    # destroying the only good backup: the previous .bak survives until the
    # new one is fully on disk.
    while true; do
        sleep "${BACKUP_INTERVAL_SECONDS}"
        HAS_DB=$("${SQLCMD[@]}" -h -1 -W -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM sys.databases WHERE name = '${DB_NAME}'" 2>/dev/null | tr -dc '0-9')
        if [ "${HAS_DB}" != "1" ]; then
            echo "backup agent: ${DB_NAME} does not exist yet; skipping this cycle" >&2
            continue
        fi
        if "${SQLCMD[@]}" -Q "BACKUP DATABASE [${DB_NAME}] TO DISK = N'${BACKUP_FILE}.new' WITH INIT, COMPRESSION" >/dev/null 2>&1; then
            mv -f "${BACKUP_FILE}.new" "${BACKUP_FILE}"
            echo "backup agent: ${DB_NAME} backed up" >&2
        else
            echo "backup agent: BACKUP DATABASE failed; will retry next cycle" >&2
            rm -f "${BACKUP_FILE}.new"
        fi
    done
) &

wait "${SQLSERVR_PID}"
