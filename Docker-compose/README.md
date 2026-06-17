# Infrastructure Docker 

## Présentation

Cette infrastructure contient :
- Reverse proxy Nginx (HTTP/HTTPS)
 Application web (PHP/Node/Laravel/Slim)
- PostgreSQL 16
- Redis 7
- Adminer
- Portainer
- Scripts de sauvegarde et maintenance

---

## Architecture réseau
project/
│
|--> app/
│   |--> Dockerfile
│   |--> src
|   |--> public
│   
|--> proxy/
│   |--> nginx.conf
│   |--> conf.d/
│       |--> webapp.conf
│       |-->webapp-ssl.conf
│       |--> ssl/
│           |--> fullchain.pem
│           |-->privkey.pem
│
|--> scripts/
│   |--> backup_postgres.sh
│   |--> restore_postgres.sh
│   |--> maintenance.sh
|   |--> list.sh
│
|--> backups/
│
|--> logs/
│   |--> nginx/
│   |--> app/
│   |--> sync/
│
|--> docker-compose.yml
|-->.env
|--> .gitignore


## Réseau frontend
- reverse-proxy
- web-app

## Réseau backend
- web-app
- db
- redis
- adminer
- portainer

Isolation stricte pour la sécurité.

---

## Sécurité

## Variables sensibles
Stockées dans `.env` (non versionné).

## Certificats SSL
Stockés dans `proxy/conf.d/ssl/` (non versionnés).

## Pare-feu recommandé
- Autoriser : 80, 443, 9443
- Bloquer : 5432 (PostgreSQL)

---

## Certificats SSL
Stockés dans `proxy/conf.d/ssl/` :

- `fullchain.pem`
- `privkey.pem`

## Pare-feu recommandé


## Sauvegardes PostgreSQL

## Script
`scripts/backup_postgres.sh`

## Restauration
`scripts/restore_postgres.sh fichier.sql`



## Maintenance 

Fonctions :
- Nettoyage Docker
- Nettoyage logs > 7 jours
- Nettoyage backups > 7 jours

---

## Tests SISR

- Test de restauration PostgreSQL  
- Vérification des droits `.env`  
- Vérification des certificats SSL  
- Vérification de l’isolation réseau Docker  
- Vérification des logs Nginx  
- Vérification de la rotation des sauvegardes  

---

## Responsabilités

- Certificats SSL → équipe SISR  
- Clés API → équipe SISR / DEV  
- Conformité MERISE → équipe MERISE  


