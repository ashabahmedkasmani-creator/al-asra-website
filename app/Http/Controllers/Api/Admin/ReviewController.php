<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $q = Review::with(['user:id,name', 'product:id,name'])->latest('id');
        if ($request->query('status') === 'pending') $q->where('is_approved', false);
        return response()->json($q->paginate(20));
    }

    public function approve(Request $request, Review $review)
    {
        $review->forceFill(['is_approved' => true])->save();
        AuditLogger::record($request, 'review.approved', $review);
        return response()->json(['data' => $review]);
    }

    public function destroy(Request $request, Review $review)
    {
        AuditLogger::record($request, 'review.deleted', $review);
        $review->delete();
        return response()->noContent();
    }
}
