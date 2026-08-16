#!/bin/bash
# The volume is mounted by the platform after the image is built, so ownership
# is corrected here — on every boot, cheaply, rather than assumed.
set -e

mkdir -p /var/opt/mssql/data /var/opt/mssql/log /var/opt/mssql/secrets
chown -R mssql:root /var/opt/mssql
chmod -R 0770 /var/opt/mssql

export HOME=/var/opt/mssql

exec /opt/mssql/bin/sqlservr
