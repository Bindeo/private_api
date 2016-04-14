<?php

// Error handler
$c = $app->getContainer();
$c['errorHandler'] = function ($c) {
    return function ($request, $response, \Exception $exception) use ($c) {
        $error = ['error' => ['code' => $exception->getCode()]];

        switch ($exception->getCode()) {
            case 400:
            case 403:
            case 409:
            case 503:
                $error['error']['message'] = $exception->getMessage();
                break;
            case 401:
                $error['error']['message'] = 'Unauthorized access';
                break;
            case 500:
                $error['error']['message'] = $exception->getMessage();
                // Write it into monolog
                $c->get('logger')
                  ->error($exception->getCode() . ' ' . $exception->getMessage(), $exception->getTrace());
                break;
        }

        return $c['response']->withJson($error, $exception->getCode());
    };
};

$c['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        return $c['response']->withJson(['error' => ['code' => 404, 'message' => 'Page not found']], 404);
    };
};

$c['notAllowedHandler'] = function ($c) {
    return function ($request, $response, $methods) use ($c) {
        return $c['response']->withJson([
            'error' => [
                'code'    => 405,
                'message' => 'Method not valid'
            ]
        ], 405);
    };
};