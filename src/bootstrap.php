<?php

namespace Canciella;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

error_reporting(E_ALL);

$environment = 'development';

$whoops = new \Whoops\Run;
if ($environment !== 'production') {
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
} else {
    $whoops->pushHandler(function($e){
        echo 'An error ocurred. Mail sent to developer.';
        mail('antonio.sanchez@inia.es', 'Error canciella', $e);
    });
}
$whoops->register();

$base_url = "$base_domain/go/";

$request = new \Http\HttpRequest($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
$response = new \Http\HttpResponse;


$dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) use ($base_url) {
    $r->addRoute('GET', '/', ['Canciella\Controllers\Main', 'show']);
    $r->addRoute('GET', '/go/{url:.+}', ['Canciella\Controllers\Proxy', 'processUri']);
});

$routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getPath());
switch($routeInfo[0]) {
    case \FastRoute\Dispatcher::NOT_FOUND:
        $content = '404 error';
        $header = ['content-type' => 'none'];
        $response->setStatusCode(404);
        break;
    case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $content = '405 error';
        $header = ['content-type' => 'none'];
        $response->setStatusCode(405);
        break;
    case \FastRoute\Dispatcher::FOUND:
        $className = $routeInfo[1][0];
        $method = $routeInfo[1][1];
        $vars = $routeInfo[2];

        $class = new $className;
        $params = array_values($vars);
        $params[] = $base_url;
        $params[0] .= '?' . $request->getQueryString();
        list($content, $content_type) = call_user_func_array([$class, $method], $params);
        break;
}

$response->addHeader('Content-type', $content_type);
$response->addHeader('Access-Control-Allow-Origin', '*');
$response->setContent($content);

foreach ($response->getHeaders() as $h) {
    header($h, false);
}
echo $response->getContent();
