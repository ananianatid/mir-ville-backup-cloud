# Plan: Backend API Cloud Backup

## Contexte

Le mobile (mir-ville-app) est prêt avec :
- `services/apiClient.ts` — Client HTTP avec auth API key
- `services/authService.ts` — Secure storage credentials
- `services/cloudBackupService.ts` — Upload/download/list backups
- `app/settings/cloud-backup.tsx` — UI de configuration

**Besoin :** Backend Laravel pour recevoir et stocker les backups.

---

## Architecture

### Endpoints API

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/backup` | Upload fichier .db (multipart/form-data) |
| GET | `/api/v1/backup/latest` | Récupérer le dernier backup |
| GET | `/api/v1/backups` | Liste des backups par date |
| GET | `/api/v1/backup/{id}` | Télécharger un backup spécifique |

### Authentification

Bearer token via header `Authorization: Bearer {API_KEY}`

- API key stockée dans `.env` (`BACKUP_API_KEY`)
- Middleware simple qui vérifie le token
- Pas besoin de Laravel Sanctum (overkill pour usage interne)

### Storage

- Fichiers stockés dans `storage/app/backups/{user_id}/`
- Nom de fichier : `{timestamp}_{uuid}.db`
- Metadata en DB (table `backups`)

---

## Implementation

### Phase 1: Migration & Model

**Fichier:** `database/migrations/xxxx_create_backups_table.php`

```php
Schema::create('backups', function (Blueprint $table) {
    $table->id();
    $table->string('device_id');        // Identifiant appareil mobile
    $table->string('filename');          // Nom du fichier
    $table->unsignedBigInteger('size');  // Taille en bytes
    $table->string('checksum')->nullable(); // SHA256 du fichier
    $table->timestamps();
});
```

**Fichier:** `app/Models/Backup.php`

```php
class Backup extends Model {
    protected $fillable = ['device_id', 'filename', 'size', 'checksum'];
}
```

---

### Phase 2: Middleware d'Authentification

**Fichier:** `app/Http/Middleware/ApiTokenAuth.php`

```php
public function handle(Request $request, Closure $next): Response
{
    $token = $request->bearerToken();
    $validToken = config('services.backup.api_key');
    
    if (!$token || !hash_equals($validToken, $token)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    return $next($request);
}
```

**Enregistrement:** `bootstrap/app.php`

---

### Phase 3: Controller

**Fichier:** `app/Http/Controllers/Api/BackupController.php`

```php
class BackupController extends Controller
{
    // POST /api/v1/backup
    public function store(Request $request)
    {
        $validated = $validated = $request->validate([
            'file' => 'required|file|mimes:db,sqlite|max:10240', // max 10MB
            'device_id' => 'required|string',
        ]);
        
        // Stocker fichier
        $file = $request->file('file');
        $filename = now()->timestamp . '_' . Str::uuid() . '.db';
        $path = $file->storeAs('backups/' . $validated['device_id'], $filename);
        
        // Créer entrée DB
        $backup = Backup::create([
            'device_id' => $validated['device_id'],
            'filename' => $filename,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->path()),
        ]);
        
        return response()->json([
            'message' => 'Backup uploaded successfully',
            'backup' => $backup,
        ], 201);
    }
    
    // GET /api/v1/backup/latest
    public function latest(Request $request)
    {
        $deviceId = $request->query('device_id');
        
        $backup = Backup::when($deviceId, fn($q) => $q->where('device_id', $deviceId))
            ->latest()
            ->first();
            
        if (!$backup) {
            return response()->json(['error' => 'No backup found'], 404);
        }
        
        return response()->json([
            'backup' => $backup,
            'download_url' => route('api.backup.download', $backup->id),
        ]);
    }
    
    // GET /api/v1/backups
    public function index(Request $request)
    {
        $deviceId = $request->query('device_id');
        
        $backups = Backup::when($deviceId, fn($q) => $q->where('device_id', $deviceId))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'filename', 'size', 'created_at']);
            
        return response()->json(['backups' => $backups]);
    }
    
    // GET /api/v1/backup/{id}
    public function download(Backup $backup)
    {
        $path = storage_path('app/backups/' . $backup->device_id . '/' . $backup->filename);
        
        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        
        return response()->download($path, $backup->filename);
    }
}
```

---

### Phase 4: Routes

**Fichier:** `routes/api.php` (à créer)

```php
use App\Http\Controllers\Api\BackupController;

Route::middleware('api.token')->group(function () {
    Route::post('/v1/backup', [BackupController::class, 'store'])->name('api.backup.store');
    Route::get('/v1/backup/latest', [BackupController::class, 'latest'])->name('api.backup.latest');
    Route::get('/v1/backups', [BackupController::class, 'index'])->name('api.backup.index');
    Route::get('/v1/backup/{backup}', [BackupController::class, 'download'])->name('api.backup.download');
});
```

---

### Phase 5: Configuration

**Fichier:** `.env`

```env
BACKUP_API_KEY=generer_une_cle_securisee_ici
```

**Fichier:** `config/services.php`

```php
'backup' => [
    'api_key' => env('BACKUP_API_KEY'),
],
```

---

## Tests (Pest)

**Fichier:** `tests/Feature/Api/BackupApiTest.php`

```php
it('rejects request without token', function () {
    $this->postJson('/api/v1/backup')->assertStatus(401);
});

it('rejects request with invalid token', function () {
    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/backup')
        ->assertStatus(401);
});

it('uploads a backup file with valid token', function () {
    $file = UploadedFile::fake()->create('backup.db', 1024);
    
    $this->withHeader('Authorization', 'Bearer ' . config('services.backup.api_key'))
        ->postJson('/api/v1/backup', [
            'device_id' => 'test-device-123',
            'file' => $file,
        ])
        ->assertStatus(201)
        ->assertJsonPath('message', 'Backup uploaded successfully');
});

it('returns latest backup', function () {
    Backup::factory()->count(3)->create(['device_id' => 'device-1']);
    
    $latest = Backup::where('device_id', 'device-1')->latest()->first();
    
    $this->withHeader('Authorization', 'Bearer ' . config('services.backup.api_key'))
        ->getJson('/api/v1/backup/latest?device_id=device-1')
        ->assertJsonPath('backup.id', $latest->id);
});

it('lists backups ordered by date', function () {
    Backup::factory()->count(5)->create(['device_id' => 'device-1']);
    
    $this->withHeader('Authorization', 'Bearer ' . config('services.backup.api_key'))
        ->getJson('/api/v1/backups?device_id=device-1')
        ->assertJsonCount(5, 'backups')
        ->assertJsonPath('backups.0.created_at', Backup::latest()->first()->created_at->toISOString());
});
```

---

## Checklist

- [ ] Créer migration `backups`
- [ ] Créer modèle `Backup` avec factory
- [ ] Créer middleware `ApiTokenAuth`
- [ ] Enregistrer middleware dans `bootstrap/app.php`
- [ ] Créer contrôleur `BackupController`
- [ ] Créer fichier de routes `routes/api.php`
- [ ] Ajouter config `config/services.backup`
- [ ] Générer API key et ajouter au `.env`
- [ ] Écrire tests Pest
- [ ] Faire un test manuel avec Postman/curl

---

## Vérification

```bash
# Lancer le serveur
composer run dev

# Tester upload
curl -X POST http://localhost:8000/api/v1/backup \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  -F "device_id=test-device" \
  -F "file=@database/database.sqlite"

# Tester liste
curl -X GET "http://localhost:8000/api/v1/backups?device_id=test-device" \
  -H "Authorization: Bearer $BACKUP_API_KEY"

# Tester download
curl -X GET "http://localhost:8000/api/v1/backup/1" \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  -o downloaded.db
```

---

## Prochaines étapes (après MVP)

1. **Nettoyage automatique** — Job pour supprimer backups > 30 jours
2. **Compression** — Gzip avant upload pour réduire bande passante
3. **Multi-user** — Table `users` + API keys par user
4. **Metrics** — Logging des uploads (taille, durée, fréquence)
