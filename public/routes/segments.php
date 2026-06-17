<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Repositories\SegmentRepository;
use App\Repositories\ContactRepository;

$app->get('/api/segments', function (Request $request, Response $response) {
    try {
        $repository = new SegmentRepository();
        $segments = $repository->findAll();

        return jsonResponse($response, [
            'success' => true,
            'data'    => $segments
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les segments',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->get('/api/segments/{id}/contacts', function (Request $request, Response $response, array $args) {
    try {
        $segmentId = (int) $args['id'];
        $contactRepo = new ContactRepository();
        $segmentRepo = new SegmentRepository();

        $segment = $segmentRepo->findById($segmentId);
        if (!$segment) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Segment inconnu'
            ], 404);
        }

        $contacts = $contactRepo->findBySegmentId($segmentId);

        return jsonResponse($response, [
            'success' => true,
            'segment' => $segment->getNomSegment(),
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