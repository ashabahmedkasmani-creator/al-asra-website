<?php
namespace App\Http\Controllers\Api;
use App\Enums\Role; use App\Http\Controllers\Controller; use App\Models\Order; use Illuminate\Http\Request;
class InvoiceController extends Controller { public function show(Request $r,string $reference){$order=Order::where('reference',$reference)->with(['items','payments','shipment'])->firstOrFail();$user=$r->user();abort_unless($order->user_id===$user->id||in_array($user->role,[Role::Admin,Role::Staff],true),403);return response()->view('demo.invoice',['order'=>$order,'store'=>['name'=>config('app.name'),'email'=>config('mail.from.address')]])->header('Content-Type','text/html; charset=UTF-8');} }
