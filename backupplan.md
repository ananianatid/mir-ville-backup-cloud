# Plan: Cloud Backup System with Laravel API

## Context

L'utilisateur veut implémenter un système de sauvegarde cloud personnel pour l'application Mir-Ville. Actuellement, l'app est 100% locale avec SQLite. Le besoin est :
- Une API Laravel qui reçoit la database à chaque changement
- Authentification par API key
- Stockage cloud pour restauration future
- Synchronisation entre différents appareils

## Exploration Summary

**Existant :**
- `services/backupService.ts` - Backup local (.db file) avec export/import
- `services/exportService.ts` - Export JSON de toutes les données
- `services/syncService.ts` - Placeholder désactivé (commenté) pour sync cloud
- `services/settingsService.ts` - Stockage settings dans SQLite (pourrait stocker API endpoint)
- Pas de client HTTP, pas de stockage sécurisé, pas d'authentification

**À créer :**
1. Client HTTP avec authentification API key
2. Stockage sécurisé des credentials (expo-secure-store)
3. Service cloud backup avec upload/download
4. Écran de configuration API
5. Backend Laravel (API)

## Requirements

1. **Mobile App (React Native/Expo)**
   - Ajouter `expo-secure-store` pour stocker API key + endpoint
   - Créer un API client wrapper autour de `fetch`
   - Implémenter upload automatique après chaque mutation DB
   - Implémenter download/restore depuis cloud
   - Écran de configuration (API endpoint + API key)
   - Gérer les erreurs réseau, retry, offline queue

2. **Backend Laravel**
   - Authentification par API key (Sanctum ou token simple)
   - Endpoint POST `/api/v1/backup` - recevoir et stocker backup
   - Endpoint GET `/api/v1/backup/latest` - récupérer dernier backup
   - Endpoint GET `/api/v1/backups` - liste des backups par date
   - Storage des fichiers .db (S3, local, ou database BLOB)
   - Rate limiting, validation, logs

## Approach 1: Full Database Backup (Recommandé)

**Flow :**
1. Après chaque `bumpVersion()`, déclencher un upload du .db
2. Backup compressé (optionnel: gzip)
3. Laravel stocke le fichier avec timestamp + user_id
4. Multi-appareil : chaque appareil upload son backup, le user choisit lequel restaurer

**Avantages :**
- Simple, fiable, pas de conflits
- Utilise l'existant (backupService.exportBackup)
- Restauration complète en un appel

**Inconvénients :**
- Upload plus lourd (~1-5 MB)
- Pas de sync temps réel, plutôt "last write wins"

## Approach 2: Incremental JSON Sync

**Flow :**
1. Exporter seulement les données changées (JSON)
2. Laravel merge les données avec conflict resolution (timestamp-based)
3. Sync bidirectionnelle

**Avantages :**
- Payload léger
- Sync plus fréquente possible
- Multi-appareil en temps réel

**Inconvénients :**
- Complexe : gestion conflits, soft deletes, timestamps
- Risque de perte de données si mal implémenté

## Recommended Approach: Approach 1 (Full DB Backup)

Justification : Plus simple, fiable, correspond au besoin exprimé ("stoque pour une restauration future"). La sync entre appareils peut être abordée ensuite.

## Implementation Plan

### Phase 1: Mobile - Infrastructure (Fichier: `services/apiClient.ts`)
```typescript
- createApiClient(baseUrl: string, apiKey: string)
- get, post methods avec headers auth
- Gestion erreurs HTTP + network
- Retry logic (3 tentatives)
```

### Phase 2: Mobile - Secure Storage (Nouveau: `services/authService.ts`)
```typescript
- saveApiCredentials(endpoint, apiKey)
- getApiCredentials()
- clearApiCredentials()
- use expo-secure-store
```

### Phase 3: Mobile - Cloud Backup Service (Nouveau: `services/cloudBackupService.ts`)
```typescript
- uploadBackup() - POST le fichier .db
- downloadLatestBackup() - GET + import
- listBackups() - GET liste des backups dispos
- autoUpload() - déclenché après mutation
```

### Phase 4: Mobile - Intégration (Modif: `store/useStore.ts` + `services/*Service.ts`)
```typescript
- Après chaque bumpVersion(), appeler cloudBackupService.autoUpload()
- Debounce : attendre 2-3 secondes après la dernière mutation
```

### Phase 5: Mobile - UI (Nouveau: `app/settings/cloud-backup.tsx`)
```typescript
- Config screen : API endpoint + API key inputs
- Boutons : "Sauvegarder maintenant", "Restaurer depuis cloud", "Voir historique"
- Status : dernier backup, prochain sync
```

### Phase 6: Laravel Backend (Nouveau repo: `mir-ville-api`)
```bash
- laravel new mir-ville-api
- composer require laravel/sanctum
- Migration : backups (id, user_id, filename, size, uploaded_at)
- API Routes : POST /api/v1/backup, GET /api/v1/backup/latest, GET /api/v1/backups
- Controller : BackupController@store, @index, @download
- Storage : local ou S3
```

## Critical Files to Modify/Create

**Mobile :**
| File | Action | Purpose |
|------|--------|---------|
| `services/apiClient.ts` | Create | HTTP wrapper |
| `services/authService.ts` | Create | Secure storage |
| `services/cloudBackupService.ts` | Create | Cloud sync logic |
| `app/settings/cloud-backup.tsx` | Create | Config UI |
| `package.json` | Modify | Add expo-secure-store |
| `store/useStore.ts` | Modify | Trigger auto-upload |

**Backend (nouveau repo) :**
- `app/Http/Controllers/Api/BackupController.php`
- `routes/api.php`
- `database/migrations/xxxx_create_backups_table.php`
- `app/Models/Backup.php`
- `.env` : API_KEY, STORAGE_PATH

## Verification

1. **Mobile**
   - `npm install expo-secure-store`
   - `npx expo install expo-secure-store`
   - TSLint : aucun `any` dans apiClient
   - Tester : input API key → save → restart app → key persist

2. **Cloud Backup Flow**
   - Config screen : entrer endpoint + API key
   - "Sauvegarder maintenant" → upload .db → succès
   - Laravel : vérifier fichier reçu dans storage/
   - "Restaurer" → download → import → DB restaurée

3. **Auto-upload**
   - Créer un client → attendre 3s → vérifier upload Laravel
   - Modifier order → attendre 3s → vérifier nouveau backup

4. **Backend**
   - `php artisan test` ou Pest
   - Postman : POST /api/v1/backup avec API key → 200 OK
   - GET /api/v1/backups → liste des backups

## Questions pour l'utilisateur

1. **Backend Laravel** : Veux-tu que je crée le repo Laravel maintenant, ou veux-tu d'abord tester le mobile avec un mock API (ex: webhook.site ou un serveur local) ?

2. **Hébergement** : Où comptes-tu héberger le backend Laravel ? (VPS perso, Laravel Forge, Heroku, Railway, etc.) — ça influence la config de l'API endpoint.

3. **Multi-appareil** : Pour la sync entre appareils, est-ce que le comportement "le dernier backup écrase les autres" te convient, ou veux-tu une vraie fusion des données (plus complexe) ?

4. **Fréquence** : Upload après CHAQUE mutation (peut être fréquent) ou avec un debounce (ex: toutes les 5 minutes max) ?
