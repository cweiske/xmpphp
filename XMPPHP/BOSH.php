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
 * @package	XMPPHP
 * @author	 Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author	 Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author	 Michael Garvin <JID: gar@netflint.net>
 * @copyright  2008 Nathanael C. Fritz
 */

/** XMPPHP_XMLStream */
require_once dirname(__FILE__) . "/XMPP.php";

/**
 * XMPPHP Main Class
 * 
 * @category   xmpphp 
 * @package	XMPPHP
 * @author	 Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author	 Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author	 Michael Garvin <JID: gar@netflint.net>
 * @copyright  2008 Nathanael C. Fritz
 * @version	$Id$
 */
class XMPPHP_BOSH extends XMPPHP_XMPP {

		protected $rid;
		protected $sid;
		protected $http_server;
		protected $http_buffer = Array();
		protected $session = false;
		protected $inactivity;

		public function connect($server=NULL, $wait='1', $session=false) {
			if (is_null( $server )) {
				//if we aren't given the server http url, try and guess it
				$port_string = ( $this->port && $this->port != 80 ) ? ":".$this->port : "";
				$this->http_server = "http://".$this->host."$port_string/http-bind/";
			} else {
				$this->http_server = $server;
			}

			$this->use_encryption = false;
			$this->session = $session;

			$this->rid = 3001;
			$this->sid = null;
			$this->inactivity=0;
			
			if($session)
			{		
				$this->loadSession();
			}

			if(!$this->sid) {
				$body = $this->__buildBody();
				$body->addAttribute('hold','1');
				$body->addAttribute('to', $this->server);
				$body->addAttribute('route', "xmpp:{$this->host}:{$this->port}");
				$body->addAttribute('secure','true');
				$body->addAttribute('xmpp:version','1.0', 'urn:xmpp:xbosh');
				$body->addAttribute('wait', strval($wait));
				$body->addAttribute('ack','1');
				$body->addAttribute('xmlns:xmpp','urn:xmpp:xbosh');
				$buff = "<stream:stream xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams'>";
				xml_parse($this->parser, $buff, false);
				$response = $this->__sendBody($body);
				$rxml = new SimpleXMLElement($response);
				$this->sid = $rxml['sid'];
				$this->inactivity = $rxml['inactivity'];
			} else {
				$buff = "<stream:stream xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams'>";
				xml_parse($this->parser, $buff, false);
			}
		}

		public function __sendBody($body=null, $recv=true) {
			if(!$body) {
				$body = $this->__buildBody();
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$this->http_server);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body->asXML());
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$header = array('Accept-Encoding: gzip, deflate','Content-Type: text/xml; charset=utf-8');
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header );
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			$output = '';

			if($recv) {
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$output = curl_exec($ch);
				if(curl_getinfo($ch,CURLINFO_HTTP_CODE)!="200") throw new XMPPHP_Exception("Wrong response from server!");
				$this->http_buffer[] = $output;
			}

			curl_close($ch);
			return $output;
		}

		public function __buildBody($sub=null) {
			$xml = new SimpleXMLElement("<body xmlns='http://jabber.org/protocol/httpbind' xmlns:xmpp='urn:xmpp:xbosh' />");
			$xml->addAttribute('content', 'text/xml; charset=utf-8');
			$xml->addAttribute('rid', $this->rid);
			$this->rid += 1;
			if($this->sid) $xml->addAttribute('sid', $this->sid);
			#if($this->sid) $xml->addAttribute('xmlns', 'http://jabber.org/protocol/httpbind');
			$xml->addAttribute('xml:lang', 'en');
			if($sub !== NULL) { // ok, so simplexml is lame
				$p = dom_import_simplexml($xml);
				$c = dom_import_simplexml($sub);
				$cn = $p->ownerDocument->importNode($c, true);
				$p->appendChild($cn);
				$xml = simplexml_import_dom($p);
			}
			return $xml;
		}

		//null params are not used and just to statify Strict Function Declaration
		public function __process($null1=NULL,$null2=NULL) {
			if($this->http_buffer) {
				$this->__parseBuffer();
			} else {
				$this->__sendBody();
				$this->__parseBuffer();
			}

			$this->saveSession();
			return true;
		}

		public function __parseBuffer() {
			while ($this->http_buffer) {
				$idx = key($this->http_buffer);
				$buffer = $this->http_buffer[$idx];
				unset($this->http_buffer[$idx]);
				if($buffer) {
					$xml = new SimpleXMLElement($buffer);
					$children = $xml->xpath('child::node()');
					foreach ($children as $child) {
						$buff = $child->asXML();
						$this->log->log("RECV: $buff",  XMPPHP_Log::LEVEL_VERBOSE);
						xml_parse($this->parser, $buff, false);
					}
				}
			}
		}

		//null params are not used and just to statify Strict Function Declaration
		public function send($msg,$null1=NULL) {
			$this->log->log("SEND: $msg",  XMPPHP_Log::LEVEL_VERBOSE);
			$msg = new SimpleXMLElement($msg);
			#$msg->addAttribute('xmlns', 'jabber:client');
			$this->__sendBody($this->__buildBody($msg), true);
			#$this->__parseBuffer();
		}

		public function reset() {
			$this->xml_depth = 0;
			unset($this->xmlobj);
			$this->xmlobj = array();
			$this->setupParser();
			#$this->send($this->stream_start);
			$body = $this->__buildBody();
			$body->addAttribute('to', $this->host);
			$body->addAttribute('xmpp:restart', 'true', 'urn:xmpp:xbosh');
			$buff = "<stream:stream xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams'>";
			$response = $this->__sendBody($body);
			$this->been_reset = true;
			xml_parse($this->parser, $buff, false);
		}

		public function loadSession() {
			if($this->session=='ON_FILE'){
				// session not started so use session_file
				$session_file = sys_get_temp_dir()."/".$this->user."_".$this->server."_session";

				// manage multiple accesses				
				if(!file_exists($session_file)) file_put_contents($session_file,"");
				$session_file_fp = fopen($session_file,"r"); flock($session_file_fp,LOCK_EX);
				$session_serialized = file_get_contents($session_file, NULL, NULL, 6); 
				flock($session_file_fp,LOCK_UN); fclose($session_file_fp);

				$this->log->log("SESSION: reading $session_serialized from $session_file",  XMPPHP_Log::LEVEL_VERBOSE);
				if($session_serialized!="")
					$_SESSION['XMPPHP_BOSH'] = unserialize($session_serialized);
			}
			
			if(isset($_SESSION['XMPPHP_BOSH']['inactivity'])) $this->inactivity = $_SESSION['XMPPHP_BOSH']['inactivity'];
			$this->lat = time() - (isset($_SESSION['XMPPHP_BOSH']['lat'])? $_SESSION['XMPPHP_BOSH']['lat'] : 0);			
			if($this->lat<$this->inactivity){
				if(isset($_SESSION['XMPPHP_BOSH']['RID'])) $this->rid = $_SESSION['XMPPHP_BOSH']['RID'];
				if(isset($_SESSION['XMPPHP_BOSH']['SID'])) $this->sid = $_SESSION['XMPPHP_BOSH']['SID'];
				if(isset($_SESSION['XMPPHP_BOSH']['authed'])) $this->authed = $_SESSION['XMPPHP_BOSH']['authed'];
				if(isset($_SESSION['XMPPHP_BOSH']['basejid'])) $this->basejid = $_SESSION['XMPPHP_BOSH']['basejid'];
				if(isset($_SESSION['XMPPHP_BOSH']['fulljid'])) $this->fulljid = $_SESSION['XMPPHP_BOSH']['fulljid'];
			}
		}

		public function saveSession() {
			$_SESSION['XMPPHP_BOSH']['RID'] = (string) $this->rid;
			$_SESSION['XMPPHP_BOSH']['SID'] = (string) $this->sid;
			$_SESSION['XMPPHP_BOSH']['authed'] = (boolean) $this->authed;
			$_SESSION['XMPPHP_BOSH']['basejid'] = (string) $this->basejid;
			$_SESSION['XMPPHP_BOSH']['fulljid'] = (string) $this->fulljid;
			$_SESSION['XMPPHP_BOSH']['inactivity'] = (string) $this->inactivity;			
			$_SESSION['XMPPHP_BOSH']['lat'] = (string) time();		
			
			if($this->session=='ON_FILE'){
				$session_file = sys_get_temp_dir()."/".$this->user."_".$this->server."_session";
				$session_file_fp = fopen($session_file,"r"); flock($session_file_fp,LOCK_EX);
				// <?php prefix used to mask the content of the session file
				$session_serialized = "<?php ".serialize($_SESSION);
				file_put_contents($session_file,$session_serialized);
				flock($session_file_fp,LOCK_UN); fclose($session_file_fp);
			}
		}
		
		public function disconnect(){
			parent::disconnect();

			if($this->session=='ON_FILE')
				unlink(sys_get_temp_dir()."/".$this->user."_".$this->server."_session");
			else{
				unset($_SESSION['XMPPHP_BOSH']['RID']);
				unset($_SESSION['XMPPHP_BOSH']['SID']);
				unset($_SESSION['XMPPHP_BOSH']['authed']);
				unset($_SESSION['XMPPHP_BOSH']['basejid']);
				unset($_SESSION['XMPPHP_BOSH']['fulljid']);
				unset($_SESSION['XMPPHP_BOSH']['inactivity']);
				unset($_SESSION['XMPPHP_BOSH']['lat']);
			}
		}
}
