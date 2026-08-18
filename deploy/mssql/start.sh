#!/bin/bash
# The volume is mounted by the platform after the image is built, so ownership
# is corrected here — on every boot, cheaply, rather than assumed.
set -e

mkdir -p /var/opt/mssql/data /var/opt/mssql/log /var/opt/mssql/secrets

# The platform volume is network-backed, and SQL Server assumes it is not.
# Left alone the engine opens its data files write-through and expects sector
# aligned IO back; the mount cannot give it that, so master.mdf comes up on
# "misaligned log IOs ... falling back to synchronous IO" and sqlpal dies on
# errno 22 before the instance ever accepts a connection. Turning both flush
# paths off is Microsoft's documented answer for storage of this kind.
#
# The engine also reads the *host* when sizing itself — 48 processors and
# 399 GB here, none of which the container is entitled to — so the buffer pool
# is capped to something the service can actually be given.
cat > /var/opt/mssql/mssql.conf <<'CONF'
[control]
writethrough = 0
alternatewritethrough = 0

[memory]
memorylimitmb = 2048
CONF

chown -R mssql:root /var/opt/mssql
chmod -R 0770 /var/opt/mssql

export HOME=/var/opt/mssql

exec /opt/mssql/bin/sqlservr
