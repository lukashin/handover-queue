<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Http\Response;

require_once "../vendor/autoload.php";

$config = [
    'dataDirPath' => realpath('../data'),
];

$app = new \Slim\App($config);




$app->get('/warehouse/{uuid}', function (Request $request, Response $response, array $args) {
    $warehouseId = $args['uuid'];

    return $response->withJson(json_decode(file_get_contents('../data/'.$warehouseId.'.json')));
})->setName('warehouse');

$app->get('/queue/{uuid}', function (Request $request, Response $response, array $args) {
    $warehouseId = $args['uuid'];

    return $response->withJson(json_decode(file_get_contents('../data/'.$warehouseId.'.json')));
})->setName('queue');


$app->post('/warehouse/{warehouseUuid}/new/{itemUuid}', function (Request $request, Response $response, array $args) use ($app) {
    $warehouseUuid = $args['warehouseUuid'];
    $itemUuid = $args['itemUuid'];

    $itemDataPath = sprintf(
        '%s/%s/$s.json',
        $app->getContainer()->get('settings')['dataDirPath'],
        $warehouseUuid,
        $itemUuid
    );
    $data = [
        'uuid' => $itemUuid,
        'caption' => $itemUuid, // ToDo
        'status' => 'new',
        'updates' => (new \DateTime('now'))->format('Y-m-d H:i:s'), // ToDo
    ];
    $bytesWritten = file_put_contents($itemDataPath, \json_encode($data, JSON_PRETTY_PRINT));
    if ($bytesWritten) {
        return $response->withStatus(201);
    }

    return $response->withStatus(500);
})->setName('new');


$app->post('/warehouse/{warehouseUuid}/reset/{itemUuid}', function (Request $request, Response $response, array $args) use ($app){
    $warehouseUuid = $args['warehouseUuid'];
    $itemUuid = $args['itemUuid'];

    $itemDataPath = sprintf(
        '%s/%s/$s.json',
        $app->getContainer()->get('settings')['dataDirPath'],
        $warehouseUuid,
        $itemUuid
    );

    if (file_exists($itemDataPath)) {
        unlink($itemDataPath);
    }

    return $response->withStatus(201);
})->setName('reset');


$app->get('/', function (Request $request, Response $response, array $args) use ($app) {
    foreach(scandir('../data') as $file) {
        $path = '../data/'.$file;
        if (is_file($path) && is_readable($path)) {
            $json = \json_decode(file_get_contents($path), JSON_OBJECT_AS_ARRAY);
//            print_r($json);
            /** @var \Slim\Interfaces\RouterInterface $router */
            $router = $app->getContainer()->get('router');

            $response->getBody()->write('<a href="'.$router->pathFor('warehouse', ['uuid' => $json['uuid']]).'">'.$json['name'].' / wh</a><br>');
            $response->getBody()->write('<a href="'.$router->pathFor('queue', ['uuid' => $json['uuid']]).'">'.$json['name'].' / q</a><br>');
        }
    }

    return $response;
});

$app->run();
