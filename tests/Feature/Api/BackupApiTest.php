<?php

use App\Models\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function backupAuthHeader(): array
{
    config()->set('services.backup.api_key', 'test-api-key');

    return [
        'Authorization' => 'Bearer test-api-key',
        'Accept' => 'application/json',
    ];
}

test('rejects request without token', function () {
    $this->postJson('/api/v1/backup')->assertStatus(401);
});

test('rejects request with invalid token', function () {
    config()->set('services.backup.api_key', 'test-api-key');

    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/backup')
        ->assertStatus(401);
});

test('uploads a backup file with valid token', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('backup.sqlite', 1024);

    $response = $this->withHeaders(backupAuthHeader())
        ->post('/api/v1/backup', [
            'device_id' => 'test-device-123',
            'file' => $file,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Backup uploaded successfully')
        ->assertJsonPath('backup.device_id', 'test-device-123');

    $backupId = $response->json('backup.id');
    expect($backupId)->toBeInt();

    $backup = Backup::query()->findOrFail($backupId);

    Storage::disk('local')->assertExists('backups/'.$backup->device_id.'/'.$backup->filename);
    expect($backup->size)->toBeGreaterThan(0);
    expect($backup->checksum)->not->toBeEmpty();
});

test('returns latest backup for a device', function () {
    Storage::fake('local');

    Backup::factory()->create([
        'device_id' => 'device-1',
        'filename' => 'old.db',
        'size' => 100,
        'checksum' => str_repeat('a', 64),
        'created_at' => now()->subMinute(),
    ]);

    $latest = Backup::factory()->create([
        'device_id' => 'device-1',
        'filename' => 'latest.db',
        'size' => 200,
        'checksum' => str_repeat('b', 64),
        'created_at' => now(),
    ]);

    $this->withHeaders(backupAuthHeader())
        ->getJson('/api/v1/backup/latest?device_id=device-1')
        ->assertOk()
        ->assertJsonPath('backup.id', $latest->id)
        ->assertJsonPath('backup.filename', $latest->filename)
        ->assertJsonPath('download_url', route('api.backup.download', $latest));
});

test('lists backups ordered by date', function () {
    Storage::fake('local');

    $older = Backup::factory()->create([
        'device_id' => 'device-1',
        'created_at' => now()->subMinutes(10),
    ]);

    $newer = Backup::factory()->create([
        'device_id' => 'device-1',
        'created_at' => now(),
    ]);

    $this->withHeaders(backupAuthHeader())
        ->getJson('/api/v1/backups?device_id=device-1')
        ->assertOk()
        ->assertJsonCount(2, 'backups')
        ->assertJsonPath('backups.0.id', $newer->id)
        ->assertJsonPath('backups.1.id', $older->id);
});

test('downloads a backup file', function () {
    Storage::fake('local');

    $backup = Backup::factory()->create([
        'device_id' => 'device-1',
        'filename' => 'download.db',
        'size' => 3,
        'checksum' => str_repeat('c', 64),
    ]);

    Storage::disk('local')->put('backups/'.$backup->device_id.'/'.$backup->filename, 'db');

    $this->withHeaders(backupAuthHeader())
        ->get('/api/v1/backup/'.$backup->id)
        ->assertOk();
});
