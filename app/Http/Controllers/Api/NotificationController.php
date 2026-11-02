<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use Illuminate\Http\Request;
class NotificationController extends Controller { public function index(Request $r){return response()->json(['data'=>$r->user()->notifications()->latest()->paginate(20)]);} public function read(Request $r,string $id){$n=$r->user()->notifications()->whereKey($id)->firstOrFail();$n->markAsRead();return response()->json(['message'=>'Notification marked as read.']);} public function readAll(Request $r){$r->user()->unreadNotifications->markAsRead();return response()->json(['message'=>'All notifications marked as read.']);} }
