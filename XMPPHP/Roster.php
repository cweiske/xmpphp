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
 * XMPPHP Roster
 *
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 * @version   $Id$
 */
class Roster
{
    /**
     * Roster array, handles contacts and presence.  Indexed by jid.
     * Contains array with potentially two indexes 'contact' and 'presence'
     * @var array
     */
    protected $roster_array = [];

    /**
     * Constructor
     * @param array $roster_array
     */
    public function __construct($roster_array = [])
    {
        if ($this->verifyRoster($roster_array)) {
            $this->roster_array = $roster_array; //Allow for pre-population with existing roster
        } else {
            $this->roster_array = [];
        }
    }

    /**
     * Check that a given roster array is of a valid structure (empty is still valid)
     *
     * @param array $roster_array
     * @return bool true for valid, false otherwise
     */
    protected function verifyRoster(array $roster_array): bool
    {
        #TODO once we know *what* a valid roster array looks like
        return true;
    }

    /**
     * Retrieve contact via jid
     *
     * @param string $jid
     * @return mixed|void
     */
    public function getContact(string $jid)
    {
        if ($this->isContact($jid)) {
            return $this->roster_array[$jid]['contact'];
        }
        return;
    }

    /**
     * Discover if a contact exists in the roster via jid
     *
     * @param string $jid
     * @return bool
     */
    public function isContact(string $jid): bool
    {
        return (array_key_exists($jid, $this->roster_array));
    }

    /**
     * Set presence
     *
     * @param string $presence
     * @param int $priority
     * @param string $show
     * @param string $status
     */
    public function setPresence(string $presence, int $priority, string $show, string $status): void
    {
        $presence = explode('/', $presence, 2);
        $jid = $presence[0];
        $resource = isset($presence[1]) ? $presence[1] : '';
        if ($show != 'unavailable') {
            if (!$this->isContact($jid)) {
                $this->addContact($jid, 'not-in-roster');
            }
            $this->roster_array[$jid]['presence'][$resource] = ['priority' => $priority, 'show' => $show, 'status' => $status];
        } else { //Nuke unavailable resources to save memory
            unset($this->roster_array[$jid]['resource'][$resource]);
            unset($this->roster_array[$jid]['presence'][$resource]);
        }
    }

    /**
     * Add given contact to roster
     *
     * @param string $jid
     * @param string $subscription
     * @param string $name (optional)
     * @param array $groups (optional)
     */
    public function addContact(string $jid, string $subscription, string $name = '', array $groups = []): void
    {
        $contact = ['jid' => $jid, 'subscription' => $subscription, 'name' => $name, 'groups' => $groups];
        if ($this->isContact($jid)) {
            $this->roster_array[$jid]['contact'] = $contact;
        } else {
            $this->roster_array[$jid] = ['contact' => $contact];
        }
    }

    /**
     * Get presence
     *
     * @param string $jid
     * @return array best presence for jid
     */
    public function getPresence(string $jid): array
    {
        $split = explode('/', $jid, 2);
        $jid = $split[0];
        if ($this->isContact($jid)) {
            $current = [
                'resource' => '',
                'active' => '',
                'priority' => -129, //Priorities can only be -128 = 127
                'show' => '',
                'status' => ''
            ];
            foreach ($this->roster_array[$jid]['presence'] as $resource => $presence) {
                //Highest available priority or just highest priority
                if ($presence['priority'] > $current['priority'] and
                    (
                        ($presence['show'] == "chat" or $presence['show'] == "available") or
                        ($current['show'] != "chat" or $current['show'] != "available")
                    )
                ) {
                    $current = $presence;
                    $current['resource'] = $resource;
                }
            }
            return $current;
        }
        return [];
    }

    /**
     * Get roster
     *
     * @return array roster_array
     */
    public function getRoster(): array
    {
        return $this->roster_array;
    }
}
