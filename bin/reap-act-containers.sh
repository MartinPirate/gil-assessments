#!/usr/bin/env bash
#
# Remove leaked `act` (local GitHub Actions) containers and their volumes.
#
# act normally cleans up after itself; when a run is interrupted it does not,
# and the leftovers sit there holding CPU and memory. Enough of them will
# starve other containers — SQL Server in particular stops answering its
# pre-login handshake, which surfaces in the app as
# "Maximum execution time exceeded" with no query ever sent.
#
# Only act-* resources are touched. Databases and long-running dev services
# are left alone.
#
# Usage:
#   bin/reap-act-containers.sh          # remove them
#   bin/reap-act-containers.sh --dry    # list what would be removed

set -uo pipefail

DRY_RUN=false
[[ "${1:-}" == "--dry" || "${1:-}" == "--dry-run" ]] && DRY_RUN=true

if ! docker info >/dev/null 2>&1; then
    echo "Docker is not running."
    exit 1
fi

containers=$(docker ps -aq --filter "name=act-" 2>/dev/null)
volumes=$(docker volume ls -q 2>/dev/null | grep '^act-' || true)

container_count=$(printf '%s' "$containers" | grep -c . || true)
volume_count=$(printf '%s' "$volumes" | grep -c . || true)

echo "Found ${container_count} act container(s) and ${volume_count} act volume(s)."

if [[ "$container_count" -eq 0 && "$volume_count" -eq 0 ]]; then
    echo "Nothing to do."
    exit 0
fi

if [[ "$DRY_RUN" == true ]]; then
    echo
    echo "Would remove these containers:"
    docker ps -a --filter "name=act-" --format '  {{.Names}}  ({{.Status}})'
    exit 0
fi

# Removed in parallel: the daemon is slow when it is already under load, which
# is exactly the situation this script exists for.
if [[ -n "$containers" ]]; then
    for c in $containers; do docker rm -f "$c" >/dev/null 2>&1 & done
    wait
fi

# Volumes still attached to a live run will refuse to go; that is fine.
if [[ -n "$volumes" ]]; then
    for v in $volumes; do docker volume rm "$v" >/dev/null 2>&1 & done
    wait
fi

remaining=$(docker ps -aq --filter "name=act-" 2>/dev/null | grep -c . || true)

echo "Done. ${remaining} act container(s) remain (any still belong to a live run)."
echo "Containers now running: $(docker ps -q | wc -l | tr -d ' ')"
