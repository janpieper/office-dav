<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

$pdo = new PDO(
  sprintf('mysql:host=%s;dbname=%s', OFFICE_DB_HOST, OFFICE_DB_NAME),
  OFFICE_DB_USER,
  OFFICE_DB_PASS
);

$authBackend = new Office\DAV\Auth\Backend\PDO($pdo, OFFICE_AUTH_REALM);
$principalBackend = new Office\DAVACL\PrincipalBackend\PDO($pdo);
$cardDavBackend = new Office\CardDAV\Backend\PDO($pdo);
$calDavBackend = new Office\CalDAV\Backend\PDO($pdo);

$server = new Sabre\DAV\Server(array(
  new Sabre\DAVACL\PrincipalCollection($principalBackend),
  new Sabre\CardDAV\AddressBookRoot($principalBackend, $cardDavBackend),
  new Sabre\CalDAV\CalendarRootNode($principalBackend, $calDavBackend)
));

$server->debugExceptions = OFFICE_DEBUG;
$server->setBaseUri(OFFICE_BASEURI);

$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend, OFFICE_AUTH_REALM));
$server->addPlugin(new Sabre\CardDAV\Plugin);
$server->addPlugin(new Sabre\CalDAV\Plugin);
$server->addPlugin(new Sabre\DAVACL\Plugin);
$server->addPlugin(new Sabre\DAV\Sync\Plugin);
$server->addPlugin(new Office\DAV\Birthday\Plugin);

$server->exec();
