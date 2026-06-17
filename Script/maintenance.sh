
echo "Nettoyage Docker..."
docker system prune -af

echo "Nettoyage logs..."
find ./logs -type f -mtime +14 -delete

echo "Nettoyage backups..."
find ./backups -type f -mtime +14 -delete

echo "Maintenance terminée."
