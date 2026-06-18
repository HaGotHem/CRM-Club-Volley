#!/bin/sh
set -e

# Attendre que le service PostgreSQL soit pr??t (connexion ?? la base par d??faut 'postgres')
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "postgres" -c '\q' > /dev/null 2>&1; do
  echo "En attente du service PostgreSQL..."
  sleep 2
done

# Cr??er la base de donn??es si elle n'existe pas
DB_EXISTS=$(PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "postgres" -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'")
if [ "$DB_EXISTS" != "1" ]; then
  echo "La base de donn??es $DB_NAME n'existe pas. Cr??ation..."
  PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "postgres" -c "CREATE DATABASE $DB_NAME"
fi

# V??rifier si la base de donn??es est vide (pas de tables)
TABLE_COUNT=$(PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';")
TABLE_COUNT=$(echo $TABLE_COUNT | xargs)

if [ "$TABLE_COUNT" = "0" ]; then
  echo "La base de donn??es est vide. Initialisation..."
  
  if [ -f "/var/www/html/sql/init_database.sql" ]; then
    echo "Injection de sql/init_database.sql..."
    PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/sql/init_database.sql
    
    if [ -f "/var/www/html/sql/data_seed.sql" ]; then
      echo "Injection de sql/data_seed.sql..."
      PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/sql/data_seed.sql
    fi
  else
    echo "Erreur: sql/init_database.sql introuvable."
  fi
else
  echo "La base de donn??es n'est pas vide ($TABLE_COUNT tables trouv??es). Saut de l'initialisation."
fi

# Lancer Nginx et PHP-FPM (commande d'origine du Dockerfile)
nginx
php-fpm
