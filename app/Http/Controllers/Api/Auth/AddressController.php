<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return AddressResource::collection($request->user()->addresses()->orderByDesc('is_default')->latest('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $address = DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            $makeDefault = ($data['is_default'] ?? false) || ! $user->addresses()->exists();
            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }
            return $user->addresses()->create(array_merge($data, ['is_default' => $makeDefault]));
        });
        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(Request $request, Address $address)
    {
        Gate::authorize('update', $address);
        $data = $this->validated($request, false);
        DB::transaction(function () use ($request, $address, $data) {
            if (! empty($data['is_default'])) {
                $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }
            $address->update($data);
        });
        return new AddressResource($address->fresh());
    }

    public function destroy(Address $address)
    {
        Gate::authorize('delete', $address);
        $address->delete();
        return response()->noContent();
    }

    private function validated(Request $request, bool $creating): array
    {
        $clean = fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v;
        $request->merge([
            'label' => $clean($request->label), 'recipient_name' => $clean($request->recipient_name),
            'address_line' => $clean($request->address_line), 'city' => $clean($request->city),
            'province' => $clean($request->province), 'postal_code' => $clean($request->postal_code),
            'phone' => Phone::normalize($request->phone),
        ]);
        $req = $creating ? 'required' : 'sometimes';
        return $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => [$req, 'string', 'min:2', 'max:100'],
            'phone' => [$req, 'regex:/^03\d{9}$/'],
            'address_line' => [$req, 'string', 'min:5', 'max:255'],
            'city' => [$req, 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
