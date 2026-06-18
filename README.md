# CRM Club Volley - Application de Gestion

Bienvenue sur le projet CRM du Club de Volley ! Ce projet est une application web basée sur le framework **Slim 4**, conçue pour gérer les contacts, les segments et les interactions avec les APIs de **Weezevent** et **Brevo**.

## 🚀 Déploiement

Le projet utilise Docker pour simplifier l'installation de l'environnement (PHP, Nginx, PostgreSQL).

### Premier lancement
1. Clonez le dépôt.
2. Créez un fichier `.env` à la racine (copiez `.env.example`).
3. Lancez les conteneurs :
   ```bash
   docker compose up --build
   ```

### Réinitialisation complète
Si vous avez une ancienne version et que vous voulez repartir sur une base propre (pour réimporter la base de données automatiquement) :
1. Arrêtez les conteneurs et supprimez les volumes :
   ```bash
   docker compose down -v
   ```
2. Relancez le tout :
   ```bash
   docker compose up --build
   ```
*Note : Le dossier `sql/` contient les scripts d'initialisation qui sont exécutés automatiquement lors de la création du conteneur de base de données.*

---

## 🏗️ Structure du Projet

L'architecture suit les standards modernes de PHP (PSR-4) et sépare les responsabilités :

### 📂 `src/Models/`
Ce sont nos **Objets Métiers**. Chaque classe (ex: `Contact`, `Segment`) représente une entité de notre base de données. 
- Elles possèdent des propriétés typées.
- Elles utilisent une méthode static `fromArray(array $data)` pour se construire facilement à partir d'un résultat SQL.

### 📂 `src/Repositories/`
C'est ici que se trouve toute la **logique SQL**. 
- Les Repositories font le lien entre la base de données et les Modèles.
- **Règle d'or** : On n'écrit jamais de SQL dans les routes, on appelle une méthode du Repository (ex: `$contactRepo->findAll()`).
- Toutes les requêtes sont **préparées** pour éviter les injections SQL.

### 📂 `public/routes/`
Ce sont nos **Contrôleurs**. Ils définissent les points d'entrée de l'API et de l'application.
- Ils reçoivent la requête HTTP.
- Ils appellent les Repositories ou les Services.
- Ils retournent une réponse (JSON ou Vue Twig).

### 📂 `src/Services/`
Contient la logique d'interaction avec les APIs externes (**BrevoService**, **WeezeventService**).

---

## 🎨 Système de Vues (Twig)

Nous utilisons **Twig** comme moteur de template. Cela permet de séparer le code PHP du code HTML.

- **Templates** : Situés dans le dossier `/templates`.
- **Héritage** : Le fichier `layouts/base.html.twig` définit la structure commune (sidebar, navbar). Les autres pages (ex: `pages/dashboard.html.twig`) "étendent" ce layout :
  ```twig
  {% extends "layouts/base.html.twig" %}
  {% block content %}
     <!-- Mon contenu spécifique -->
  {% endblock %}
  ```
- **Variables** : Les données passées depuis PHP sont accessibles via `{{ ma_variable }}`.

---

## 🛠️ À venir : Weezevent & Brevo

Les prochaines étapes consisteront à mixer notre base locale avec les données externes :
1. **Front-end** : Appels AJAX vers nos routes `/api/...` pour afficher les statistiques en temps réel.
2. **Back-end** : Synchronisation automatique des participants Weezevent vers notre CRM, puis vers nos listes Brevo pour l'envoi de newsletters.

---

## 📜 Commandes Utiles

- **Installer des dépendances** : `docker compose exec web-app composer install`
- **Mettre à jour l'autoloader** : `docker compose exec web-app composer dump-autoload`
- **Logs en temps réel** : `docker compose logs -f`
- **Accès à l'application** : `http://localhost`
- **Accès à Adminer (gestion BDD)** : `http://localhost:8081`
