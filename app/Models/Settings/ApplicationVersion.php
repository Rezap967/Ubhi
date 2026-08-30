<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version', 'release_date', 'release_notes', 'minimum_version', 'is_latest',
    ];

    protected $casts = [
        'release_date' => 'date',
        'is_latest' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
