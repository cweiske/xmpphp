<?php

/**
 * @file: XMPPHP Send message example
 *
 * @info: If this script doesn't work, are you running 64-bit PHP with < 5.2.6?
 */
/**
 * Activate full error reporting
 * error_reporting(E_ALL & E_STRICT);
 *
 * XMPPHP Log levels:
 *
 * LEVEL_ERROR   = 0;
 * LEVEL_WARNING = 1;
 * LEVEL_INFO    = 2;
 * LEVEL_DEBUG   = 3;
 * LEVEL_VERBOSE = 4;
 */

require_once __DIR__.'/../vendor/autoload.php';

$conf = array(
  'server'   => 'talk.google.com',
  'port'     => 5222,
  'username' => 'username',
  'password' => 'password',
  'proto'    => 'xmpphp',
  'domain'   => 'gmail.com',
  'printlog' => true,
  'loglevel' => XMPPHP\Log::LEVEL_VERBOSE,
);

// Easy and simple for access to variables with their names
extract($conf);

$XMPP = new XMPPHP\XMPP($server, $port, $username, $password, $proto, $domain, $printlog, $loglevel);

try {
    $XMPP->connect();
    $XMPP->processUntil('session_start', 10);
    $XMPP->presence();
    $XMPP->message('target.user@jabber.domain.com', 'Hello, how are you?', 'chat');
    $XMPP->disconnect();
} catch (XMPPHP\Exception $e) {
    die($e->getMessage());
}
