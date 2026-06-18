<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/stats/dashboard', function (Request $request, Response $response) {
    try {
        // Résultat mis en cache 60s : les requêtes ne sont rejouées qu'une fois
        // par minute, quel que soit le nombre d'affichages/refresh du dashboard.
        $data = cacheRemember('stats_dashboard', 60, function (): array {
            $pdo = Database::getConnection();

            // --- Requête 1 : table "contact" parcourue UNE SEULE fois ---------------
            // (totaux + répartition par source + nouveaux 7 jours + historique 12 mois)
            // Le CTE `c AS MATERIALIZED` force un unique scan ; les agrégats et
            // l'historique lisent ensuite ce résultat matérialisé.
            $contactRow = $pdo->query("
                WITH c AS MATERIALIZED (
                    SELECT idcontact, source, date_creation,
                           date_trunc('month', date_creation)::date AS month_date
                    FROM contact
                ),
                months AS (
                    SELECT date_trunc('month', m)::date AS month_date
                    FROM generate_series(
                        date_trunc('month', NOW() - INTERVAL '11 months'),
                        date_trunc('month', NOW()),
                        INTERVAL '1 month'
                    ) m
                ),
                history AS (
                    SELECT m.month_date,
                           TO_CHAR(m.month_date, 'Mon') AS month,
                           COUNT(c.idcontact) AS count
                    FROM months m
                    LEFT JOIN c ON c.month_date = m.month_date
                    GROUP BY m.month_date
                )
                SELECT
                    (SELECT COUNT(*) FROM c)                                                  AS total,
                    (SELECT COUNT(*) FROM c WHERE source = 'weezevent')                       AS weezevent,
                    (SELECT COUNT(*) FROM c WHERE source = 'brevo')                           AS brevo,
                    (SELECT COUNT(*) FROM c WHERE source = 'manual')                          AS manual,
                    (SELECT COUNT(*) FROM c WHERE date_creation >= NOW() - INTERVAL '7 days') AS new_7days,
                    (SELECT json_agg(json_build_object('month', month, 'count', count) ORDER BY month_date)
                       FROM history)                                                          AS history
            ")->fetch();

            // --- Requête 2 : table "billet" parcourue UNE SEULE fois ----------------
            // (total des ventes + historique des ventes sur 6 mois)
            $billetRow = $pdo->query("
                WITH b AS MATERIALIZED (
                    SELECT quantite,
                           date_trunc('month', date_achat)::date AS month_date
                    FROM billet
                ),
                months AS (
                    SELECT date_trunc('month', m)::date AS month_date
                    FROM generate_series(
                        date_trunc('month', NOW() - INTERVAL '5 months'),
                        date_trunc('month', NOW()),
                        INTERVAL '1 month'
                    ) m
                ),
                sales_history AS (
                    SELECT m.month_date,
                           TO_CHAR(m.month_date, 'Mon') AS month,
                           COALESCE(SUM(b.quantite), 0) AS count
                    FROM months m
                    LEFT JOIN b ON b.month_date = m.month_date
                    GROUP BY m.month_date
                )
                SELECT
                    (SELECT COALESCE(SUM(quantite), 0) FROM b) AS total_sales,
                    (SELECT json_agg(json_build_object('month', month, 'count', count) ORDER BY month_date)
                       FROM sales_history)                     AS sales_history
            ")->fetch();

            return [
                'total_contacts'     => (int) $contactRow['total'],
                'weezevent_contacts' => (int) $contactRow['weezevent'],
                'brevo_contacts'     => (int) $contactRow['brevo'],
                'manual_contacts'    => (int) $contactRow['manual'],
                'new_contacts_7days' => (int) $contactRow['new_7days'],
                'history'            => json_decode($contactRow['history'] ?? '[]', true) ?: [],
                'sales_history'      => json_decode($billetRow['sales_history'] ?? '[]', true) ?: [],
                'total_sales'        => (int) $billetRow['total_sales'],
            ];
        });

        return jsonResponse($response, [
            'success' => true,
            'data'    => $data,
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les statistiques',
            'details' => $e->getMessage()
        ], 500);
    }
});
