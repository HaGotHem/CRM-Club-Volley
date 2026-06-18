<?php

declare(strict_types=1);

/** @var \Slim\App $app */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/stats/dashboard', function (Request $request, Response $response) {
    try {
        $pdo = Database::getConnection();

        $stats = $pdo
            ->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE source = 'weezevent') as weezevent,
                    COUNT(*) FILTER (WHERE source = 'brevo') as brevo,
                    COUNT(*) FILTER (WHERE source = 'manual') as manual,
                    COUNT(*) FILTER (WHERE date_creation >= NOW() - INTERVAL '7 days') as new_7days
                FROM contact
            ")
            ->fetch();

        $history = $pdo
            ->query("
                WITH months AS (
                    SELECT date_trunc('month', m)::date as month_date
                    FROM generate_series(
                        date_trunc('month', NOW() - INTERVAL '11 months'),
                        date_trunc('month', NOW()),
                        INTERVAL '1 month'
                    ) m
                )
                SELECT 
                    TO_CHAR(m.month_date, 'Mon') as month,
                    COUNT(c.idcontact) as count
                FROM months m
                LEFT JOIN contact c ON date_trunc('month', c.date_creation) = m.month_date
                GROUP BY m.month_date
                ORDER BY m.month_date
            ")
            ->fetchAll();

        $sales_history = $pdo
            ->query("
                WITH months AS (
                    SELECT date_trunc('month', m)::date as month_date
                    FROM generate_series(
                        date_trunc('month', NOW() - INTERVAL '11 months'),
                        date_trunc('month', NOW()),
                        INTERVAL '1 month'
                    ) m
                )
                SELECT 
                    TO_CHAR(m.month_date, 'Mon') as month,
                    COUNT(cb.idbilletweezevent) as count
                FROM months m
                LEFT JOIN billet b ON date_trunc('month', b.date_achat) = m.month_date AND b.type_tarif NOT ILIKE '%invitation%' AND b.type_tarif NOT ILIKE '%gratuit%'
                LEFT JOIN contact_billet cb ON b.idbilletweezevent = cb.idbilletweezevent
                GROUP BY m.month_date
                ORDER BY m.month_date
            ")
            ->fetchAll();

        // Total Sales (only paid tickets for the "Sales" count)
        $total_sales = $pdo->query("
            SELECT COUNT(*) 
            FROM contact_billet cb
            JOIN billet b ON cb.idbilletweezevent = b.idbilletweezevent
            WHERE b.type_tarif NOT ILIKE '%invitation%' AND b.type_tarif NOT ILIKE '%gratuit%'
        ")->fetchColumn();

        $total_groups = $pdo->query("SELECT COUNT(*) FROM segment")->fetchColumn();

        $invited_count = $pdo->query("
            SELECT COUNT(*) 
            FROM contact_billet cb
            JOIN billet b ON cb.idbilletweezevent = b.idbilletweezevent
            WHERE b.type_tarif ILIKE '%invitation%' OR b.type_tarif ILIKE '%gratuit%'
        ")->fetchColumn();

        $paid_sales = $total_sales; // Consistent with total_sales logic now

        // --- DERNIER EVENEMENT ET NOUVEAUX VISITEURS ---
        // 1. Récupérer le dernier événement
        $last_event = $pdo->query("
            SELECT idevenementweezevent, nom_evenement, date
            FROM evenement
            ORDER BY date DESC
            LIMIT 1
        ")->fetch();

        $new_visitors_count = 0;
        $new_visitors_list = [];
        $last_event_name = "Aucun événement";

        if ($last_event) {
            $last_event_id = $last_event['idevenementweezevent'];
            $last_event_name = $last_event['nom_evenement'];

            // 2. Identifier les nouveaux visiteurs :
            // Un nouveau visiteur est un visiteur dont TOUS les tickets sont liés à ce dernier événement.
            // On cherche les contacts qui ont au moins un ticket sur cet événement ET aucun ticket sur d'autres événements.
            $new_visitors_query = "
                SELECT c.idcontact, c.nom, c.prenom, c.email
                FROM contact c
                JOIN contact_billet cb ON c.idcontact = cb.idcontact
                JOIN billet_evenement be ON cb.idbilletweezevent = be.idbilletweezevent
                WHERE be.idevenementweezevent = :last_event_id
                AND NOT EXISTS (
                    SELECT 1
                    FROM contact_billet cb2
                    JOIN billet_evenement be2 ON cb2.idbilletweezevent = be2.idbilletweezevent
                    WHERE cb2.idcontact = c.idcontact
                    AND be2.idevenementweezevent != :last_event_id
                )
                GROUP BY c.idcontact, c.nom, c.prenom, c.email
            ";
            $stmt = $pdo->prepare($new_visitors_query);
            $stmt->execute(['last_event_id' => $last_event_id]);
            $new_visitors_list = $stmt->fetchAll();
            $new_visitors_count = count($new_visitors_list);
        }

        // Helper function for trends
        $getTrend = function($queryCurrent, $queryPrevious) use ($pdo) {
            $current = (int) $pdo->query($queryCurrent)->fetchColumn();
            $previous = (int) $pdo->query($queryPrevious)->fetchColumn();
            if ($previous === 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        // Contacts Trends (30 days)
        $contacts_trend = $getTrend(
            "SELECT COUNT(*) FROM contact WHERE date_creation >= NOW() - INTERVAL '30 days'",
            "SELECT COUNT(*) FROM contact WHERE date_creation >= NOW() - INTERVAL '60 days' AND date_creation < NOW() - INTERVAL '30 days'"
        );

        // Groups Trends (30 days) - assuming groups have no date, we might just compare current count to... well, if no date, trend is hard.
        // Let's check if 'segment' has a date column. If not, we'll return 0 or mock it.
        $hasDateSegment = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'segment' AND column_name = 'date_creation'")->fetchColumn();
        $groups_trend = 0;
        if ($hasDateSegment) {
            $groups_trend = $getTrend(
                "SELECT COUNT(*) FROM segment WHERE date_creation >= NOW() - INTERVAL '30 days'",
                "SELECT COUNT(*) FROM segment WHERE date_creation >= NOW() - INTERVAL '60 days' AND date_creation < NOW() - INTERVAL '30 days'"
            );
        }

        // Sales Trends (30 days)
        $sales_trend = $getTrend(
            "SELECT COUNT(*) FROM contact_billet cb JOIN billet b ON cb.idbilletweezevent = b.idbilletweezevent WHERE b.date_achat >= NOW() - INTERVAL '30 days' AND b.type_tarif NOT ILIKE '%invitation%' AND b.type_tarif NOT ILIKE '%gratuit%'",
            "SELECT COUNT(*) FROM contact_billet cb JOIN billet b ON cb.idbilletweezevent = b.idbilletweezevent WHERE b.date_achat >= NOW() - INTERVAL '60 days' AND b.date_achat < NOW() - INTERVAL '30 days' AND b.type_tarif NOT ILIKE '%invitation%' AND b.type_tarif NOT ILIKE '%gratuit%'"
        );

        // Invitations Trends (30 days)
        $invites_trend = $getTrend(
            "SELECT COUNT(*) FROM contact_billet cb JOIN billet b ON cb.idbilletweezevent = b.idbilletweezevent WHERE b.date_achat >= NOW() - INTERVAL '30 days' AND (b.type_tarif ILIKE '%invitation%' OR b.type_tarif ILIKE '%gratuit%')",
            "SELECT COUNT(*) FROM contact_billet cb JOIN billet b ON cb.idbilletweezevent = b.idbilletweezevent WHERE b.date_achat >= NOW() - INTERVAL '60 days' AND b.date_achat < NOW() - INTERVAL '30 days' AND (b.type_tarif ILIKE '%invitation%' OR b.type_tarif ILIKE '%gratuit%')"
        );

        return jsonResponse($response, [
            'success' => true,
            'data'    => [
                'total_contacts'     => (int) $stats['total'],
                'weezevent_contacts' => (int) $stats['weezevent'],
                'brevo_contacts'     => (int) $stats['brevo'],
                'manual_contacts'    => (int) $stats['manual'],
                'new_contacts_7days' => (int) $stats['new_7days'],
                'history'            => $history,
                'sales_history'      => $sales_history,
                'total_sales'        => (int) $total_sales,
                'total_groups'       => (int) $total_groups,
                'invited_count'      => (int) $invited_count,
                'paid_sales'         => (int) $paid_sales,
                'last_event'         => [
                    'name'                => $last_event_name,
                    'new_visitors_count'  => $new_visitors_count,
                    'new_visitors_list'   => $new_visitors_list
                ],
                'trends'             => [
                    'contacts'    => $contacts_trend,
                    'groups'      => $groups_trend,
                    'invitations' => $invites_trend,
                    'sales'       => $sales_trend
                ]
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