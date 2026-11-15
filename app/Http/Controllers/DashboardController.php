<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $bookingsThisMonth = $workspace?->appointments()
            ->where('starts_at', '>=', now()->startOfMonth())
            ->where('status', 'confirmed')
            ->count() ?? 0;
        $activeConversations = $workspace?->conversations()
            ->where('status', 'active')
            ->count() ?? 0;

        return Inertia::render('Dashboard', [
            'stats' => [
                'bookings_this_month' => $bookingsThisMonth,
                'active_conversations' => $activeConversations,
                'hours_saved' => round(($bookingsThisMonth * ($workspace?->appointment_duration_minutes ?? 0)) / 60, 1),
            ],
            'setup' => [
                'workspace' => $workspace !== null,
                'whatsapp' => $workspace?->whatsAppConnection?->status === 'connected',
                'calendar' => $workspace?->calendarConnection?->status === 'connected',
                'assistant' => $workspace !== null
                    && filled($workspace->bot_instructions)
                    && $workspace->services()->where('is_active', true)->exists(),
            ],
        ]);
    }
}
