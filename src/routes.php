<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'index.html', $args);
});

$app->get('/lines', function (Request $request, Response $response, array $args) use ($app) {
    return $response->withStatus(200)->withJson($this->metroData->getLines());
});

$app->get('/stations', function (Request $request, Response $response, array $args) use ($app) {
    return $response->withStatus(200)->withJson($this->metroData->getStations());
});

$app->get('/home', function (Request $request, Response $response, array $args) use ($app) {
    return $response->withStatus(200)->withJson(array(
        'lines' => $this->metroData->getLines(),
        'stations' => $this->metroData->getStations(),
    ));
});

$app->get('/station/{code}', function (Request $request, Response $response, array $args) use ($app) {
    return $response->withStatus(200)->withJson($this->metroData->getStation($args['code']));
});
