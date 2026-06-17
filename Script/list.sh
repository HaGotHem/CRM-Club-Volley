#!/usr/bin/env bash

BACKUP_DIR="/var/backups/mon_app"

echo "=== LISTE DES BACKUPS ==="
ls -1 "$BACKUP_DIR" | grep ".meta" | sed 's/.meta//'

echo
read -r -p "Afficher les détails d’un backup ? (nom ou vide) : " NAME

if [ -n "$NAME" ]; then
  META="$BACKUP_DIR/$NAME.meta"
  if [ -f "$META" ]; then
    echo "=== DÉTAILS ==="
    cat "$META"
  else
    echo "Backup introuvable"
  fi
fi
