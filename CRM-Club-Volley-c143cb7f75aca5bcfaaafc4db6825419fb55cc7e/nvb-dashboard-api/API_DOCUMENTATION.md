# Documentation API — Nice Volley Ball Dashboard

## Informations générales

- **Base URL** : `http://localhost:8080/api`
- **Format** : JSON
- **Encodage** : UTF-8

Toutes les réponses suivent ce format :

```json
{
  "success": true,
  "data": {}
}
```

En cas d'erreur :

```json
{
  "success": false,
  "error": "Message d'erreur clair"
}
```

---

## 1. Santé de l'API

### GET /api/health

Vérifie que l'API fonctionne correctement.

**Paramètres** : aucun

**Réponse succès (200)** :
```json
{
  "status": "ok",
  "message": "API Nice Volley Ball opérationnelle",
  "timestamp": "2026-06-16 09:00:00"
}
```

---

## 2. Contacts

### GET /api/contacts

Récupère la liste de tous les contacts.

**Paramètres** : aucun

**Réponse succès (200)** :
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "Alice",
      "last_name": "Martin",
      "email": "alice.martin@example.com",
      "phone": "0600000001",
      "source": "weezevent",
      "created_at": "2026-06-16 09:26:15"
    }
  ]
}
```

**Erreurs possibles** :
- `500` : base de données inaccessible

---

### GET /api/contacts/{id}

Récupère le détail d'un contact par son identifiant.

**Paramètres URL** :
- `id` (entier) : identifiant du contact

**Réponse succès (200)** :
```json
{
  "success": true,
  "data": {
    "id": 1,
    "first_name": "Alice",
    "last_name": "Martin",
    "email": "alice.martin@example.com",
    "phone": "0600000001",
    "source": "weezevent",
    "created_at": "2026-06-16 09:26:15",
    "updated_at": "2026-06-16 09:26:15"
  }
}
```

**Erreurs possibles** :
- `404` : contact introuvable
- `500` : base de données inaccessible

---

### POST /api/contacts

Crée un nouveau contact.

**Corps de la requête (JSON)** :
```json
{
  "first_name": "Lucas",
  "last_name": "Robert",
  "email": "lucas.robert@example.com",
  "phone": "0600000004",
  "source": "manual"
}
```

**Champs obligatoires** : `first_name`, `last_name`, `email`

**Champs optionnels** : `phone`, `source` (valeur par défaut : `manual`)

**Réponse succès (201)** :
```json
{
  "success": true,
  "message": "Contact créé",
  "data": {
    "id": 6,
    "first_name": "Lucas",
    "last_name": "Robert",
    "email": "lucas.robert@example.com",
    "phone": "0600000004",
    "source": "manual",
    "created_at": "2026-06-16 10:00:00"
  }
}
```

**Erreurs possibles** :
- `400` : champs obligatoires manquants
- `400` : adresse email invalide
- `409` : un contact existe déjà avec cet email
- `500` : base de données inaccessible

---

## 3. Statistiques

### GET /api/stats/dashboard

Récupère les indicateurs clés pour le tableau de bord.

**Paramètres** : aucun

**Réponse succès (200)** :
```json
{
  "success": true,
  "data": {
    "total_contacts": 5,
    "weezevent_contacts": 3,
    "brevo_contacts": 1,
    "manual_contacts": 1,
    "new_contacts_7days": 5
  }
}
```

**Erreurs possibles** :
- `500` : base de données inaccessible

---

## 4. Segments

### GET /api/segments

Récupère la liste des segments disponibles.

**Paramètres** : aucun

**Réponse succès (200)** :
```json
{
  "success": true,
  "data": [
    {
      "id": "nouveaux-visiteurs",
      "label": "Nouveaux visiteurs",
      "description": "Contacts ajoutés ces 7 derniers jours"
    },
    {
      "id": "supporters-reguliers",
      "label": "Supporters réguliers",
      "description": "Contacts venus via Weezevent"
    },
    {
      "id": "abonnes-potentiels",
      "label": "Abonnés potentiels",
      "description": "Contacts avec email valide à fort potentiel"
    }
  ]
}
```

---

### GET /api/segments/{id}/contacts

Récupère les contacts appartenant à un segment.

**Paramètres URL** :
- `id` : identifiant du segment (`nouveaux-visiteurs`, `supporters-reguliers`, `abonnes-potentiels`)

**Réponse succès (200)** :
```json
{
  "success": true,
  "segment": "supporters-reguliers",
  "data": [
    {
      "id": 1,
      "first_name": "Alice",
      "last_name": "Martin",
      "email": "alice.martin@example.com",
      "phone": "0600000001",
      "source": "weezevent"
    }
  ]
}
```

**Erreurs possibles** :
- `404` : segment inconnu
- `500` : base de données inaccessible

---

## 5. Synchronisation

### POST /api/sync/weezevent

Déclenche la synchronisation des participants depuis Weezevent vers la base PostgreSQL.

**Paramètres** : aucun

**Réponse succès (200)** :
```json
{
  "success": true,
  "message": "Synchronisation Weezevent terminée",
  "data": {
    "total_retrieved": 42,
    "contacts_created": 30,
    "contacts_updated": 12,
    "errors": 0
  }
}
```

**Erreurs possibles** :
- `500` : clé API Weezevent manquante ou invalide
- `500` : base de données inaccessible

---

### POST /api/sync/brevo

Synchronise un segment de contacts vers Brevo.

**Corps de la requête (JSON)** :
```json
{
  "segment": "supporters-reguliers"
}
```

**Valeurs possibles pour `segment`** :
- `tous` : tous les contacts
- `supporters-reguliers` : contacts source weezevent
- `nouveaux-visiteurs` : contacts des 7 derniers jours

**Réponse succès (200)** :
```json
{
  "success": true,
  "message": "Synchronisation Brevo terminée",
  "segment": "supporters-reguliers",
  "data": {
    "success": 3,
    "errors": 0,
    "details": []
  }
}
```

**Erreurs possibles** :
- `500` : clé API Brevo manquante ou invalide
- `500` : base de données inaccessible

---

## 6. Tableau récapitulatif des routes

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | /api/health | Santé de l'API |
| GET | /api/contacts | Liste des contacts |
| GET | /api/contacts/{id} | Détail d'un contact |
| POST | /api/contacts | Créer un contact |
| GET | /api/stats/dashboard | Statistiques du dashboard |
| GET | /api/segments | Liste des segments |
| GET | /api/segments/{id}/contacts | Contacts d'un segment |
| POST | /api/sync/weezevent | Synchronisation Weezevent |
| POST | /api/sync/brevo | Synchronisation Brevo |

---

## 7. Exemples de tests avec curl

### Tester la santé de l'API
```bash
curl http://localhost:8080/api/health
```

### Récupérer tous les contacts
```bash
curl http://localhost:8080/api/contacts
```

### Créer un contact
```bash
curl -X POST http://localhost:8080/api/contacts \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Test","last_name":"NVB","email":"test@example.com","source":"manual"}'
```

### Synchroniser vers Brevo
```bash
curl -X POST http://localhost:8080/api/sync/brevo \
  -H "Content-Type: application/json" \
  -d '{"segment":"supporters-reguliers"}'
```