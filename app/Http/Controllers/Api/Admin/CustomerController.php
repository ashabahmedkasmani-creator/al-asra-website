<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = User::where('role', Role::Customer->value)->withCount('orders')->latest('id');
        if (! empty($v['search'])) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v['search']).'%';
            $q->where(fn ($w) => $w->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]));
        }
        return UserResource::collection($q->paginate($v['per_page'] ?? 20)->withQueryString());
    }

    public function show(User $user)
    {
        abort_unless($user->role === Role::Customer, 404);
        $user->loadCount('orders');
        return response()->json(['data' => [
            'customer' => new UserResource($user),
            'orders' => AdminOrderResource::collection($user->orders()->latest('id')->limit(20)->get())->resolve(),
        ]]);
    }
}
