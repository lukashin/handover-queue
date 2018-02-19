<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Http\Response;

require_once "../vendor/autoload.php";

$config = [];
$config['settings'] = [
    'dataDirPath' => realpath('../data'),
    'displayErrorDetails' => true,
];

$app = new \Slim\App($config);

$container = $app->getContainer();
$container['view'] = function ($container) {
    $twigConfig = [
        'cache' => realpath('../var/cache/twig'),
        'debug' => true,
    ];
    $twigConfig['cache'] = false;
    $view = new \Slim\Views\Twig(realpath('../templates'), $twigConfig);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));

    return $view;
};

$app->get('/warehouse/{uuid}', function (Request $request, Response $response, array $args) {
    $warehouseId = $args['uuid'];

    return $response->withJson(json_decode(file_get_contents('../data/'.$warehouseId.'.json')));
})->setName('warehouse');

$app->get('/queue/{uuid}', function (Request $request, Response $response, array $args) {
    $warehouseUuid = $args['uuid'];

    $statusWeight = [
        'readyForPickup' => 10,
        'assemblyInProgress' => 20,
        'new' => 30,
        'handedOver' => 40,
    ];

    $contents = file_get_contents('../config/config.json');
    $config = \json_decode($contents, JSON_OBJECT_AS_ARRAY);

    $data = [];
    $warehouseDataPath = sprintf('%s/%s', $this->get('settings')['dataDirPath'], $warehouseUuid);
    $itemFiles = scandir($warehouseDataPath);
    foreach ($itemFiles as $file) {
        if (in_array($file, ['.', '..'])) continue;

        $itemFilePath = sprintf('%s/%s', $warehouseDataPath, $file);
        $itemData = json_decode(file_get_contents($itemFilePath), JSON_OBJECT_AS_ARRAY);
        if ($itemData && isset($itemData['status']) && isset($itemData['status'])) {
            // check datetime
            $status = $itemData['status'];
            $itemData['statusWeight'] = $statusWeight[$itemData['status']];
            $itemData['updated'] = new \DateTime($itemData['updated']);
            $itemData['translated'] = $config['status'][$status]['queue'];

            $data[] = $itemData;
        }
    }

    usort($data, function ($a, $b) {
        if ($a['statusWeight'] < $b['statusWeight']) {
            return -1;
        }
        if ($a['statusWeight'] > $b['statusWeight']) {
            return 1;
        }
        if ($a['updated'] < $b['updated']) {
            return -1;
        }
        if ($a['updated'] > $b['updated']) {
            return 1;
        }

        return 0;
    });

    $warehouse = [];
    foreach ($config['warehouses'] as $w) {
        if ($w['uuid'] == $warehouseUuid) {
            $warehouse = $w;
        }
    }

    return $this->view->render($response, 'queue.twig', [
        'queue' => $data,
        'config' => $config,
        'warehouse' => $warehouse,
    ]);
})->setName('queue');

$app->post(
    '/warehouse/{warehouseUuid}/{status:new|assemblyInProgress|readyForPickup|handedOver|reset}/{itemUuid}[/{caption}]',
    function (Request $request, Response $response, array $args) use ($app) {
        $contents = file_get_contents('../config/config.json');
        $config = \json_decode($contents, JSON_OBJECT_AS_ARRAY);
        $allowedStatuses = array_keys($config['status']);
        $allowedStatuses[] = 'reset';

        $errors = [];

        $parsedRequest = $request->getParsedBody();
        $warehouseUuid = $args['warehouseUuid'];
        if (!$warehouseUuid && isset($parsedRequest['warehouseUuid'])) {
            $warehouseUuid = trim($parsedRequest['warehouseUuid']);
        }

        if (!$warehouseUuid) {
            $errors[] = 'parameter warehouseUuid missing';
        } elseif (!\Ramsey\Uuid\Uuid::isValid($warehouseUuid)) {
            $errors[] = 'parameter warehouseUuid value is invalid, UUIDv4 expected';
        }

        $itemUuid = $args['itemUuid'];
        if (!$itemUuid && isset($parsedRequest['itemUuid'])) {
            $itemUuid = trim($parsedRequest['itemUuid']);
        }

        if (!$itemUuid) {
            $errors[] = 'parameter itemUuid missing';
        } elseif (!\Ramsey\Uuid\Uuid::isValid($itemUuid)) {
            $errors[] = 'parameter itemUuid value is invalid, UUIDv4 expected';
        }

        $status = $args['status'];
        if (!in_array($status, $allowedStatuses)) {
            $errors[] = 'parameter status value  is not expected';
        }

        $caption = trim($args['caption']);
        if (!$caption && isset($parsedRequest['caption'])) {
            $caption = trim($parsedRequest['caption']);
        }

        if (!$caption && $status == 'new') {
            $errors[] = 'parameter caption non-null value expected for status=new';
        }

        $warehouse = [];
        foreach ($config['warehouses'] as $w) {
            if ($w['uuid'] == $warehouseUuid) {
                $warehouse = $w;
            }
        }
        if (!$warehouse) {
            $errors[] = 'warehouse with provided UUID not found';
        }

        if ($errors) {
            return $response->withJson(['errors' => $errors], 409);
        }

        // check if warehouse with this id exists
        $itemDataPath = sprintf(
            '%s/%s/%s.json',
            $this->get('settings')['dataDirPath'],
            $warehouseUuid,
            $itemUuid
        );

        $referer = $request->getHeader('HTTP_REFERER');
        if ($referer && is_array($referer)) {
            $referer = reset($referer);
        }

        if ('reset' === $status && file_exists($itemDataPath)) {
            unlink($itemDataPath);

            if ($referer) {
                return $response->withRedirect($referer);
            }

            return $response->withStatus(204);
        }

        $existingFileFound = file_exists($itemDataPath);
        if ($existingFileFound) {
            $data = json_decode(file_get_contents($itemDataPath), JSON_OBJECT_AS_ARRAY|JSON_UNESCAPED_UNICODE);
        } else {
            $data = [];
        }

        $data['uuid'] = $itemUuid;
        $data['status'] = $status;
        $data['updated'] = (new \DateTime('now'))->format('Y-m-d H:i:s');
        if ($caption) {
            $data['caption'] = $caption;
        }

        // used for warehouse-side ordering
        if (!isset($data['created'])) {
            $data['created'] = (new \DateTime('now'))->format('Y-m-d H:i:s');
        }

        $warehouseDataDirPath = dirname($itemDataPath);
        if (!is_dir($warehouseDataDirPath)) {
            mkdir($warehouseDataDirPath, 0777, true);
        }

        $json = \json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
        $bytesWritten = file_put_contents($itemDataPath, $json);
        if ($bytesWritten) {
            chmod($itemDataPath, 0644);

            if ($referer) {
                return $response->withRedirect($referer);
            }

            return $response->withJson($data,$existingFileFound ? 200 : 201, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }

        return $response->withStatus(500);
})->setName('setStatus');



$app->get('/queue/{uuid}/manage', function (Request $request, Response $response, array $args) {
    $warehouseUuid = $args['uuid'];

    // get config of warehouses

    $statusWeight = [
        'readyForPickup' => 10,
        'assemblyInProgress' => 20,
        'new' => 30,
        'handedOver' => 40,
    ];

    $contents = file_get_contents('../config/config.json');
    $config = \json_decode($contents, JSON_OBJECT_AS_ARRAY);

    $data = [];
    $warehouseDataPath = sprintf('%s/%s', $this->get('settings')['dataDirPath'], $warehouseUuid);
    $itemFiles = scandir($warehouseDataPath);
    foreach ($itemFiles as $file) {
        if (in_array($file, ['.', '..'])) continue;

        $itemFilePath = sprintf('%s/%s', $warehouseDataPath, $file);
        $itemData = json_decode(file_get_contents($itemFilePath), JSON_OBJECT_AS_ARRAY);
        if ($itemData && isset($itemData['status']) && isset($itemData['status'])) {
            // check datetime
            $status = $itemData['status'];
            $itemData['statusWeight'] = $statusWeight[$itemData['status']];
            $itemData['updated'] = new \DateTime($itemData['updated']);
            $itemData['translated'] = $config['status'][$status]['queue'];

            $data[] = $itemData;
        }
    }

    $warehouse = [];
    foreach ($config['warehouses'] as $w) {
        if ($w['uuid'] == $warehouseUuid) {
            $warehouse = $w;
        }
    }

    return $this->view->render($response, 'manage.twig', [
        'queue' => $data,
        'config' => $config,
        'warehouseUuid' => $warehouseUuid,
        'warehouse' => $warehouse,
        'newUuid' => \Ramsey\Uuid\Uuid::uuid4(),
    ]);
})->setName('manage');


$app->get('/', function (Request $request, Response $response, array $args) use ($app) {
    $contents = file_get_contents('../config/config.json');
    $config = \json_decode($contents, JSON_OBJECT_AS_ARRAY);

    return $this->view->render($response, 'index.twig', ['config' => $config]);
})->setName('index');

$app->run();
