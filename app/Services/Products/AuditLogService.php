<?php

// app/Services/AuditLogService.php

namespace App\Services\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    public function log(string $action, Model $current, ?Model $old = null): void
    {
        // Sesuaikan dengan struktur tabel audit_logs Anda yang sebenarnya.
        // Placeholder ini mencatat perubahan minimal — GANTI sesuai skema asli project Anda.
        DB::table('audit_logs')->insert([
            'action' => $action,
            'user_id' => Auth::id(),
            'reference_type' => get_class($current),
            'reference_id' => $current->id,
            'old_values' => $old ? json_encode($old->getAttributes()) : null,
            'new_values' => json_encode($current->getAttributes()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
