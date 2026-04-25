<?php

namespace App\Models;

use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['device_id', 'filename', 'size', 'checksum'])]
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;
}
