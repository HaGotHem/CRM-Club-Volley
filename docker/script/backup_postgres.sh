#!/bin/bash

DATE=$(date +%Y-%m-%d_%H-%M)
BACKUP_DIR="./backups"
LOGFILE="./logs/backup.log"
CONTAINER="database"
DB_USER="appuser"
DB_NAME="appdb"

mkdir -p $BACKUP_DIR
mkdir -p ./logs

echo "[$DATE] D??but sauvegarde PostgreSQL..." >> $LOGFILE

docker exec $CONTAINER pg_dump -U $DB_USER $DB_NAME > $BACKUP_DIR/backup_$DATE.sql

if [ $? -eq 0 ]; then
    echo "[$DATE] Sauvegarde OK : backup_$DATE.sql" >> $LOGFILE
else
    echo "[$DATE] ERREUR sauvegarde" >> $LOGFILE
fi

# Rotation : garder 7 jours
find $BACKUP_DIR -type f -mtime +7 -delete

###############################################


# 2??me version (avec option suppl??mentaire)

BACKUP_DIR="/var/backups/mon_app"
DEFAULT_INCLUDE="/etc /var/www /var/lib/mon_app"
EXCLUDE_FILE="/etc/backup_exclude.lst"
SNAPSHOT_FILE="$BACKUP_DIR/backup.snar"
LOG_FILE="/var/log/backup.log"

mkdir -p "$BACKUP_DIR"

log() {
  echo "$(date '+%Y-%m-%d %H:%M:%S') [BACKUP] $*" | tee -a "$LOG_FILE"
}

echo "=== SAUVEGARDE ==="
echo "1) Compl??te"
echo "2) Incr??mentale"
read -r -p "Choix : " TYPE

read -r -p "Dossiers ?? sauvegarder (d??faut : $DEFAULT_INCLUDE) : " INC
INC=${INC:-$DEFAULT_INCLUDE}

DATE=$(date '+%Y%m%d-%H%M%S')
NAME="backup-$DATE"
TAR="$BACKUP_DIR/$NAME.tar.gz"

EXCL=()
[ -f "$EXCLUDE_FILE" ] && EXCL=(--exclude-from="$EXCLUDE_FILE")

if [ "$TYPE" = "2" ]; then
  TAR_CMD=(tar -cpz --listed-incremental="$SNAPSHOT_FILE" "${EXCL[@]}" -f "$TAR" $INC)
else
  rm -f "$SNAPSHOT_FILE"
  TAR_CMD=(tar -cpz --listed-incremental="$SNAPSHOT_FILE" "${EXCL[@]}" -f "$TAR" $INC)
fi

echo "Activer le chiffrement ? (o/N)"
read -r ENC

if [[ "$ENC" =~ ^[oOyY]$ ]]; then
  read -r -s -p "Mot de passe : " PASS
  echo
  TMP="$TAR.tmp"
  "${TAR_CMD[@]}"
  echo "$PASS" | openssl enc -aes-256-cbc -salt -pass stdin -in "$TAR" -out "$TMP"
  mv "$TMP" "$TAR.enc"
  rm -f "$TAR"
  TAR="$TAR.enc"
else
  "${TAR_CMD[@]}"
fi

META="$BACKUP_DIR/$NAME.meta"
{
  echo "name=$NAME"
  echo "file=$TAR"
  echo "date=$DATE"
  echo "type=$TYPE"
  echo "includes=$INC"
  echo "encrypted=$ENC"
} > "$META"

log "Sauvegarde termin??e : $NAME"
echo "Backup cr???? : $NAME"
