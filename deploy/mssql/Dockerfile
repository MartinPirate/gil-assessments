# syntax=docker/dockerfile:1
#
# SQL Server, adjusted to survive a platform-mounted volume.
#
# Microsoft's image drops to the unprivileged `mssql` user, whose home is not
# set. On Railway that leaves the engine trying to create its system directory
# at the filesystem root — "The system directory [/.system] could not be
# created" — and the container restarts forever. Worse, the volume arrives
# owned by root, so even with a home the data directory is unwritable.
#
# Both are fixed at start-up rather than at build time, because the volume does
# not exist until the container runs.
FROM mcr.microsoft.com/mssql/server:2022-latest

USER root

ENV HOME=/var/opt/mssql

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
