#!/bin/sh
set -e

# Attendre que le service PostgreSQL soit prêt (connexion à la base par défaut 'postgres')
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "postgres" -c '\q' > /dev/null 2>&1; do
  echo "En attente du service PostgreSQL..."
  sleep 2
done

# Créer la base de données si elle n'existe pas
DB_EXISTS=$(PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "postgres" -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'")
if [ "$DB_EXISTS" != "1" ]; then
  echo "La base de données $DB_NAME n'existe pas. Création..."
  PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "postgres" -c "CREATE DATABASE $DB_NAME"
fi

# Vérifier si la base de données est vide (pas de tables)
TABLE_COUNT=$(PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';")
TABLE_COUNT=$(echo $TABLE_COUNT | xargs)

if [ "$TABLE_COUNT" = "0" ]; then
  echo "La base de données est vide. Initialisation..."
  
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
  echo "La base de données n'est pas vide ($TABLE_COUNT tables trouvées). Saut de l'initialisation."
fi

# Lancer Nginx et PHP-FPM (commande d'origine du Dockerfile)
nginx
php-fpm
