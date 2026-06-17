# Diagnostic — Problème de routage backend (Slim)

> Branche `projet-slim`. Le routage est **cassé à plusieurs niveaux** : aucune route `/api/*` ne peut répondre aujourd'hui. La structure est à mi‑chemin d'une migration vers Slim. Ci‑dessous les problèmes du plus bloquant au plus secondaire.

## Chaîne de routage attendue

```
Client → reverse-proxy (nginx, :80/:443)
       → web-app (nginx + php-fpm, :80)
       → public/index.php (front-controller Slim)
       → routes /api/*
```

---

## 1. Config nginx du conteneur web invalide → nginx ne démarre pas (BLOQUANT)

Fichier : `app/nginx/default.conf`, lignes 8‑11

```nginx
contact contacts.php contact.html;
segments segments.php segment.html;
stats stats.php stat.html;
sync sync.php sync.html;
```

`contact`, `segments`, `stats`, `sync` **ne sont pas des directives nginx** → nginx refuse de démarrer (`unknown directive`). C'est le blocage n°1.

Lignes 14‑20, le bloc `location /` empile **5 `try_files`** : seul le dernier est pris en compte par nginx. Pour un front‑controller Slim il n'en faut **qu'un seul** :

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 2. `fastcgi_pass` ne correspond pas à PHP‑FPM (BLOQUANT)

Fichier : `app/nginx/default.conf`, ligne 25

```nginx
fastcgi_pass unix:/run/php/php8.2-fpm.sock;
```

L'image `php:8.2-fpm` écoute en **TCP sur `127.0.0.1:9000`**, pas sur ce socket unix. Même si nginx démarrait, aucun `.php` ne s'exécuterait.

➡️ Corriger en : `fastcgi_pass 127.0.0.1:9000;`

## 3. Dockerfile et `root` nginx pointent au mauvais endroit (BLOQUANT)

Fichier : `app/Dockerfile` (contexte de build = `./app`)

- `COPY . /var/www/html` → les fichiers arrivent dans `/var/www/html/Backend/...`
- `COPY ./public/ /var/www/html/` → **`app/public/` n'existe pas** (le public réel est `app/Backend/public/`) → le build échoue.
- nginx a `root /var/www/html/public` (ligne 6) qui ne correspond à aucun emplacement réel.

➡️ Aligner le `COPY` et le `root` nginx sur l'emplacement réellement servi.

## 4. Le vrai routeur Slim n'est jamais servi, et ses `require` sont faux (BLOQUANT)

- nginx sert `app/Backend/public/index.php`, qui fait seulement :
  ```php
  echo "Application PHP opérationnelle 🚀";
  ```
  → il **ne charge jamais Slim**, donc aucune route `/api/*` n'existe.
- Le vrai bootstrap est `app/Backend/src/index.php`, mais il n'est jamais servi **et** ses chemins sont incohérents :
  - `composer.json` est dans `src/` → vendor s'installe dans `src/vendor`, or la ligne 8 cherche `__DIR__/../vendor/autoload.php` = `Backend/vendor` ❌
  - lignes 52‑55 : `require __DIR__/../routes/...` = `Backend/routes` alors que les routes sont dans `Backend/src/routes` ❌

## 5. Les fichiers `public/*.php` sont des doublons cassés

Fichiers : `app/Backend/public/{contacts,segments,stats,sync}.php`

- Ils recopient les routes mais appellent `$app->get(...)` **sans jamais créer `$app`** ni charger l'autoloader / `Helpers.php` → erreur fatale s'ils sont atteints directement.
- En plus, `public/contacts.php` ligne 27 contient un `echo "contacts.php opérationnelle 🚀";` parasite inséré au milieu des définitions de routes.
- Les versions **valides** sont dans `app/Backend/src/routes/`.

## 6. Reverse-proxy bancal (SECONDAIRE)

Fichiers : `proxy/conf.d/reverse-proxy.conf` et `proxy/conf.d/webapp.conf`

- Les deux écoutent sur `:80` avec `server_name _`. Le HTTP fonctionne par chance (ordre alphabétique de chargement → `reverse-proxy.conf` gagne).
- `webapp.conf` redirige vers HTTPS alors qu'**aucun serveur n'écoute en 443** → HTTPS cassé / boucle potentielle.

---

## Checklist de correction (ordre conseillé)

| # | Fichier | Action | Priorité |
|---|---------|--------|----------|
| 1 | `app/nginx/default.conf` | Supprimer les lignes 8‑11 (fausses directives) ; ne garder qu'un seul `try_files $uri $uri/ /index.php?$query_string;` | 🔴 Bloquant |
| 2 | `app/nginx/default.conf:25` | `fastcgi_pass 127.0.0.1:9000;` au lieu du socket unix | 🔴 Bloquant |
| 3 | `app/Dockerfile` | Corriger `COPY ./public/...` (chemin inexistant) et aligner le `root` nginx | 🔴 Bloquant |
| 4 | `public/index.php` | En faire le vrai bootstrap Slim (le contenu utile est dans `src/index.php`) | 🔴 Bloquant |
| 5 | bootstrap Slim | Corriger les `require` : vendor dans `src/vendor`, routes dans `src/routes` | 🔴 Bloquant |
| 6 | `public/{contacts,segments,stats,sync}.php` | Supprimer ces doublons cassés (versions valides dans `src/routes/`) | 🟠 Important |
| 7 | `proxy/conf.d/webapp.conf` | Ajouter un bloc `listen 443 ssl` + certificat, ou retirer la redirection HTTPS tant que TLS n'est pas configuré | 🟡 Secondaire |
