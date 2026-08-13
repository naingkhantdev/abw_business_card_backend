<?php
// bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Do NOT call $middleware->use([...]) here. use() REPLACES the whole
        // global stack rather than appending to it, which previously dropped
        // ConvertEmptyStringsToNull and TrimStrings and let blank multipart
        // fields overwrite real column values. HandleCors is already part of
        // Laravel's default global stack, so no registration is needed.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();