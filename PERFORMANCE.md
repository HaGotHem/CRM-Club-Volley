# ⚡ Optimisation des performances — Dashboard Nice Volley Ball

Résumé complet de l'analyse et des corrections appliquées pour réduire le temps de
chargement du dashboard, passé de **~2,7 s à ~0,1 s** (≈ **25× plus rapide**).

---

## 1. Contexte initial

Le dashboard (`http://localhost:8888/dashboard`) mettait **2,71 s** à se charger.

| Métrique | Valeur initiale |
|---|---|
| TTFB page HTML (`/dashboard`) | **1 264 ms** |
| API `/api/stats/dashboard` | **444 ms** |
| Routes 404 / inexistantes | 400 – 900 ms |
| Parsing DOM | 63 ms |
| Ressources statiques | 35 ms |

**Indice clé :** même une route **inexistante (404)** prenait 400–900 ms. Le goulot
n'était donc **pas** la logique métier (SQL, HTML), mais le **coût de démarrage de
PHP à chaque requête**.

---

## 2. Causes racines (pourquoi c'était lent)

Le point commun des trois causes : **du travail répété inutilement à chaque requête**,
alors qu'il aurait dû être fait une seule fois puis mis en cache.

### 🥇 Cause principale — OPcache désactivé (~80 % du problème)

Sans OPcache, **PHP recompilait tout le code source en bytecode à CHAQUE requête**
(Slim, Twig, PSR-7, autoloader, routes… des centaines de fichiers). OPcache garde
normalement ce bytecode en mémoire et saute cette étape.

➡️ C'est ce qui explique que **les routes 404 étaient aussi lentes** : le temps était
brûlé dans le bootstrap, pas dans le traitement.

### 🥈 Cache Twig désactivé (`'cache' => false`)

La route `/dashboard` ne fait **aucune requête SQL** : elle rend juste un template.
Or Twig **recompilait le template + le layout `base.html.twig` en PHP à chaque
affichage**, et sans OPcache ce PHP régénéré n'était même pas mis en cache.

### 🥉 Autoloader non optimisé + bind-mount Windows

Le classmap Composer ne contenait que **8 stubs** (au lieu de 1 524 classes). Pour
charger chaque classe, PHP faisait une recherche fichier par fichier (`stat()`), et
ces accès disque traversaient la frontière **Windows ↔ conteneur Linux** (bind-mount),
très lente sur des milliers de petits fichiers.

```
TTFB 1264 ms  ≈  recompilation PHP totale (OPcache OFF)        ← le gros morceau
              +  recompilation des templates Twig
              +  centaines de stat() fichiers (autoloader + bind-mount lent)
```

> Les **444 ms** de l'API étaient un problème **secondaire et distinct** : mêmes coûts
> de bootstrap **+** 4 requêtes SQL séquentielles (chaque table scannée 2 fois).

---

## 3. Corrections appliquées

### 3.1 Bootstrap / infrastructure (les plus gros gains)

| Fichier | Changement | Effet |
|---|---|---|
| `Dockerfile` + `docker/php/opcache.ini` | **Activation d'OPcache** + `realpath_cache` | Plus de recompilation PHP à chaque requête |
| `public/index.php` | **Cache Twig activé** (était `cache => false`) | Templates compilés une seule fois |
| `public/index.php` | `addErrorMiddleware` dépend de `APP_ENV` (debug coupé en prod) | Perf + sécurité |
| Autoloader Composer | `composer dump-autoload --optimize` → classmap **17 → 1 524 classes** | Plus de `stat()` filesystem par classe |

### 3.2 Requêtes SQL

| Fichier | Changement | Effet |
|---|---|---|
| `public/routes/stats.php` | 4 requêtes → **2** (1 scan par table via CTE `MATERIALIZED`) + **cache fichier 60 s** | Dashboard : **0 requête** sur les chargements répétés |
| `src/Repositories/ContactRepository.php` | `save()` renvoie la ligne via `RETURNING *` ; nouvelle méthode `upsertWithStatus()` (upsert + détection création/maj en 1 requête) | — |
| `public/routes/contacts.php` | `POST /contacts` : suppression du `SELECT` redondant | 3 → 2 requêtes |
| `public/routes/sync.php` | Les 2 boucles de synchro suppriment le `findByEmail` par contact | **2N → N** requêtes |
| `src/Helpers.php` | Ajout de `cacheRemember()` (cache fichier, sans dépendance) | — |

### 3.3 Index base de données

| Action | Effet |
|---|---|
| Ajout `idx_contact_segment_segment` sur `contact_segment(idSegment)` (fichier SQL **+ base live**) | Jointures segments indexées |
| Suppression de l'index redondant `idx_contact_email` (assuré par la contrainte `UNIQUE`) | Écritures (synchros) plus rapides |

### 3.4 Correctif bloquant rencontré

| Problème | Solution |
|---|---|
| Conteneur en **boucle de redémarrage** après rebuild : `entrypoint.sh: no such file or directory` (fins de ligne **CRLF** Windows sur le shebang) | Conversion des scripts `docker/script/*.sh` en **LF** + ajout de `.gitattributes` (`*.sh eol=lf`) pour éviter toute récidive |

---

## 4. Résultats mesurés (avant / après)

| Élément | Avant | Après (à chaud) | Gain |
|---|---|---|---|
| **Page HTML `/dashboard`** | **1 264 ms** | **~76 ms** | **‑94 %** |
| **API `/api/stats/dashboard`** | **444 ms** | **~35 ms** | **‑92 %** |
| Routes 404 / inexistantes | 400 – 900 ms | ~30 ms | ‑93 % |
| **Chargement total dashboard** | **~2 710 ms** | **~110 ms** | **‑96 %** |

> ⚠️ Le **tout premier** appel après un redémarrage du conteneur reste à ~1,4 s
> (le temps qu'OPcache et le cache Twig se « chauffent »). Toutes les requêtes
> suivantes sont à ~70 ms. Comportement normal et attendu.

### Nombre de requêtes SQL : avant → après

| Endpoint | Avant | Après |
|---|---|---|
| `/api/stats/dashboard` | 4 (contact ×2 scans, billet ×2 scans) | **2**, ou **0** si en cache |
| `POST /api/contacts` | 3 | **2** |
| `POST /api/sync/weezevent` | **2 × N** | **N** |
| `POST /api/sync/brevo/import` | **2 × N** | **N** |

---

## 5. Validations effectuées

- ✅ `php -l` propre sur tous les fichiers PHP modifiés
- ✅ Requêtes SQL fusionnées testées en base : **sortie identique** à l'original (parité prouvée)
- ✅ `xmax` (détection création/maj) testé puis `ROLLBACK` — aucune donnée modifiée
- ✅ OPcache confirmé **chargé et actif pour FPM** (`opcache.enable=1`)
- ✅ Test bout-en-bout authentifié : `/api/stats/dashboard` renvoie le bon JSON en ~35 ms
- ✅ Conteneur stable, application pleinement fonctionnelle

---

## 6. Comment appliquer (rappel)

Les changements de code PHP/Twig sont pris en compte immédiatement (bind-mount).
**OPcache nécessite un rebuild de l'image** :

```bash
# Rebuild + redémarrage du conteneur web (active OPcache)
docker compose build web-app
docker compose up -d web-app

# Optimiser l'autoloader (le bind-mount écrase le vendor de l'image)
docker exec slim-web-app composer dump-autoload --optimize --no-dev -d /var/www/html
```

Vérifier qu'OPcache est actif :

```bash
docker exec slim-web-app php -r "echo ini_get('opcache.enable');"   # doit afficher 1
```

---

## 7. Pistes complémentaires (non faites, optionnelles)

- **Invalider le cache stats** après une synchro (sinon stats périmées jusqu'à 60 s).
- En production réelle (sans bind-mount), passer `opcache.validate_timestamps=0`
  pour gagner encore quelques ms.
- Pagination par **curseur (keyset)** sur `GET /api/contacts` au lieu d'`OFFSET`.
- **Révoquer / rotater** les clés API Brevo et Weezevent commitées en clair dans `.env`.

---

*Document généré le 2026-06-18.*
