<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('/json', 'Home::json');
$routes->get('/db', 'Home::db');
