#!/usr/bin/env bash
#
# Deploy the portal on the server: pull the latest code, rebuild the app container,
# run migrations, clear caches, and make sure a queue worker is running.
#
# Usage (from the project folder on the server):
#   ./deploy.sh                 pull the current branch and deploy
#   ./deploy.sh main            switch to and deploy another branch
#   SKIP_BUILD=1 ./deploy.sh    skip the image rebuild and only rerun migrations, cache
#                               clearing and the queue worker (the code lives in the image,
#                               so new code needs a rebuild)
#
set -euo pipefail

cd "$(dirname "$0")"

SERVICE="${SERVICE:-app}"
QUEUE_COMMAND="php artisan queue:work redis --queue=ai,default --tries=2 --timeout=120 --sleep=1"
BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"

step() {
    printf '\n\033[1;34m==> %s\033[0m\n' "$1"
}

artisan() {
    docker compose exec -T "$SERVICE" php artisan "$@"
}

step "Mengambil kode terbaru (branch ${BRANCH})"
git fetch --prune origin
if [ "$(git rev-parse --abbrev-ref HEAD)" != "$BRANCH" ]; then
    git checkout "$BRANCH"
fi
BEFORE="$(git rev-parse --short HEAD)"
git pull --ff-only origin "$BRANCH"
AFTER="$(git rev-parse --short HEAD)"
if [ "$BEFORE" = "$AFTER" ]; then
    echo "Tidak ada commit baru (${AFTER})."
else
    git log --oneline "${BEFORE}..${AFTER}"
fi

if [ "${SKIP_BUILD:-0}" != "1" ]; then
    step "Build ulang dan menjalankan container ${SERVICE}"
    docker compose up -d --build "$SERVICE"
fi

step "Menunggu aplikasi siap"
for attempt in $(seq 1 30); do
    if docker compose exec -T "$SERVICE" php artisan --version >/dev/null 2>&1; then
        break
    fi
    if [ "$attempt" -eq 30 ]; then
        echo "Container ${SERVICE} belum siap setelah 60 detik. Cek: docker compose logs ${SERVICE}" >&2
        exit 1
    fi
    sleep 2
done

step "Menjalankan migration"
artisan migrate --force

step "Membersihkan cache"
artisan optimize:clear

step "Memulai ulang worker antrean (AI matching, notifikasi, email)"
# Ask running workers to finish their current job and stop, so the new code is loaded.
artisan queue:restart
worker_running() {
    # "wor[k]" keeps this check from matching its own command line.
    docker compose exec -T "$SERVICE" sh -c 'grep -qs "queue:wor[k]" /proc/[0-9]*/cmdline'
}
for _ in $(seq 1 10); do
    worker_running || break
    sleep 2
done
if worker_running; then
    echo "Worker lama masih menyelesaikan satu job. Setelah selesai, jalankan: SKIP_BUILD=1 ./deploy.sh" >&2
else
    # shellcheck disable=SC2086
    docker compose exec -d "$SERVICE" $QUEUE_COMMAND
    echo "Worker dijalankan: ${QUEUE_COMMAND}"
fi

step "Cek halaman login"
STATUS="$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8000/login || true)"
if [ "$STATUS" = "200" ]; then
    echo "OK: portal merespons (HTTP 200)."
else
    echo "Peringatan: halaman login merespons HTTP ${STATUS}. Cek: docker compose logs --tail=100 ${SERVICE}" >&2
fi

step "Selesai: ${AFTER} sudah ter-deploy"
