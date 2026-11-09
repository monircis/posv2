<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\Request;

/**
 * Settings > System > "Back up now".
 * Saves a backup request; the backup job itself is not built yet, so it stays "requested".
 */
class BackupController extends Controller
{
    /** The last 20 backups, newest first. */
    public function index()
    {
        return Backup::orderBy('id', 'desc')->limit(20)->get();
    }

    public function store(Request $request)
    {
        $backup = Backup::create([
            'status' => 'requested',
            'requested_by' => $request->user()->name,
        ]);

        return response()->json($backup->fresh(), 201);
    }
}
