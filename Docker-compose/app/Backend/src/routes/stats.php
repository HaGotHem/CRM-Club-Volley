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

        $weezeventContacts = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE source = 'weezevent'")
            ->fetchColumn();

        $brevoContacts = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE source = 'brevo'")
            ->fetchColumn();

        $manualContacts = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE source = 'manual'")
            ->fetchColumn();

        $newContacts = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE created_at >= NOW() - INTERVAL '7 days'")
            ->fetchColumn();

        $invitedCount = (int) $pdo
            ->query("SELECT COUNT(*) FROM contacts WHERE is_invited = true")
            ->fetchColumn();

        $ticketsSold = (int) $pdo
            ->query("SELECT COALESCE(SUM(ticket_count), 0) FROM contacts")
            ->fetchColumn();

        return jsonResponse($response, [
            'success' => true,
            'data'    => [
                'total_contacts'     => $totalContacts,
                'weezevent_contacts' => $weezeventContacts,
                'brevo_contacts'     => $brevoContacts,
                'manual_contacts'    => $manualContacts,
                'new_contacts_7days' => $newContacts,
                'invited_count'      => $invitedCount,
                'tickets_sold'       => $ticketsSold,
                'segment_count'      => 3
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