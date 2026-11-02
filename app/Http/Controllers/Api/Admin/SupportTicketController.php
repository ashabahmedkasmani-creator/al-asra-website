<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Notifications\SupportTicketUpdateNotification;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'pending', 'resolved', 'closed'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SupportTicket::with([
            'user:id,name,email', 'order:id,reference', 'assignee:id,name',
        ])->withCount('messages')->latest('last_message_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        return response()->json(['data' => $query->paginate($validated['per_page'] ?? 25)]);
    }

    public function show(SupportTicket $ticket)
    {
        return response()->json(['data' => $ticket->load([
            'user:id,name,email', 'order:id,reference', 'assignee:id,name', 'messages.user:id,name',
        ])]);
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'pending', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        if (! empty($validated['assigned_to'])) {
            $assignee = User::findOrFail($validated['assigned_to']);
            abort_unless($assignee->isStaffOrAdmin() && $assignee->is_active, 422, 'Assignee must be active staff/admin.');
        }

        $old = $ticket->only(['status', 'priority', 'assigned_to']);
        $ticket->update($validated);
        AuditLogger::record($request, 'support_ticket.updated', $ticket, $old, $ticket->only(array_keys($old)));

        return response()->json(['data' => $ticket->fresh(['user', 'assignee'])]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);
        $internal = (bool) ($validated['is_internal'] ?? false);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => trim(strip_tags($validated['message'])),
            'is_internal' => $internal,
        ]);
        $ticket->forceFill([
            'last_message_at' => now(),
            'status' => $internal ? $ticket->status : 'pending',
        ])->save();

        if (! $internal && $ticket->user) {
            $ticket->user->notify(new SupportTicketUpdateNotification(
                $ticket->id,
                $ticket->reference,
                'A support agent replied to your ticket.'
            ));
        }

        AuditLogger::record($request, 'support_ticket.replied', $ticket);

        return response()->json(['data' => $ticket->load('messages.user:id,name')], 201);
    }
}
