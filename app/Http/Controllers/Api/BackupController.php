<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBackupRequest;
use App\Models\Backup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function store(StoreBackupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $file = $request->file('file');
        $deviceId = $validated['device_id'];

        $filename = now()->timestamp.'_'.Str::uuid().'.db';
        $checksum = hash_file('sha256', $file->getRealPath());

        Storage::disk('local')->putFileAs(
            'backups/'.$deviceId,
            $file,
            $filename
        );

        $backup = Backup::create([
            'device_id' => $deviceId,
            'filename' => $filename,
            'size' => $file->getSize() ?? 0,
            'checksum' => $checksum,
        ]);

        return response()->json([
            'message' => 'Backup uploaded successfully',
            'backup' => $backup,
        ], 201);
    }

    public function latest(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');

        $backup = Backup::query()
            ->when(is_string($deviceId) && $deviceId !== '', fn ($query) => $query->where('device_id', $deviceId))
            ->latest()
            ->first();

        if (! $backup) {
            return response()->json(['error' => 'No backup found'], 404);
        }

        return response()->json([
            'backup' => $backup,
            'download_url' => route('api.backup.download', $backup),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');

        $backups = Backup::query()
            ->when(is_string($deviceId) && $deviceId !== '', fn ($query) => $query->where('device_id', $deviceId))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'device_id', 'filename', 'size', 'checksum', 'created_at']);

        return response()->json(['backups' => $backups]);
    }

    public function download(Backup $backup): BinaryFileResponse|JsonResponse
    {
        $path = 'backups/'.$backup->device_id.'/'.$backup->filename;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($path, $backup->filename);
    }
}
