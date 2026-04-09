#!/usr/bin/env bash
# ==============================================================================
#  moodle_export.sh — parte del plugin local_reportcsv
#  Uso: ./moodle_export.sh /percorso/al/file.sql
#  Il path del .env viene passato tramite variabile REPORTCSV_ENV_FILE
#  (impostata automaticamente dal plugin nel crontab).
# ==============================================================================

set -euo pipefail

SQL_FILE="${1:-}"

if [[ -z "$SQL_FILE" || ! -f "$SQL_FILE" ]]; then
    echo "ERRORE: File SQL non trovato: ${SQL_FILE:-<non specificato>}"
    echo "Uso: $0 /percorso/al/file.sql"
    exit 1
fi

# ------------------------------------------------------------------------------
# RICERCA FILE .env
# ------------------------------------------------------------------------------
ENV_FILE="${REPORTCSV_ENV_FILE:-}"

if [[ -z "$ENV_FILE" || ! -f "$ENV_FILE" ]]; then
    echo "ERRORE: file .env non trovato."
    echo ""
    echo "Questo script viene normalmente lanciato dal crontab generato dal plugin,"
    echo "che imposta automaticamente la variabile REPORTCSV_ENV_FILE."
    echo ""
    echo "Per lanciarlo manualmente, usa:"
    echo "  REPORTCSV_ENV_FILE=/home/oidcdcmf/test.osel.it/.htm5gpvhgvmc9l.data/reportcsv/.env \\"
    echo "    $0 ${SQL_FILE}"
    exit 1
fi

echo "INFO: Uso file .env: ${ENV_FILE}"
# shellcheck disable=SC1090
source "$ENV_FILE"

# Verifica variabili obbligatorie
for VAR in REPORTCSV_DB_HOST REPORTCSV_DB_NAME REPORTCSV_DB_USER REPORTCSV_OUTPUT_DIR; do
    if [[ -z "${!VAR:-}" ]]; then
        echo "ERRORE: variabile ${VAR} mancante nel file .env: ${ENV_FILE}"
        exit 1
    fi
done

DB_HOST="${REPORTCSV_DB_HOST}"
DB_PORT="${REPORTCSV_DB_PORT:-3306}"
DB_NAME="${REPORTCSV_DB_NAME}"
DB_USER="${REPORTCSV_DB_USER}"
DB_PASS="${REPORTCSV_DB_PASS:-}"
DB_PREFIX="${REPORTCSV_DB_PREFIX:-mdl_}"
OUTPUT_DIR="${REPORTCSV_OUTPUT_DIR}"
LOG_FILE="${REPORTCSV_LOG_FILE:-${OUTPUT_DIR}/log/moodle_export.log}"

# ------------------------------------------------------------------------------
# INIZIALIZZAZIONE DIRECTORY
# ------------------------------------------------------------------------------
mkdir -p "${OUTPUT_DIR}/log"

# ------------------------------------------------------------------------------
# FUNZIONI
# ------------------------------------------------------------------------------
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"
}

die() {
    log "ERRORE: $*"
    exit 1
}

# ------------------------------------------------------------------------------
# CALCOLO INTERVALLO TEMPORALE (ieri)
# ------------------------------------------------------------------------------
YESTERDAY=$(date -d "yesterday" '+%Y-%m-%d')
FILE_DATE=$(date -d "yesterday" '+%d_%m_%Y')
START_TIME=$(date -d "${YESTERDAY} 00:00:00" '+%s')
END_TIME=$(date -d "${YESTERDAY} 23:59:59" '+%s')

SQL_BASENAME=$(basename "${SQL_FILE}" .sql)
OUTPUT_FILE="${OUTPUT_DIR}/report_${SQL_BASENAME}_${FILE_DATE}.csv"

log "========================================================"
log "Avvio export — query: ${SQL_BASENAME} — periodo: ${YESTERDAY}"
log "Output: ${OUTPUT_FILE}"

# ------------------------------------------------------------------------------
# VERIFICA PREREQUISITI
# ------------------------------------------------------------------------------
command -v mysql >/dev/null 2>&1 || die "mysql client non trovato nel PATH."

# ------------------------------------------------------------------------------
# COSTRUZIONE QUERY
# ------------------------------------------------------------------------------
log "Lettura query da: ${SQL_FILE}"
QUERY_TEMPLATE=$(cat "$SQL_FILE")

QUERY=$(echo "$QUERY_TEMPLATE" \
    | sed "s/%%STARTTIME%%/${START_TIME}/g" \
    | sed "s/%%ENDTIME%%/${END_TIME}/g" \
    | sed "s/{/${DB_PREFIX}/g" \
    | sed "s/}//g")

# ------------------------------------------------------------------------------
# ESECUZIONE QUERY E SCRITTURA CSV
# ------------------------------------------------------------------------------
log "Esecuzione query MySQL..."

mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --database="$DB_NAME" \
    --batch \
    --execute="$QUERY" \
    2>>"$LOG_FILE" \
| sed 's/\t/;/g' \
> "$OUTPUT_FILE"

if [[ ! -s "$OUTPUT_FILE" ]]; then
    log "ATTENZIONE: file CSV vuoto (nessun dato per ${YESTERDAY})."
else
    ROW_COUNT=$(( $(wc -l < "$OUTPUT_FILE") - 1 ))
    log "Export completato: ${ROW_COUNT} righe → ${OUTPUT_FILE}"
fi

log "Script terminato."
log "========================================================"
