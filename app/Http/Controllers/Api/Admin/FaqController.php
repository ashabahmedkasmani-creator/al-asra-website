<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Faq::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $faq = Faq::create($this->validated($request, true));
        AuditLogger::record($request, 'faq.created', $faq);
        return response()->json(['data' => $faq], 201);
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validated($request, false));
        AuditLogger::record($request, 'faq.updated', $faq);
        return response()->json(['data' => $faq->fresh()]);
    }

    public function destroy(Request $request, Faq $faq)
    {
        AuditLogger::record($request, 'faq.deleted', $faq, ['question' => $faq->question]);
        $faq->delete();
        return response()->noContent();
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'question' => [$req, 'string', 'max:255'], 'answer' => [$req, 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean'],
        ]);
        foreach (['question', 'answer'] as $f) {
            if (isset($data[$f])) $data[$f] = trim(strip_tags($data[$f]));
        }
        return $data;
    }
}
