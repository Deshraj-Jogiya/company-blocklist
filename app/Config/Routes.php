<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('blocked-companies', 'BlockedCompanies::index');
$routes->get('blocked-companies/warn-check', 'BlockedCompanies::warnCheck');
$routes->get('blocked-companies/(:num)', 'BlockedCompanies::show/$1');
$routes->post('blocked-companies', 'BlockedCompanies::create');
$routes->post('blocked-companies/import', 'BlockedCompanies::import');
$routes->delete('blocked-companies/(:num)', 'BlockedCompanies::delete/$1');
