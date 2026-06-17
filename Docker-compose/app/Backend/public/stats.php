<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/stats/dashboard', function (Request $request, Response $response) {
    try {
        $pdo = Database::getConnection();

        $totalContacts = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts")
            ->fetchColumn();

        $totalSupporters = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE source = 'weezevent'")
            ->fetchColumn();

        $newSupporters = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE source = 'weezevent' AND created_at >= NOW() - INTERVAL '7 days'")
            ->fetchColumn();

        $totalClients = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE source = 'brevo'")
            ->fetchColumn();

        $ticketsSold = (int) $pdo
            ->query("SELECT COALESCE(SUM(ticket_count), 0) FROM contacts")
            ->fetchColumn();

        $invitedCount = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE is_invited = true")
            ->fetchColumn();

        $newContacts = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE created_at >= NOW() - INTERVAL '7 days'")
            ->fetchColumn();

        $emailsSent = 0;
        try {
            $brevo      = new BrevoService();
            $brevoStats = $brevo->getEmailStats();
            $emailsSent = $brevoStats['totalEmailsSent'] ?? 0;
        } catch (\Exception $e) {
            $emailsSent = 0;
        }

        $capacity   = 5000;
        $attendance = $ticketsSold > 0
            ? round(($ticketsSold / $capacity) * 100, 1)
            : 0;

        return jsonResponse($response, [
            'success' => true,
            'data'    => [
                'total_contacts'     => $totalContacts,
                'total_supporters'   => $totalSupporters,
                'new_supporters'     => $newSupporters,
                'total_clients'      => $totalClients,
                'tickets_sold'       => $ticketsSold,
                'attendance_rate'    => $attendance,
                'capacity'           => $capacity,
                'emails_sent'        => $emailsSent,
                'invited_count'      => $invitedCount,
                'segment_count'      => 3,
                'new_contacts_7days' => $newContacts
            ]
        ]);

    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les statistiques',
            'details' => $e->getMessage()
        ], 500);
    }
});