<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartService
{
    /** Merge a guest cart into the signed-in customer's cart without trusting client prices. */
    public function mergeGuestCart(Request $request, User $user): void
    {
        $raw = (string) $request->header('X-Cart-Token', '');
        if ($raw === '' || strlen($raw) < 32 || strlen($raw) > 128) return;
        DB::transaction(function () use ($raw, $user) {
            $guest = Cart::where('token', hash('sha256', $raw))->whereNull('user_id')->lockForUpdate()->first();
            if (! $guest) return;
            $customer = Cart::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $customer) $customer = Cart::forceCreate(['user_id' => $user->id]);
            foreach ($guest->items()->get() as $line) {
                $existing = $customer->items()->where('product_id', $line->product_id)->where('variant_id', $line->variant_id)->first();
                $qty = min((int) config('alasra.max_line_quantity'), ($existing?->quantity ?? 0) + $line->quantity);
                if ($existing) $existing->update(['quantity' => $qty]);
                else $customer->items()->create(['product_id' => $line->product_id, 'variant_id' => $line->variant_id, 'quantity' => $qty]);
            }
            $guest->delete();
        });
    }

    /** Guests identify their cart with a random token (X-Cart-Token); only its SHA-256 is stored. */
    public function resolve(Request $request, bool $create = false): array
    {
        $user = $request->user('sanctum');
        if ($user) {
            $cart = Cart::where('user_id', $user->id)->first();
            if (! $cart && $create) {
                $cart = Cart::forceCreate(['user_id' => $user->id]);
            }
            return [$cart, null];
        }

        $raw = (string) $request->header('X-Cart-Token', '');
        if ($raw !== '' && strlen($raw) >= 32 && strlen($raw) <= 128) {
            $cart = Cart::where('token', hash('sha256', $raw))->whereNull('user_id')->first();
            if ($cart) {
                return [$cart, null];
            }
        }
        if ($create) {
            $raw = Str::random(48);
            $cart = Cart::forceCreate(['token' => hash('sha256', $raw)]);
            return [$cart, $raw];
        }
        return [null, null];
    }
}
