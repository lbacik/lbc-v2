<?php

declare(strict_types=1);

// Handles one rejected POST /contact through the prod kernel so that
// ProdContactAuditLogTest can inspect what the prod log config writes to
// stderr. Runs in a separate process: prod logging goes to php://stderr.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

(new Dotenv())->bootEnv($root.'/.env');

$kernel = new Kernel('prod', false);
// No CSRF token, so the request is rejected before any send: a non-error
// response that a buffered (fingers_crossed) handler would never flush.
$request = Request::create('/contact', 'POST', ['name' => 'Audit Check'], [], [], ['REMOTE_ADDR' => '203.0.113.'.random_int(1, 254)]);
$response = $kernel->handle($request);
echo $response->getStatusCode();
$kernel->terminate($request, $response);
