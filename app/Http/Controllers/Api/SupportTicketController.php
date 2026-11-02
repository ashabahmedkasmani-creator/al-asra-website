<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->supportTickets()->withCount('messages')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:180'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'order_reference' => ['nullable', 'string', 'max:24'],
        ]);

        $order = null;
        if (! empty($validated['order_reference'])) {
            $order = $request->user()->orders()
                ->where('reference', strtoupper($validated['order_reference']))
                ->firstOrFail();
        }

        $ticket = SupportTicket::create([
            'reference' => 'AST-'.strtoupper(Str::random(10)),
            'user_id' => $request->user()->id,
            'order_id' => $order?->id,
            'subject' => strip_tags($validated['subject']),
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => trim(strip_tags($validated['message'])),
        ]);

        return response()->json([
            'data' => $ticket->load(['messages.user:id,name', 'order:id,reference']),
        ], 201);
    }

    public function show(Request $request, int $ticket)
    {
        $supportTicket = $request->user()->supportTickets()
            ->with([
                'messages' => fn ($query) => $query->where('is_internal', false)->with('user:id,name'),
                'order:id,reference',
            ])
            ->findOrFail($ticket);

        return response()->json(['data' => $supportTicket]);
    }

    public function reply(Request $request, int $ticket)
    {
        $supportTicket = $request->user()->supportTickets()->findOrFail($ticket);
        abort_if(in_array($supportTicket->status, ['resolved', 'closed'], true), 422, 'This ticket is closed.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $supportTicket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => trim(strip_tags($validated['message'])),
        ]);
        $supportTicket->forceFill(['last_message_at' => now(), 'status' => 'open'])->save();

        return response()->json(['data' => $supportTicket->load('messages.user:id,name')], 201);
    }
}
