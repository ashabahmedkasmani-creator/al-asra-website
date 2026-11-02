<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate(['action' => ['nullable', 'string', 'max:80'], 'user_id' => ['nullable', 'integer']]);
        $q = AuditLog::with('user:id,name,email')->latest('id');
        if (! empty($v['action'])) $q->where('action', 'like', str_replace(['%', '_'], ['', ''], $v['action']).'%');
        if (! empty($v['user_id'])) $q->where('user_id', $v['user_id']);
        return JsonResource::collection($q->paginate(50)->withQueryString());
    }
}
