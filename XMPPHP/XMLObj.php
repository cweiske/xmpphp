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
 * XMPPHP XMLObject
 *
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 * @version   $Id$
 */
class XMLObj
{
    /**
     * Tag name
     *
     * @var string
     */
    public $name;

    /**
     * Namespace
     *
     * @var string
     */
    public $ns;

    /**
     * Attributes
     *
     * @var array
     */
    public $attrs = [];

    /**
     * Subs?
     *
     * @var array
     */
    public $subs = [];

    /**
     * Node data
     *
     * @var string
     */
    public $data = '';

    /**
     * Constructor
     *
     * @param string $name
     * @param string $ns (optional)
     * @param array $attrs (optional)
     * @param string $data (optional)
     */
    public function __construct(string $name, string $ns = '', array $attrs = [], string $data = '')
    {
        $this->name = strtolower($name);
        $this->ns = $ns;
        if (is_array($attrs) && count($attrs)) {
            foreach ($attrs as $key => $value) {
                $this->attrs[strtolower($key)] = $value;
            }
        }
        $this->data = $data;
    }

    /**
     * Dump this XML Object to output.
     *
     * @param int $depth (optional)
     */
    public function printObj(int $depth = 0): void
    {
        print str_repeat("\t", $depth) . $this->name . " " . $this->ns . ' ' . $this->data;
        print "\n";
        foreach ($this->subs as $sub) {
            $sub->printObj($depth + 1);
        }
    }

    /**
     * Return this XML Object in xml notation
     *
     * @param string $str (optional)
     * @return string
     */
    public function toString(string $str = ''): string
    {
        $str .= "<{$this->name} xmlns='{$this->ns}' ";
        foreach ($this->attrs as $key => $value) {
            if ($key != 'xmlns') {
                $value = htmlspecialchars($value);
                $str .= "$key='$value' ";
            }
        }
        $str .= ">";
        foreach ($this->subs as $sub) {
            $str .= $sub->toString();
        }
        $body = htmlspecialchars($this->data);
        $str .= "$body</{$this->name}>";
        return $str;
    }

    /**
     * Has this XML Object the given sub?
     *
     * @param string $name
     * @param string|null $ns
     * @return bool
     */
    public function hasSub(string $name, ?string $ns = null): bool
    {
        foreach ($this->subs as $sub) {
            if (($name == "*" or $sub->name == $name) and ($ns == null or $sub->ns == $ns)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return a sub
     *
     * @param string $name
     * @param array|null $attrs (optional)
     * @param string|null $ns (optional)
     * @return mixed
     */
    public function sub(string $name, ?array $attrs = null, ?string $ns = null)
    {
        #TODO attrs is ignored
        foreach ($this->subs as $sub) {
            if ($sub->name == $name and ($ns == null or $sub->ns == $ns)) {
                return $sub;
            }
        }
        return null;
    }
}
