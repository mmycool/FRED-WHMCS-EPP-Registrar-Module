<?php

class myfredEPP {

	/**
	* Variable used for internal storage of objects and data
	* @var array
	*/
	private $vars;


	// Proctect the magic clone function!
	// This to make it impossible to clone the class and use $vars.
	protected function __clone() {
		/* Placeholder */
	}

	/**
	* Constuctor for the EPP object.
	* Automaticly connects to the specified EPP server
	*
	* And creates the DOMDocument object to be used later on
	* Also adds the default <epp/> tree and sets default attributes for the root element.
	*
	* @return void
	*/
	public function __construct($connect = true) {

		// Connects to the EPP server if $connect == true.
		if($connect && !$this->socket) {
			$this->Connect(EPP_HOST, EPP_PORT, EPP_TIMEOUT);
		}

		// Initialize the DOM-tree
		$this->document = new DOMDocument('1.0', 'UTF-8');

		// Set DOM modes and output format
		$this->document->standalone = false;
		$this->document->formatOutput = FORMAT_OUTPUT;

		// Create $this->epp and fill it with default attributes
		$this->epp = $this->document->appendChild($this->document->createElement('epp'));
		$this->epp->appendChild($this->setAttribute('xmlns', XMLNS_EPP));
		$this->epp->appendChild($this->setAttribute('xmlns:xsi', XMLNS_XSCHEMA));
		$this->epp->appendChild($this->setAttribute('xsi:schemaLocation', XSCHEMA_EPP));

		// Append <epp/> to the document
		$this->document->appendChild($this->epp);
	}

	/**
	* This method establishes the connection to the server. If the connection was
	* established, then this method will call getFrame() and return the EPP <greeting>
	* frame which is sent by the server upon connection.
	*
	* @param string the hostname and protocol (tls://example.com)
	* @param integer the TCP port
	* @param integer the timeout in seconds
	* @return on success a string containing the server <greeting>
	*/
	function Connect($host, $port = 700, $timeout = 60) {

		$context = stream_context_create(array(
			'ssl'=>array(			
			'local_cert' => EPP_CERT,
			'verify_peer' => false,
             'verify_peer_name' => false,		            								
			)
		));

		if (!$this->socket = stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context)) {
			die("Failed to connect:" . $errstr);
			
		} else {
			stream_set_timeout($this->socket, $timeout);
			return $this->getFrame();
		}

	}

	/**
	* Get an EPP frame from the server.
	* This retrieves a frame from the server. Since the connection is blocking, this
	* method will wait until one becomes available. 
	* containing the XML from the server
	* @return on success a string containing the frame
	*/
	function getFrame() {
		if (@feof($this->socket)) die('Couldn\'t get frame - closed by remote server');

		// Read the 4 first bytes (reply length)
		$hdr = fread($this->socket, 4);

		if (empty($hdr) && feof($this->socket)) {

			die('Couldn\'t get HDR - connection closed by remote server');

		} elseif (empty($hdr)) {

			die('Error reading from server - connection closed.');

		} else {

			$unpacked = unpack('N', $hdr);
			$length = $unpacked[1];
			if ($length < 5) {
				die(sprintf('Got a bad frame header length of %d bytes from server', $length));
			} else {
				// Read everything except the 4 bytes we read before.
				$xml = fread($this->socket, ($length - 4));
				$this->latest = $xml; // Store the latest XML
				$this->_logframe($xml); // And then log the frame.
                $dom = new DOMDocument;
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($xml);

                file_put_contents('/home/mabula/public_html/jaribu/fred/mw/com_output.xml', $dom->saveXML()
, FILE_APPEND);
                //return 
                
                return $xml;
			}

		}
	}

	/**
	* Send the current XML frame to the server.
	* @return boolean the result of the fwrite() operation
	*/
	function sendFrame() {
		if($this->socket) {
			$xml = $this->getXML(); // Get the current XML frame
			$this->_logframe($xml); // Log the frame if enabled.
            file_put_contents('/home/mabula/public_html/jaribu/fred/mw/command.xml', $xml, FILE_APPEND);
			return fwrite($this->socket, pack('N', (strlen($xml)+4)).$xml);
		}

		return false;
	}

	/**
	* a wrapper around sendFrame() and getFrame()
	* @return string the frame returned by the server
	*/
	function Process() {
		if($this->sendFrame()) {
            //$xml=$this->getFrame();
            //$cools=simplexml_load_string($xml);
			return $this->getFrame();
           // return $this->xml_array($xml);
            
		}

		return false;
	}
	
	/**
		* a wrapper around sendFrame() and getFrame()
		* @param string $xml the frame to send to the server
		* @throws Exception when it doesn't complete the write to the socket
		* @return string the frame returned by the server, or an error object
		*/
		function request($xml) {
			$res = $this->sendFrame1($xml);
			return $this->getFrame();
		}
		
		/**
	* Send the current XML frame to the server.
	* @return boolean the result of the fwrite() operation
	*/
	function sendFrame1($xml) {
		if($this->socket) {
			//$xml = simplexml_load_string($xml);
			//$xml = $this->getXML(); // Get the current XML frame
			//$this->_logframe($xml); // Log the frame if enabled.
            file_put_contents('/home/mabula/public_html/jaribu/fred/mw/command.xml', $xml, FILE_APPEND);
			return fwrite($this->socket, pack('N', (strlen($xml)+4)).$xml);
		}

		return false;
	}


	/**
	* Creates EPP request for <login/>
	* Uses EPP_USER and EPP_PWD constants for login
	*
	* @return void
	*/
	public function Login() {

		// As this is a command, add the element.
		$this->_command();

		$login = $this->command->appendChild($this->document->createElement('login'));
		$login->appendChild($this->document->createElement('clID', EPP_USER));
		$login->appendChild($this->document->createElement('pw', EPP_PWD));

		$options = $login->appendChild($this->document->createElement('options'));
		$options->appendChild($this->document->createElement('version', '1.0'));
		$options->appendChild($this->document->createElement('lang', 'en'));

		$svcs = $login->appendChild($this->document->createElement('svcs'));
		$svcs->appendChild($this->document->createElement('objURI', XSCHEMA_DOMAIN));
		$svcs->appendChild($this->document->createElement('objURI', XSCHEMA_CONTACT));
		//$svcs->appendChild($this->document->createElement('objURI', XSCHEMA_HOST));

		//$svcx = $svcs->appendChild($this->document->createElement('svcExtension'));
		//$svcx->appendChild($this->document->createElement('extURI', XSCHEMA_EXTDNSSEC));
		//$svcx->appendChild($this->document->createElement('extURI', XSCHEMA_EXTIIS));

		// Add transactionId to this frame
		$this->_transaction();
        
	}


	/**
	* Creates EPP request for <logout/>
	*
	* @return void
	*/
	public function Logout() {
		// As this is a command, add the element.
		$this->_command();

		$this->command->appendChild($this->document->createElement('logout'));

		// Add transactionId to this frame
		$this->_transaction();
	}


	/**
	* Creates EPP request for <poll/>
	*
	* @param string $op req|ack
	* @param int $msgID Message id to ack
	* @return void
	*/
	public function Poll($op = 'req', $msgID = null) {
		// As this is a command, add the element.
		$this->_command();

		$poll = $this->command->appendChild($this->document->createElement('poll'));
		$poll->appendChild($this->setAttribute('op', $op));

		if($msgID) {
			$poll->appendChild($this->setAttribute('msgID', $msgID));
		}

		// Add transactionId to this frame
		$this->_transaction();
	}

	

	/**
	* Basic <hello/> over EPP
	* 
	* @return void
	*/
	public function Hello() {
		$this->epp->appendChild($this->document->createElement('hello'));
	}

	/**
	* GetXML function for getting generated XML
	* When requested, the _clean functions is runned to reset the XML.
	* But only if $clean == true.
	*
	* @param boolean should we clean the XML-DOM after returned data?
	* @return void
	*/
	public function getXML($clean = true) {
		$xml = $this->document->saveXML();
		
		if($clean) {
			$this->_clean();
		}
		
		return $xml;
	}
	

	/**
	* SetXML function for setting custom/test XML
	* When requested, the _clean functions is runned to restart EPP.
	* And then it sets the current XML to the input var.
	*
	* @param string $xml XML.
	* @return void
	*/
	public function setXML($xml) {
	
		// Initialize the DOM-tree
		$this->document = new DOMDocument();
		$this->document->loadXML($xml);
		
		return $this->document->saveXML();
	}
	
	

	public function XPath($xml = null) {
		$dom = new DOMDocument;

		if(empty($xml)) {
			// If $xml is null, use the latest frame. 
			$xml = $this->latest;

			// If $xml still is empty, return false.
			if(empty($xml)) return false;
		}

		if (@$dom->loadXML($xml) === false) {
			die("XML parse error, couldn't loadXML() in ".__FILE__);
		}

		$xpath = new DOMXPath($dom);

		/**
		* Register all of namespaces.
		*/
		$xpath->registerNamespace( 'epp', XMLNS_EPP );
		//$xpath->registerNamespace( 'con', XSCHEMA_CONTACT );
		//$xpath->registerNamespace( 'dom', XSCHEMA_DOMAIN );
		//$xpath->registerNamespace( 'hos', XSCHEMA_HOST );
		//$xpath->registerNamespace( 'iis', XSCHEMA_EXTIIS );
		//$xpath->registerNamespace( 'iis', XSCHEMA_EXTIISO );
		//$xpath->registerNamespace( 'secDNS', XSCHEMA_EXTDNSSEC );

		return $xpath;

	}

	public function getResultCode() {
		// Use the latest frame for XPath.
		$xpath = $this->XPath();

		return $xpath->query('/epp:epp/epp:response/epp:result/@code')->item(0)->nodeValue;
	}

	public function getResultMsg() {
		// Use the latest frame for XPath.
		$xpath = $this->XPath();

		return $xpath->query('/epp:epp/epp:response/epp:result/epp:msg/text()')->item(0)->nodeValue;
	}
   
    
   
  
   
	/**
	* Close the connection.
	* This method closes the connection to the server. Note that the
	* EPP specification indicates that clients should send a <logout>
	* command before ending the session.
	* @return boolean true | the result of the fclose() operation
	*/
	function Disconnect() {
		if($this->socket) {
			return @fclose($this->socket);
		}

		return true;
	}

	/********************************************
	* Private section starts here.
	* Functions below are set to private
	* And are used internally in this class.
	*********************************************/

	/**
	* Adds a <command/> element to <epp/> inside of the document.
	* Used in all functions thats generates commands.
	*
	* @return void
	*/
	private function _command() {
		// Create <command/>
		$this->command = $this->epp->appendChild($this->document->createElement('command'));
	}


	/**
	* Re-initialize the class, using __construct.
	* 
	* @return void
	*/
	private function _clean() {
		$this->__construct();
	}

	/**
	* Adds a <clTRID/> element the document.
	* Used in all functions thats generates commands.
	* Required by the RFC.
	*
	* @return void
	*/
	private function _transaction() {

		// Fix for making microtime floats more accurate.
		ini_set('precision', 16);

		// Add transactionid's to all generated EPP frames with commands
		$tranId = "afriEPP-" . microtime(1) . "-" . getmypid();
		$this->command->appendChild($this->document->createElement('clTRID', $tranId));
	}


	/**
	* Private function used for binary logging of all frames.
	* Writes ALL XML sent to it, to EPP_LOG_FILE as gzip:ed data.
	*
	* @param string $xml XML-frame in fully
	* @return boolean
	*/
	private function _logframe($xml) {
		if(constant('EPP_LOG') !== false && constant('EPP_LOG_FILE')) {
			return file_put_contents(EPP_LOG_FILE, gzcompress($xml) . pack('L', 0xFEE1DEAD), FILE_APPEND);
            return file_put_contents(EPP_LOG_FILE,$xml, FILE_APPEND);
		}

		return false;
	}

	private function setAttribute($name, $value) {
		$attribute = $this->document->createAttribute($name);
		$attribute->nodeValue = $value;
		return $attribute;
	}

	private function __set($var, $value) {
		$this->vars[$var] = $value;
	}

	private function __get($var) {
		if(isset($this->vars[$var])) {
			return $this->vars[$var];
		}
	}
	
	private function __isset($var) { 
		return (bool)$this->vars[$var]; 
	}

	private function __unset($var) { 
		unset($this->vars[$var]);
	}

	private function __toString() {
		return $this->getXML();
	}
   
  

	/**
	* phpEPP Destructor, just to do some garbage cleaning.
	* Removes $vars and closes EPP connection. 
	*/
	public function __destruct() {
		$this->Disconnect();
		unset($this->vars);
	}
	
	public function clTRID() {
		$clTRID = md5(uniqid(rand(), true) . date("U"));
		return $clTRID;
	}


	public function eppKey() {
		$chars = "a0Zb1Yc2Xd3We4Vf5Ug6Th7Si8Rj9Qk8Pl7Om6Nn5Mo4Lp3Kq2Jr1Is0Ht1Gu2Fv3Ew4Dx5Cy6Bz7A";
		$max = strlen($chars) - 1;
		$eppKey = null;
		$i = 0;
		while ($i < 10) {
			$eppKey .= $chars[mt_rand(0, $max)];
			++$i;
		}
		return $eppKey;
	}


	public function eppContactId($size = 16, $prefix = "", $suffix = "", $case = 1) {
		$prefix_size = count(count_chars(trim($prefix), 1));
		$suffix_size = count(count_chars(trim($suffix), 1));
		$added_chars = $size - 13 - $prefix_size - $suffix_size;
		if ($added_chars < 0) {
			$added_chars = 0;
		}
		$id = trim($prefix) . uniqid(substr(md5(rand(0, 99999)), rand(0, 29), $added_chars)) . trim($suffix);
		if ($case) {
			$id = strtoupper($id);
		}
		return $id;
	}


	public function eppHello() {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <hello/>\r\n</epp>";
		return $command;
	}

public function creditInfo() {
		$command = "<?xml version=\"1.0\" encoding=\"utf-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n<extension>\r\n<fred:extcommand xmlns:fred=\"http://www.nic.cz/xml/epp/fred-1.5\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/fred-1.5 fred-1.5.xsd\">\r\n<fred:creditInfo/>\r\n<fred:clTRID>" . $this->clTRID() . "</fred:clTRID>\r\n</fred:extcommand>\r\n</extension>\r\n</epp>";
		return $command;
	}
	

	public function eppLogin($username, $password) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <login>\r\n      <clID>" . $username . "</clID>\r\n      <pw>" . $password . "</pw>\r\n      <options>\r\n        <version>1.0</version>\r\n        <lang>en</lang>\r\n      </options>\r\n      <svcs>\r\n        <objURI>http://www.nic.cz/xml/epp/contact-1.6</objURI>\r\n        <objURI>http://www.nic.cz/xml/epp/domain-1.4</objURI>\r\n        <objURI>http://www.nic.cz/xml/epp/nsset-1.2</objURI>\r\n        <objURI>http://www.nic.cz/xml/epp/keyset-1.3</objURI>\r\n        <svcExtension>\r\n          <extURI>http://www.nic.cz/xml/epp/enumval-1.2</extURI>\r\n        </svcExtension>\r\n      </svcs>\r\n    </login>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppLogout() {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <logout/>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppPollRequest() {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <poll op=\"req\"/>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppPollAcknowledge($msgid) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <poll op=\"ack\" msgID=\"" . $msgid . "\"/>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainCheck($domain) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <check>\r\n      <domain:check xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      </domain:check>\r\n    </check>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainInfo($domain, $eppKey) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <info>\r\n      <domain:info xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n      <domain:name>" . $domain . "</domain:name>\r\n    ";
		if ($eppKey != "") {
			$command .= "  <domain:authInfo>" . $eppKey . "</domain:authInfo>\r\n    ";
		}
		$command .= "  </domain:info>\r\n    </info>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainTransferQuery($domain, $eppKey) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <transfer op=\"query\">\r\n      <domain:transfer xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n      <domain:name>" . $domain . "</domain:name>\r\n    ";
		if ($eppKey != "") {
			$command .= "  <domain:authInfo>" . $eppKey . "</domain:authInfo>\r\n    ";
		}
		$command .= "</domain:transfer>\r\n    </transfer>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainCreate($domain, $periodUnit, $period, $nsset, $registrantContactID, $adminContactID, $techContactID, $billingContactID) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <create>\r\n      <domain:create xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:period unit=\"" . $periodUnit . "\">" . $period . "</domain:period>\r\n      ";
		if ($nsset != "") {
			//foreach ($nsset as $nssetID) {
			//	if (!($nssetID != "")) {
			//		continue;
			//	}
				$command .= "  <domain:nsset>" . $nsset . "</domain:nsset>\r\n      ";
		//	}
		}
		if ($registrantContactID != "") {
			$command .= "  <domain:registrant>" . $registrantContactID . "</domain:registrant>\r\n      ";
		}
		if ($adminContactID != "") {
			$command .= "  <domain:admin>" . $adminContactID . "</domain:admin>\r\n      ";
		}
		if ($techContactID != "") {
			$command .= "  <domain:tech>" . $techContactID . "</domain:admin>\r\n      ";
		}
		if ($billingContactID != "") {
			$command .= "  <domain:billing>" . $billingContactID . "</domain:admin>\r\n      ";
		}
		$command .= "  <domain:authInfo>" . $this->eppKey() . "</domain:authInfo>\r\n      </domain:create>\r\n    </create>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainDelete($domain) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <delete>\r\n      <domain:delete xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      </domain:delete>\r\n    </delete>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainRenew($domain, $curExpDate, $periodUnit, $period) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <renew>\r\n      <domain:renew xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:curExpDate>" . $curExpDate . "</domain:curExpDate>\r\n        <domain:period unit=\"" . $periodUnit . "\">" . $period . "</domain:period>\r\n      </domain:renew>\r\n    </renew>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainTransfer($domain, $periodUnit, $period, $eppKey) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <transfer op=\"request\">\r\n      <domain:transfer xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n  <domain:authInfo>" . $eppKey . "</domain:authInfo>\r\n      </domain:transfer>\r\n    </transfer>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainTransferCancel($domain) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <transfer op=\"cancel\">\r\n      <domain:transfer xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      </domain:transfer>\r\n    </transfer>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainTransferReject($domain) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <transfer op=\"reject\">\r\n      <domain:transfer xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      </domain:transfer>\r\n    </transfer>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainTransferApprove($domain) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <transfer op=\"approve\">\r\n      <domain:transfer xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      </domain:transfer>\r\n    </transfer>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainContactUpdate($domain, $newContact, $oldContact) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      ";
		if ($newContact != "") {
			
			if($newContact[1]["type"]=="admin") {
				$command .= "  <domain:add>\r\n      ";
				foreach ($newContact as $contact) {
					if (!($contact != "")) {
						continue;
					}
					if($contact["type"]=="registrant"){
					    continue;
					}
					$command .= "    <domain:" . $contact["type"] . ">" . $contact["id"] . "</domain:" . $contact["type"] . ">\r\n      ";
				}
				$command .= "  </domain:add>\r\n      ";
			}
			if ($newContact[0]["type"] == "registrant") {
				$command .= "  <domain:chg>\r\n            <domain:registrant>" . $newContact[0]["id"] . "</domain:registrant>\r\n          </domain:chg>\r\n      ";
			}
		}
		if ($Admin_old != "") {
			$command .= "  <domain:rem>\r\n      ";
			foreach ($oldContact as $contact) {
				if (!($contact != "")) {
					continue;
				}
				if($contact["type"]=="registrant"){
					    continue;
					}
				$command .= "    <domain:" . $contact["type"] . ">" . $contact["id"] . "</domain:" . $contact["type"] . ">\r\n      ";
			}
			$command .= "  </domain:rem>\r\n      ";
		}
		$command .= "</domain:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainNewEppKey($domain, $newEppKey) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:chg>\r\n          <domain:authInfo>" . $newEppKey . "</domain:authInfo>\r\n        </domain:chg>\r\n      </domain:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainDNSUpdate($domain, $ns, $oldNs) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n      ";
		if (!(empty($ns))) {
			$command .= "  <domain:add>\r\n            <domain:ns>\r\n      ";
			foreach ($ns as $data) {
				if (!($data != "")) {
					continue;
				}
				$command .= "  <domain:hostObj>" . $data . "</domain:hostObj>\r\n      ";
			}
			$command .= "    </domain:ns>\r\n        </domain:add>\r\n      ";
		}
		if (!(empty($oldNs))) {
			$command .= "  <domain:rem>\r\n            <domain:ns>\r\n      ";
			foreach ($oldNs as $data) {
				if (!($data != "")) {
					continue;
				}
				$command .= "  <domain:hostObj>" . $data . "</domain:hostObj>\r\n      ";
			}
			$command .= "</domain:ns>\r\n        </domain:rem>\r\n      ";
		}
		$command .= "</domain:update>\r\n\t</update>\r\n\t<clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainStatusAdd($domain, $status) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:add>\r\n      ";
		foreach ($status as $s) {
			if (!($s != "")) {
				continue;
			}
			$command .= "    <domain:status s=\"" . $s . "\"/>\r\n      ";
		}
		$command .= "  </domain:add>\r\n      </domain:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainStatusRemove($domain, $status) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:rem>\r\n      ";
		foreach ($status as $s) {
			if (!($s != "")) {
				continue;
			}
			$command .= "    <domain:status s=\"" . $s . "\"/>\r\n      ";
		}
		$command .= "  </domain:rem>\r\n      </domain:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppDomainRestore($domain, $restoreType, $restorePreData, $restorePostData, $restoreDelTime, $restoreResTime, $restoreResReason, $restoreStatement, $restoreOther) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:chg/>\r\n      </domain:update>\r\n    </update>\r\n    <extension>\r\n      <rgp:update xmlns:rgp=\"urn:ietf:params:xml:ns:rgp-1.0\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:rgp-1.0 rgp-1.0.xsd\">\r\n      ";
		if ($restoreType == "request") {
			$command .= "  <rgp:restore op=\"request\"/>\r\n      ";
		}
		if ($restoreType == "report") {
			$command .= "  <rgp:restore op=\"report\">\r\n          <rgp:report>\r\n            <rgp:preData>" . $restorePreData . "</rgp:preData>\r\n            <rgp:postData>" . $restorePostData . "</rgp:postData>\r\n            <rgp:delTime>" . $restoreDelTime . "</rgp:delTime>\r\n            <rgp:resTime>" . $restoreResTime . "</rgp:resTime>\r\n            <rgp:resReason>" . $restoreResReason . "</rgp:resReason>\r\n            <rgp:statement>" . $restoreStatement . "</rgp:statement>\r\n            <rgp:other>" . $restoreOther . "</rgp:other>\r\n          </rgp:report>\r\n        </rgp:restore>\r\n      ";
		}
		$command .= "</rgp:update>\r\n    </extension>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppHostCheck($hostId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <check>\r\n      <nsset:check xmlns:nsset=\"http://www.nic.cz/xml/epp/nsset-1.2\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/nsset-1.2 nsset-1.2.xsd\">\r\n        <nsset:id>" . $hostId . "</nsset:id>\r\n      </nsset:check>\r\n    </check>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppHostInfo($hostId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <info>\r\n      <nsset:info xmlns:nsset=\"http://www.nic.cz/xml/epp/nsset-1.2\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/nsset-1.2 nsset-1.2.xsd\">\r\n        <nsset:id>" . $hostId . "</nsset:id>\r\n      </nsset:info>\r\n    </info>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppHostCreate($hostId, $ns, $techId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <create>\r\n      <nsset:create xmlns:nsset=\"http://www.nic.cz/xml/epp/nsset-1.2\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/nsset-1.2 nsset-1.2.xsd\">\r\n        <nsset:id>" . $hostId . "</nsset:id>\r\n      ";
		foreach ($ns as $host) {
			if (!($host != "")) {
				continue;
			}
			$command .= "  <nsset:ns>\r\n          <nsset:name>" . $host . "</nsset:name>\r\n      ";
			//foreach ($host["ip"] as $ip) {
			//	if (!($ip["address"] != "")) {
				//	continue;
			//	}
			//	$command .= "    <nsset:addr>" . $ip["address"] . "</nsset:addr>\r\n      ";
			//}
			$command .= "  </nsset:ns>\r\n      ";
		}
		$command .= "  <nsset:tech>" . $techId . "</nsset:tech>\r\n      </nsset:create>\r\n    </create>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppHostDelete($hostId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <delete>\r\n      <nsset:delete xmlns:nsset=\"http://www.nic.cz/xml/epp/nsset-1.2\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/nsset-1.2 nsset-1.2.xsd\">\r\n        <nsset:id>" . $hostId . "</nsset:id>\r\n      </nsset:delete>\r\n    </delete>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppHostUpdate($hostId, $ns) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <nsset:update xmlns:nsset=\"http://www.nic.cz/xml/epp/nsset-1.2\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/nsset-1.2 nsset-1.2.xsd\">\r\n        <nsset:id>" . $hostId . "</nsset:id>\r\n      ";
		if ($ns["new"] != "") {
			$command .= "  <nsset:add>\r\n      ";
			foreach ($ns["new"] as $host) {
				$command .= "    <nsset:ns>\r\n                <nsset:name>" . $host["name"] . "</nsset:name>\r\n      ";
				foreach ($host["ip"] as $ip) {
					$command .= "        <nsset:addr>" . $ip["address"] . "</nsset:addr>\r\n      ";
				}
				$command .= "    </nsset:ns>\r\n      ";
			}
			$command .= "  </nsset:add>\r\n      ";
		}
		if ($ns["old"] != "") {
			$command .= "  <nsset:rem>\r\n      ";
			foreach ($ns["old"] as $host) {
				$command .= "  <nsset:name>" . $host["name"] . "</nsset:name>\r\n      ";
			}
			$command .= "  </nsset:rem>\r\n      ";
		}
		$command .= "</nsset:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppContactCheck($contactId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <check>\r\n      <contact:check xmlns:contact=\"http://www.nic.cz/xml/epp/contact-1.6\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/contact-1.6 contact-1.6.xsd\">\r\n        <contact:id>" . $contactId . "</contact:id>\r\n      </contact:check>\r\n    </check>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppContactInfo($contactId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <info>\r\n      <contact:info xmlns:contact=\"http://www.nic.cz/xml/epp/contact-1.6\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/contact-1.6 contact-1.6.xsd\">\r\n        <contact:id>" . $contactId . "</contact:id>\r\n      </contact:info>\r\n    </info>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppContactCreate($contactId, $postalInfo, $phone, $fax, $emailAddress, $eppKey, $discloseFlag, $identityType, $identityNumber, $vatNumber) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <create>\r\n      <contact:create xmlns:contact=\"http://www.nic.cz/xml/epp/contact-1.6\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/contact-1.6 contact-1.6.xsd\">\r\n      ";
		if ($contactId != "") {
			$command .= "  <contact:id>" . $contactId . "</contact:id>\r\n      ";
		}
		foreach ($postalInfo as $postalType => $postalTypeValue) {
			$command .= "  <contact:postalInfo>\r\n      ";
			foreach ($postalTypeValue as $field => $value) {
				if ($field == "name" || $field == "org") {
					if ($value != "") {
						$command .= "    <contact:" . $field . ">" . $value . "</contact:" . $field . ">\r\n      ";
					}
					else {
						$command .= "    <contact:" . $field . "/>\r\n      ";
					}
				}
				if ($field == "street") {
					$command .= "    <contact:addr>\r\n      ";
					foreach ($value as $street) {
						if ($street != "") {
							$command .= "      <contact:" . $field . ">" . $street . "</contact:" . $field . ">\r\n      ";
							continue;
						}
						$command .= "      <contact:" . $field . "/>\r\n      ";
					}
				}
				if (!($field == "city" || $field == "pc" || $field == "cc")) {
					continue;
				}
				if ($value != "") {
					$command .= "      <contact:" . $field . ">" . $value . "</contact:" . $field . ">\r\n      ";
					continue;
				}
				$command .= "      <contact:" . $field . "/>\r\n      ";
			}
			$command .= "    </contact:addr>\r\n        </contact:postalInfo>\r\n      ";
		}
		if ($phone != "") {
			$command .= "  <contact:voice>" . $phone . "</contact:voice>\r\n      ";
		}
		if ($fax != "") {
			$command .= "  <contact:fax>" . $fax . "</contact:fax>\r\n      ";
		}
		if ($emailAddress != "") {
			$command .= "  <contact:email>" . $emailAddress . "</contact:email>\r\n      ";
		}
		if ($identityNumber != "") {
			$command .= "  <contact:ident type=\"" . $identityType . "\">" . $identityNumber . "</contact:ident>\r\n      ";
		}
		if ($vatNumber != "") {
			$command .= "  <contact:vat>" . $vatNumber . "</contact:vat>\r\n      ";
		}
		if ($discloseFlag != "") {
			$command .= "  <contact:disclose flag=\"" . $discloseFlag . "\">\r\n            <contact:name/>\r\n            <contact:org/>\r\n            <contact:addr/>\r\n            <contact:voice/>\r\n            <contact:fax/>\r\n            <contact:email/>\r\n        </contact:disclose>\r\n      ";
		}
		$command .= "</contact:create>\r\n    </create>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppContactDelete($contactId) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <delete>\r\n      <contact:delete xmlns:contact=\"http://www.nic.cz/xml/epp/contact-1.6\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/contact-1.6 contact-1.6.xsd\">\r\n        <contact:id>" . $contactId . "</contact:id>\r\n      </contact:delete>\r\n    </delete>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}


	public function eppContactUpdate($contactId, $postalInfo, $phone, $fax, $emailAddress, $eppKey, $discloseFlag) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <contact:update xmlns:contact=\"http://www.nic.cz/xml/epp/contact-1.6\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/contact-1.6 contact-1.6.xsd\">\r\n        <contact:id>" . $contactId . "</contact:id>\r\n        <contact:chg>\r\n        ";
		foreach ($postalInfo as $postalType => $postalTypeValue) {
			$command .= "  <contact:postalInfo>\r\n        ";
			foreach ($postalTypeValue as $field => $value) {
				if ($field == "name" || $field == "org") {
					if ($value != "") {
						$command .= "    <contact:" . $field . ">" . $value . "</contact:" . $field . ">\r\n        ";
					}
					else {
						$command .= "    <contact:" . $field . "/>\r\n        ";
					}
				}
				if ($field == "street") {
					$command .= "    <contact:addr>\r\n        ";
					foreach ($value as $street) {
						if ($street != "") {
							$command .= "      <contact:" . $field . ">" . $street . "</contact:" . $field . ">\r\n        ";
							continue;
						}
						$command .= "      <contact:" . $field . "/>\r\n        ";
					}
				}
				if (!($field == "city" || $field == "sp" || $field == "pc" || $field == "cc")) {
					continue;
				}
				if ($value != "") {
					$command .= "      <contact:" . $field . ">" . $value . "</contact:" . $field . ">\r\n        ";
					continue;
				}
				$command .= "      <contact:" . $field . "/>\r\n        ";
			}
			$command .= "    </contact:addr>\r\n          </contact:postalInfo>\r\n        ";
		}
		if ($phone != "") {
			$command .= "  <contact:voice>" . $phone . "</contact:voice>\r\n        ";
		}
		else {
			$command .= "  <contact:voice/>\r\n        ";
		}
		if ($fax != "") {
			$command .= "  <contact:fax>" . $fax . "</contact:fax>\r\n        ";
		}
		else {
			$command .= "  <contact:fax/>\r\n        ";
		}
		if ($emailAddress != "") {
			$command .= "  <contact:email>" . $emailAddress . "</contact:email>\r\n        ";
		}
		else {
			$command .= "  <contact:email/>\r\n        ";
		}
		if ($discloseFlag != "") {
			$command .= "  <contact:disclose flag=\"" . $discloseFlag . "\">\r\n            <contact:name/>\r\n            <contact:org/>\r\n            <contact:addr/>\r\n            <contact:voice/>\r\n            <contact:fax/>\r\n            <contact:email/>\r\n          </contact:disclose>\r\n        ";
		}
		$command .= "</contact:chg>\r\n      </contact:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}
	
	public function eppDomainNSSETUpdate($domain, $newnsset) {
		$command = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\r\n<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">\r\n  <command>\r\n    <update>\r\n      <domain:update xmlns:domain=\"http://www.nic.cz/xml/epp/domain-1.4\" xsi:schemaLocation=\"http://www.nic.cz/xml/epp/domain-1.4 domain-1.4.xsd\">\r\n        <domain:name>" . $domain . "</domain:name>\r\n        <domain:chg>\r\n          <domain:nsset>" . $newnsset . "</domain:nsset>\r\n        </domain:chg>\r\n      </domain:update>\r\n    </update>\r\n    <clTRID>" . $this->clTRID() . "</clTRID>\r\n  </command>\r\n</epp>";
		return $command;
	}

}
