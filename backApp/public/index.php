<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Déterminer si le mode maintenance est activé...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Enregistrer l'autoloader de Composer...
require __DIR__.'/../vendor/autoload.php';

// Démarrer Laravel et gérer la requête...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());