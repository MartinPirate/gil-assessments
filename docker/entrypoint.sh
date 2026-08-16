#!/bin/sh
#
# Everything that has to happen against a *running* database, rather than at
# build time when there isn't one.
set -e

# Railway hands the port in at runtime; FrankenPHP is told to listen on it
# rather than on a number baked into the image.
: "${PORT:=8080}"

# A missing key is a 500 on every page with nothing in the log to explain it,
# so say so plainly instead.
if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY is not set. Generate one with 'php artisan key:generate --show' and set it in the service variables." >&2
    exit 1
fi

# A fresh SQL Server container comes up with master and nothing else, and it
# takes the best part of a minute to answer at all. Both are handled here:
# wait for it, then create the database if this is the first boot. Without
# this, the first deploy fails on "Cannot open database" and looks like a
# configuration mistake rather than a cold start.
php -r '
$host = getenv("DB_HOST") ?: "localhost";
$port = getenv("DB_PORT") ?: "1433";
$name = getenv("DB_DATABASE") ?: "gil_assessment";
$user = getenv("DB_USERNAME") ?: "sa";
$pass = getenv("DB_PASSWORD") ?: "";
$dsn  = sprintf("sqlsrv:Server=%s,%s;Database=master;TrustServerCertificate=1", $host, $port);

$deadline = time() + 180;
$attempt = 0;

while (true) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Quoted as an identifier, not interpolated into a string literal:
        // the name comes from the environment, and this runs as sa.
        $safe = str_replace("]", "]]", $name);
        $pdo->exec("IF DB_ID(" . $pdo->quote($name) . ") IS NULL CREATE DATABASE [{$safe}]");
        fwrite(STDERR, "database {$name} is ready\n");
        exit(0);
    } catch (PDOException $e) {
        if (time() >= $deadline) {
            fwrite(STDERR, "could not reach SQL Server at {$host}:{$port} after 180s: " . $e->getMessage() . "\n");
            exit(1);
        }
        $attempt++;
        fwrite(STDERR, "waiting for SQL Server ({$attempt})\n");
        sleep(5);
    }
}
'

php artisan migrate --force --no-interaction

# Cached at boot rather than in the image: the config cache bakes in
# environment variables, and those are only known once the service starts.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

# The public disk holds invoice PDFs, driver licences and vehicle photographs.
php artisan storage:link || true

exec frankenphp php-server --root /app/public --listen ":${PORT}"
