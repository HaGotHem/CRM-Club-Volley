<?php

declare(strict_types=1);

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
                        date_trunc('month', NOW() - INTERVAL '5 months'),
                        date_trunc('month', NOW()),
                        INTERVAL '1 month'
                    ) m
                )
                SELECT 
                    TO_CHAR(m.month_date, 'Mon') as month,
                    COALESCE(SUM(b.quantite), 0) as count
                FROM months m
                LEFT JOIN billet b ON date_trunc('month', b.date_achat) = m.month_date
                GROUP BY m.month_date
                ORDER BY m.month_date
            ")
            ->fetchAll();

        $total_sales = $pdo->query("SELECT COALESCE(SUM(quantite), 0) FROM billet")->fetchColumn();

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
                'total_sales'        => (int) $total_sales
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