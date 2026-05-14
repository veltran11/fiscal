<?php

namespace App\Controllers;

use App\Core\Request;
use App\Middlewares\JwtMiddleware;

abstract class BaseController
{
    protected function requireAuth(Request $request): array
    {
        return JwtMiddleware::handle($request);
    }
}
