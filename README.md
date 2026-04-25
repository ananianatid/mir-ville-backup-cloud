## mir-ville-backup-cloud

Laravel 13 API pour recevoir et stocker des sauvegardes SQLite (`.db`) depuis l’app Mir-Ville.

### Pré-requis

- PHP 8.4
- Composer

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Configuration

Ajoute une clé API dans `.env` :

```env
BACKUP_API_KEY=change-me-to-a-secure-random-key
```

### Lancer en dev

```bash
composer run dev
```

### Déploiement cPanel (production)

- **Document Root**: pointe vers `public/` (ex: `/home/<user>/apps/.../mir-ville-backup-cloud/public`)
- **Fichier d’environnement**: copie `/.env.exampleprod` vers `.env`, puis renseigne `APP_URL`, `BACKUP_API_KEY`, et la base MySQL.
- **Permissions**: `storage/` et `bootstrap/cache/` doivent être écrivable par PHP.

Commandes (depuis le dossier du projet) :

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force

php artisan config:cache
php artisan view:cache
```

Si l’app utilise les assets Vite/Tailwind en production :

```bash
npm install
npm run build
```

### Authentification API

Toutes les routes API sont protégées via :

- Header **`Authorization: Bearer <BACKUP_API_KEY>`**

### Endpoints

- **POST** `/api/v1/backup` (multipart) — upload d’un backup `.db`
- **GET** `/api/v1/backup/latest?device_id=...` — dernier backup (optionnel: filtré par device)
- **GET** `/api/v1/backups?device_id=...` — liste des backups (optionnel: filtré par device)
- **GET** `/api/v1/backup/{id}` — téléchargement d’un backup

Les fichiers sont stockés sur le disk `local` (par défaut `storage/app/private`) sous :
`backups/{device_id}/{filename}`.

### Exemples curl

```bash
export BACKUP_API_KEY="change-me-to-a-secure-random-key"

# Upload
curl -X POST "http://localhost:8000/api/v1/backup" \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  -H "Accept: application/json" \
  -F "device_id=test-device" \
  -F "file=@database/database.sqlite"

# Liste
curl -X GET "http://localhost:8000/api/v1/backups?device_id=test-device" \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  -H "Accept: application/json"

# Latest
curl -X GET "http://localhost:8000/api/v1/backup/latest?device_id=test-device" \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  -H "Accept: application/json"

# Download
curl -X GET "http://localhost:8000/api/v1/backup/1" \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  -o downloaded.db
```

### Tests

```bash
php artisan test --compact
```
