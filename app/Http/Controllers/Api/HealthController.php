<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('select 1');
            return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
        } catch (\Throwable) {
            return response()->json(['status' => 'degraded'], 503);
        }
    }
}
