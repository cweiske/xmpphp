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

/**
 * XMPPHP Log
 *
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 * @version   $Id$
 */
class Log
{
    const LEVEL_ERROR = 0;
    const LEVEL_WARNING = 1;
    const LEVEL_INFO = 2;
    const LEVEL_DEBUG = 3;
    const LEVEL_VERBOSE = 4;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $names = ['ERROR', 'WARNING', 'INFO', 'DEBUG', 'VERBOSE'];

    /**
     * @var int
     */
    protected $runlevel;

    /**
     * @var bool
     */
    protected $printout;

    /**
     * Constructor
     *
     * @param boolean $printout
     * @param int $runlevel (optional)
     */
    public function __construct($printout = false, ?int $runlevel = self::LEVEL_INFO)
    {
        $this->printout = (bool) $printout;
        $this->runlevel = (int) ($runlevel ?? 0);
    }

    /**
     * Add a message to the log data array
     * If printout in this instance is set to true, directly output the message
     *
     * @param string $msg
     * @param int $runlevel (optional)
     */
    public function log($msg, $runlevel = self::LEVEL_INFO): void
    {
        $time = time();
        //$this->data[] = [$this->runlevel, $msg, $time];
        if ($this->printout and $runlevel <= $this->runlevel) {
            $this->writeLine($msg, $runlevel, $time);
        }
    }

    /**
     * @param string $msg
     * @param int $runlevel
     * @param int $time
     */
    protected function writeLine(string $msg, int $runlevel, int $time): void
    {
        //echo date('Y-m-d H:i:s', $time)." [".$this->names[$runlevel]."]: ".$msg."\n";
        echo $time . " [" . $this->names[$runlevel] . "]: " . $msg . "\n";
        flush();
    }

    /**
     * Output the complete log.
     * Log will be cleared if $clear = true
     *
     * @param bool $clear
     * @param int $runlevel
     */
    public function printout(bool $clear = true, int $runlevel = null): void
    {
        if ($runlevel === null) {
            $runlevel = $this->runlevel;
        }
        foreach ($this->data as $data) {
            if ($runlevel <= $data[0]) {
                $this->writeLine($data[1], $runlevel, $data[2]);
            }
        }
        if ($clear) {
            $this->data = [];
        }
    }
}
