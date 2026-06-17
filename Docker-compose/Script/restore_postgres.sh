
CONTAINER="database"
DB_USER="appuser"
DB_NAME="appdb"
FILE=$1

if [ -z "$FILE" ]; then
    echo "Usage: ./restore_postgres.sh fichier.sql"
    exit 1
fi

docker exec -i $CONTAINER psql -U $DB_USER -d $DB_NAME < $FILE




#########################################################

# 2ème version (option de restauration complète ou partielle)
BACKUP_DIR="/var/backups/mon_app"
LOG_FILE="/var/log/restore.log"

log() {
  echo "$(date '+%Y-%m-%d %H:%M:%S') [RESTORE] $*" | tee -a "$LOG_FILE"
}

echo "=== RESTAURATION ==="
ls -1 "$BACKUP_DIR" | grep ".meta" | sed 's/.meta//'

read -r -p "Nom du backup : " NAME
META="$BACKUP_DIR/$NAME.meta"

if [ ! -f "$META" ]; then
  echo "Backup introuvable"
  exit 1
fi

source <(sed 's/^/export /' "$META")

FILE="$file"

if [[ "$encrypted" =~ ^[oOyY]$ ]]; then
  read -r -s -p "Mot de passe : " PASS
  echo
  TMP=$(mktemp /tmp/restore.XXXXXX.tar.gz)
  echo "$PASS" | openssl enc -d -aes-256-cbc -pass stdin -in "$FILE" -out "$TMP"
  FILE="$TMP"
fi

echo "1) Restauration complète"
echo "2) Restauration partielle"
read -r -p "Choix : " MODE

read -r -p "Dossier cible (défaut /) : " TARGET
TARGET=${TARGET:-/}

if [ "$MODE" = "1" ]; then
  tar -xpz -f "$FILE" -C "$TARGET"
  log "Restauration complète effectuée"
  echo "Restauration complète OK"
else
  read -r -p "Chemin à restaurer (ex: etc/mon_app) : " PATH
  tar -xpz -f "$FILE" -C "$TARGET" "$PATH"
  log "Restauration partielle : $PATH"
  echo "Restauration partielle OK"
fi

[ -n "$TMP" ] && rm -f "$TMP"
