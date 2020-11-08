<?php
/**
 * XMPPHP: The PHP XMPP Library
 * Copyright (C) 2008  Nathanael C. Fritz
 * This file is part of SleekXMPP.
 *
 * XMPPHP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * XMPPHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with XMPPHP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category   xmpphp
 * @package    XMPPHP
 * @author     Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author     Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author     Michael Garvin <JID: gar@netflint.net>
 * @author     Alexander Birkner (https://github.com/BirknerAlex)
 * @author     zorn-v (https://github.com/zorn-v/xmpphp/)
 * @author     GNU social
 * @copyright  2008 Nathanael C. Fritz
 */

namespace XMPPHP;

/** Exception */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception.php';

/** XMLObj */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'XMLObj.php';

/** Log */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Log.php';


/**
 * XMPPHP XMLStream
 *
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 * @version   $Id$
 */
class XMLStream
{
    /**
     * @var resource
     */
    protected $socket;
    /**
     * @var resource
     */
    protected $parser;
    /**
     * @var string
     */
    protected $buffer;
    /**
     * @var int
     */
    protected $xml_depth = 0;
    /**
     * @var string
     */
    protected $host;
    /**
     * @var integer
     */
    protected $port;
    /**
     * @var string
     */
    protected $stream_start = '<stream>';
    /**
     * @var string
     */
    protected $stream_end = '</stream>';
    /**
     * @var bool
     */
    protected $disconnected = false;
    /**
     * @var bool
     */
    protected $sent_disconnect = false;
    /**
     * @var array
     */
    protected $ns_map = [];
    /**
     * @var array
     */
    protected $current_ns = [];
    /**
     * @var array
     */
    protected $xmlobj = null;
    /**
     * @var array
     */
    protected $nshandlers = [];
    /**
     * @var array
     */
    protected $xpathhandlers = [];
    /**
     * @var array
     */
    protected $idhandlers = [];
    /**
     * @var array
     */
    protected $eventhandlers = [];
    /**
     * @var int
     */
    protected $lastid = 0;
    /**
     * @var string
     */
    protected $default_ns;
    /**
     * @var string[]
     */
    protected $until = [];
    /**
     * @var int[]
     */
    protected $until_count = [];
    /**
     * @var array
     */
    protected $until_happened = false;
    /**
     * @var array
     */
    protected $until_payload = [];
    /**
     * @var Log
     */
    protected $log;
    /**
     * @var bool
     */
    protected $reconnect = true;
    /**
     * @var bool
     */
    protected $been_reset = false;
    /**
     * @var bool
     */
    protected $is_server;
    /**
     * @var float
     */
    protected $last_send = 0;
    /**
     * @var bool
     */
    protected $use_ssl = false;
    /**
     * @var int
     */
    protected $reconnectTimeout = 30;

    /**
     * Constructor
     *
     * @param string|null $host (optional)
     * @param string|null $port (optional)
     * @param bool $print_log (optional)
     * @param string $log_level (optional)
     * @param bool $is_server (optional)
     */
    public function __construct(
        ?string $host = null,
        ?string $port = null,
        bool $print_log = false,
        ?string $log_level = null,
        bool $is_server = false
    ) {
        $this->reconnect = !$is_server;
        $this->is_server = $is_server;
        $this->host = $host;
        $this->port = $port;
        $this->setupParser();
        $this->log = new Log($print_log, $log_level);
    }

    /**
     * Setup the XML parser
     */
    public function setupParser(): void
    {
        $this->parser = xml_parser_create('UTF-8');
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'startXML', 'endXML');
        xml_set_character_data_handler($this->parser, 'charXML');
    }

    /**
     * Destructor
     * Cleanup connection
     * @throws Exception
     */
    public function __destruct()
    {
        if (!$this->disconnected && $this->socket) {
            $this->disconnect();
        }
    }

    /**
     * Disconnect from XMPP Host
     * @throws Exception
     */
    public function disconnect(): void
    {
        $this->log->log("Disconnecting...", Log::LEVEL_VERBOSE);
        if (false == (bool)$this->socket) {
            return;
        }
        $this->reconnect = false;
        $this->send($this->stream_end);
        $this->sent_disconnect = true;
        $this->processUntil('end_stream', 5);
        $this->disconnected = true;
    }

    /**
     * Send to socket
     *
     * @param string $msg
     * @param int|null $timeout
     * @return bool|int
     * @throws Exception
     */
    public function send(string $msg, ?int $timeout = null)
    {
        if (is_null($timeout)) {
            $secs = null;
            $usecs = null;
        } elseif ($timeout == 0) {
            $secs = 0;
            $usecs = 0;
        } else {
            $maximum = $timeout * 1000000;
            $usecs = $maximum % 1000000;
            $secs = floor(($maximum - $usecs) / 1000000);
        }

        $read = [];
        $write = [$this->socket];
        $except = [];

        $select = @stream_select($read, $write, $except, $secs, $usecs);

        if ($select === false) {
            $this->log->log("ERROR sending message; reconnecting.");
            $this->doReconnect();
            // TODO: retry send here
            return false;
        } elseif ($select > 0) {
            $this->log->log("Socket is ready; send it.", Log::LEVEL_VERBOSE);
        } else {
            $this->log->log("Socket is not ready; break.", Log::LEVEL_ERROR);
            return false;
        }

        $sentbytes = @fwrite($this->socket, $msg);
        $this->log->log("SENT: " . mb_substr($msg, 0, $sentbytes, '8bit'), Log::LEVEL_VERBOSE);
        if ($sentbytes === false) {
            $this->log->log("ERROR sending message; reconnecting.", Log::LEVEL_ERROR);
            $this->doReconnect();
            return false;
        }
        $this->log->log("Successfully sent $sentbytes bytes.", Log::LEVEL_VERBOSE);
        return $sentbytes;
    }

    /**
     * Reconnect XMPP Host
     * @throws Exception
     */
    public function doReconnect()
    {
        if (!$this->is_server) {
            $this->log->log("Reconnecting ($this->reconnectTimeout)...", Log::LEVEL_WARNING);
            $this->connect(false, false, $this->reconnectTimeout);
            $this->reset();
            $this->event('reconnect');
        }
    }

    /**
     * Connect to XMPP Host
     *
     * @param bool $persistent (optional)
     * @param bool $send_init (optional)
     * @param int $timeout (optional)
     * @throws Exception
     */
    public function connect(bool $persistent = false, bool $send_init = true, int $timeout = 30): void
    {
        $this->sent_disconnect = false;
        $start_time = time();

        do {
            $this->disconnected = false;
            $this->sent_disconnect = false;
            if ($persistent) {
                $conflag = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
            } else {
                $conflag = STREAM_CLIENT_CONNECT;
            }
            $conn_type = 'tcp';
            if ($this->use_ssl) {
                $conn_type = 'ssl';
            }
            $this->log->log("Connecting to $conn_type://{$this->host}:{$this->port}");
            $this->socket = @stream_socket_client("$conn_type://{$this->host}:{$this->port}", $errno, $errstr, $timeout, $conflag);
            if (!$this->socket) {
                $this->log->log("Could not connect.", Log::LEVEL_ERROR);
                $this->disconnected = true;
                # Take it easy for a few seconds
                sleep(min($timeout, 5));
            }
        } while (!$this->socket && (time() - $start_time) < $timeout);

        if ($this->socket) {
            stream_set_blocking($this->socket, 1);
            if ($send_init) {
                $this->send($this->stream_start);
            }
        } else {
            throw new Exception("Could not connect before timeout.");
        }
    }

    /**
     * Reset connection
     * @throws Exception
     */
    public function reset(): void
    {
        $this->xml_depth = 0;
        unset($this->xmlobj);
        $this->xmlobj = [];
        $this->setupParser();
        if (!$this->is_server) {
            $this->send($this->stream_start);
        }
        $this->been_reset = true;
    }

    /**
     * Event?
     *
     * @param string $name
     * @param array|null $payload
     */
    public function event(string $name, ?array $payload = null): void
    {
        $this->log->log("EVENT: {$name}", Log::LEVEL_DEBUG);
        foreach ($this->eventhandlers as $handler) {
            if ($handler[0] === $name) {
                call_user_func_array($handler[1], [&$payload]);
            }
        }
        foreach ($this->until as $key => $until) {
            if (is_array($until)) {
                if (in_array($name, $until)) {
                    $this->until_payload[$key][] = [$name, $payload];
                    if (!isset($this->until_count[$key])) {
                        $this->until_count[$key] = 0;
                    }
                    $this->until_count[$key] += 1;
                    //$this->until[$key] = false;
                }
            }
        }
    }

    /**
     * Process until a specified event or a timeout occurs
     *
     * @param string|array $event
     * @param int $timeout (optional)
     * @return array
     * @throws Exception
     */
    public function processUntil($event, int $timeout = -1): array
    {
        $start = time();
        if (!is_array($event)) {
            $event = array($event);
        }
        $this->until[] = $event;
        end($this->until);
        $event_key = key($this->until);
        reset($this->until);
        $this->until_count[$event_key] = 0;
        while (!$this->disconnected and $this->until_count[$event_key] < 1 and (time() - $start < $timeout or $timeout == -1)) {
            $this->__process();
        }
        if (array_key_exists($event_key, $this->until_payload)) {
            $payload = $this->until_payload[$event_key];
            unset($this->until_payload[$event_key]);
            unset($this->until_count[$event_key]);
            unset($this->until[$event_key]);
        } else {
            $payload = [];
        }
        return $payload;
    }

    /**
     * Core reading tool
     * 0 -> only read if data is immediately ready
     * NULL -> wait forever and ever
     * integer -> process for this amount of time
     * @param int $maximum
     * @return bool
     * @throws Exception
     */
    private function __process(int $maximum = 5): bool
    {
        $remaining = $maximum;

        do {
            $starttime = (microtime(true) * 1000000);
            $read = array($this->socket);
            $write = [];
            $except = [];
            if (is_null($maximum)) {
                $secs = null;
                $usecs = null;
            } elseif ($maximum == 0) {
                $secs = 0;
                $usecs = 0;
            } else {
                $usecs = $remaining % 1000000;
                $secs = floor(($remaining - $usecs) / 1000000);
            }
            $updated = @stream_select($read, $write, $except, $secs, $usecs);
            if ($updated === false) {
                $this->log->log("Error on stream_select()", Log::LEVEL_VERBOSE);
                if ($this->reconnect) {
                    $this->doReconnect();
                } else {
                    fclose($this->socket);
                    $this->socket = null;
                    return false;
                }
            } elseif ($updated > 0) {
                # XXX: Is this big enough?
                $buff = @fread($this->socket, 4096);
                if (!$buff) {
                    if ($this->reconnect) {
                        $this->doReconnect();
                    } else {
                        fclose($this->socket);
                        $this->socket = null;
                        return false;
                    }
                }
                $this->log->log("RECV: $buff", Log::LEVEL_VERBOSE);
                xml_parse($this->parser, $buff, false);
            } // Otherwise,
            // $updated == 0 means no changes during timeout.

            $endtime = (microtime(true) * 1000000);
            $time_past = $endtime - $starttime;
            $remaining = $remaining - $time_past;
        } while (is_null($maximum) || $remaining > 0);
        return true;
    }

    /**
     * Return the log instance
     *
     * @return Log
     */
    public function getLog(): Log
    {
        return $this->log;
    }

    /**
     * Get next ID
     *
     * @return string
     */
    public function getId(): string
    {
        ++$this->lastid;
        return (string) $this->lastid;
    }

    /**
     * Set SSL
     * @param bool $use
     */
    public function useSSL(bool $use = true): void
    {
        $this->use_ssl = $use;
    }

    /**
     * Compose a proper callable if given legacy syntax
     *
     * @param callable|string $pointer
     * @param object|null|bool $obj
     * @return callable
     * @throws InvalidArgumentException
     */
    protected function ensureHandler($pointer, $obj = false): callable
    {
        $handler = $pointer;

        if (is_string($pointer)) {
            if (is_object($obj)) {
                $handler = [$obj, $pointer];
            } elseif (is_null($obj)) {
                // Questionable behaviour for backwards compatibility
                $handler = [$this, $pointer];
            }
        }

        if (!is_callable($handler)) {
            throw new \InvalidArgumentException(
                'Cannot compose a proper callable'
            );
        }
        return $handler;
    }

    /**
     * Add ID Handler
     *
     * @param int $id
     * @param callable|string $pointer
     * @param object|bool|null $obj
     */
    public function addIdHandler(string $id, $pointer, $obj = null): void
    {
        $this->idhandlers[$id] = [$this->ensureHandler($pointer, $obj)];
    }

    /**
     * Add Handler
     *
     * @param string $name
     * @param string $ns
     * @param string $pointer
     * @param object|bool|null $obj
     * @param int $depth
     *
     * public function addHandler(string $name, string $ns, $pointer, $obj = null, int $depth = 1): void
     * {
     *     // TODO deprecation warning
     *     $this->nshandlers[] = [$name, $ns, $this->ensureHandler($pointer, $obj), $depth];
     * }*/

    /**
     * Add XPath Handler
     *
     * @param string $xpath
     * @param callable|string $pointer
     * @param object|bool|null $obj
     */
    public function addXPathHandler(string $xpath, $pointer, $obj = null): void
    {
        if (preg_match_all('/\/?(\{[^\}]+\})?[^\/]+/', $xpath, $regs)) {
            $tag = $regs[0];
        } else {
            $tag = [$xpath];
        }
        $xpath_array = [];
        foreach ($tag as $t) {
            $t = ltrim($t, '/');
            preg_match('/(\{([^\}]+)\})?(.*)/', $t, $regs);
            $xpath_array[] = [$regs[2], $regs[3]];
        }

        $this->xpathhandlers[] = [$xpath_array, $this->ensureHandler($pointer, $obj)];
    }

    /**
     * Add Event Handler
     *
     * @param string $name
     * @param callable|string $pointer
     * @param object|bool|null $obj
     */
    public function addEventHandler(string $name, $pointer, $obj = null): void
    {
        $this->eventhandlers[] = [$name, $this->ensureHandler($pointer, $obj)];
    }

    /**
     * @param int $timeout
     */
    public function setReconnectTimeout(int $timeout): void
    {
        $this->reconnectTimeout = $timeout;
    }

    /**
     * Are we are disconnected?
     *
     * @return bool
     */
    public function isDisconnected(): bool
    {
        return $this->disconnected;
    }

    /**
     * Process
     *
     * @throws Exception
     */
    public function process(): void
    {
        $this->__process(null);
    }

    /**
     * Process until a timeout occurs
     *
     * @param integer $timeout
     * @return string
     * @throws Exception
     */
    public function processTime($timeout = null): string
    {
        if (is_null($timeout)) {
            return $this->__process(null);
        } else {
            return $this->__process($timeout * 1000000);
        }
    }

    /**
     * Obsolete?
     * @param $socket
     *
     * public function Xapply_socket($socket)
     * {
     * $this->socket = $socket;
     * }*/

    /**
     * XML start callback
     *
     * @param resource $parser
     * @param string $name
     * @param array $attr
     * @see xml_set_element_handler
     */
    public function startXML($parser, string $name, array $attr): void
    {
        if ($this->been_reset) {
            $this->been_reset = false;
            $this->xml_depth = 0;
        }
        $this->xml_depth++;
        if (array_key_exists('XMLNS', $attr)) {
            $this->current_ns[$this->xml_depth] = $attr['XMLNS'];
        } else {
            $this->current_ns[$this->xml_depth] = $this->current_ns[$this->xml_depth - 1];
            if (!$this->current_ns[$this->xml_depth]) {
                $this->current_ns[$this->xml_depth] = $this->default_ns;
            }
        }
        $ns = $this->current_ns[$this->xml_depth];
        foreach ($attr as $key => $value) {
            if (strstr($key, ":")) {
                $key = explode(':', $key);
                $key = $key[1];
                $this->ns_map[$key] = $value;
            }
        }
        if (!strstr($name, ":") === false) {
            $name = explode(':', $name);
            $ns = $this->ns_map[$name[0]];
            $name = $name[1];
        }
        $obj = new XMLObj($name, $ns, $attr);
        if ($this->xml_depth > 1) {
            $this->xmlobj[$this->xml_depth - 1]->subs[] = $obj;
        }
        $this->xmlobj[$this->xml_depth] = $obj;
    }

    /**
     * XML end callback
     *
     * @param resource $parser
     * @param string $name
     * @throws Exception
     * @see xml_set_element_handler
     *
     */
    public function endXML($parser, string $name): void
    {
        #$this->log->log("Ending $name",  Log::LEVEL_DEBUG);
        #print "$name\n";
        if ($this->been_reset) {
            $this->been_reset = false;
            $this->xml_depth = 0;
        }
        $this->xml_depth--;
        if ($this->xml_depth == 1) {
            #clean-up old objects
            #$found = false; #FIXME This didn't appear to be in use --Gar
            $searchxml = null;
            foreach ($this->xpathhandlers as $handler) {
                if (is_array($this->xmlobj) && array_key_exists(2, $this->xmlobj)) {
                    $searchxml = $this->xmlobj[2];
                    $nstag = array_shift($handler[0]);
                    if (($nstag[0] == null or $searchxml->ns == $nstag[0]) and ($nstag[1] == "*" or $nstag[1] == $searchxml->name)) {
                        foreach ($handler[0] as $nstag) {
                            if ($searchxml !== null and $searchxml->hasSub($nstag[1], $ns = $nstag[0])) {
                                $searchxml = $searchxml->sub($nstag[1], $ns = $nstag[0]);
                            } else {
                                $searchxml = null;
                                break;
                            }
                        }
                        if (!is_null($searchxml)) {
                            call_user_func_array($handler[1], [&$this->xmlobj[2]]);
                        }
                    }
                }
            }
            foreach ($this->nshandlers as $handler) {
                if ($handler[4] != 1 and array_key_exists(2, $this->xmlobj) and $this->xmlobj[2]->hasSub($handler[0])) {
                    $searchxml = $this->xmlobj[2]->sub($handler[0]);
                } elseif (is_array($this->xmlobj) and array_key_exists(2, $this->xmlobj)) {
                    $searchxml = $this->xmlobj[2];
                }
                if (
                    !is_null($searchxml)
                    && $searchxml->name === $handler[0]
                    && (
                        (!$handler[1] && $searchxml->ns === $this->default_ns)
                        || $searchxml->ns === $handler[1]
                    )
                ) {
                    call_user_func_array($handler[2], [&$this->xmlobj[2]]);
                }
            }
            foreach ($this->idhandlers as $id => $handler) {
                if (
                    array_key_exists(2, $this->xmlobj)
                    && array_key_exists('id', $this->xmlobj[2]->attrs)
                    && $this->xmlobj[2]->attrs['id'] === (string) $id
                ) {
                    call_user_func_array($handler[0], [&$this->xmlobj[2]]);
                    // id handlers are only used once
                    unset($this->idhandlers[$id]);
                    break;
                }
            }
            if (is_array($this->xmlobj)) {
                $this->xmlobj = array_slice($this->xmlobj, 0, 1);
                if (isset($this->xmlobj[0]) && $this->xmlobj[0] instanceof XMLObj) {
                    $this->xmlobj[0]->subs = null;
                }
            }
            unset($this->xmlobj[2]);
        }
        if ($this->xml_depth == 0 and !$this->been_reset) {
            if (!$this->disconnected) {
                if (!$this->sent_disconnect) {
                    $this->send($this->stream_end);
                }
                $this->disconnected = true;
                $this->sent_disconnect = true;
                fclose($this->socket);
                if ($this->reconnect) {
                    $this->doReconnect();
                }
            }
            $this->event('end_stream');
        }
    }

    /**
     * XML character callback
     * @param resource $parser
     * @param string $data
     * @see xml_set_character_data_handler
     *
     */
    public function charXML($parser, string $data): void
    {
        if (array_key_exists($this->xml_depth, $this->xmlobj)) {
            $this->xmlobj[$this->xml_depth]->data .= $data;
        }
    }

    /**
     * Read from socket
     * @return bool Did read
     * @throws Exception
     */
    public function read(): bool
    {
        $buff = @fread($this->socket, 1024);
        if (!$buff) {
            if ($this->reconnect) {
                $this->doReconnect();
            } else {
                fclose($this->socket);
                return false;
            }
        }
        $this->log->log("RECV: $buff", Log::LEVEL_VERBOSE);
        xml_parse($this->parser, $buff, false);
        return true;
    }

    public function time(): float
    {
        list($usec, $sec) = explode(" ", microtime());
        return (float)$sec + (float)$usec;
    }

    public function readyToProcess(): bool
    {
        $read = array($this->socket);
        $write = [];
        $except = [];
        $updated = @stream_select($read, $write, $except, 0);
        return (($updated !== false) && ($updated > 0));
    }
}
