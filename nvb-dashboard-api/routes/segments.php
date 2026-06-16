<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/segments', function (Request $request, Response $response) {
    $segments = [
        [
            'id'          => 'nouveaux-visiteurs',
            'label'       => 'Nouveaux visiteurs',
            'description' => 'Contacts ajoutés ces 7 derniers jours'
        ],
        [
            'id'          => 'supporters-reguliers',
            'label'       => 'Supporters réguliers',
            'description' => 'Contacts venus via Weezevent'
        ],
        [
            'id'          => 'abonnes-potentiels',
            'label'       => 'Abonnés potentiels',
            'description' => 'Contacts avec email valide à fort potentiel'
        ]
    ];

    return jsonResponse($response, [
        'success' => true,
        'data'    => $segments
    ]);
});

$app->get('/api/segments/{id}/contacts', function (Request $request, Response $response, array $args) {
    try {
        $segmentId = $args['id'];
        $pdo = Database::getConnection();

        if ($segmentId === 'nouveaux-visiteurs') {
            $sql = "SELECT id, first_name, last_name, email, phone, source
                    FROM contacts
                    WHERE created_at >= NOW() - INTERVAL '7 days'
                    ORDER BY created_at DESC";
        } elseif ($segmentId === 'supporters-reguliers') {
            $sql = "SELECT id, first_name, last_name, email, phone, source
                    FROM contacts
                    WHERE source = 'weezevent'
                    ORDER BY created_at DESC";
        } elseif ($segmentId === 'abonnes-potentiels') {
            $sql = "SELECT id, first_name, last_name, email, phone, source
                    FROM contacts
                    WHERE email IS NOT NULL
                    ORDER BY created_at DESC";
        } else {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Segment inconnu'
            ], 404);
        }

        $contacts = $pdo->query($sql)->fetchAll();

        return jsonResponse($response, [
            'success' => true,
            'segment' => $segmentId,
            'data'    => $contacts
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les contacts du segment',
            'details' => $e->getMessage()
        ], 500);
    }
});