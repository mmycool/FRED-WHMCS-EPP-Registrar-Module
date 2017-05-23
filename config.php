<?php


// EPP Server settings
define('EPP_HOST',          'sslv3://server');
define('EPP_PORT',          700);
define('EPP_TIMEOUT',       60);

// EPP Cert information
define('EPP_CERT',         'cert.pem');
//define('EPP_CERT_PASS',     'AfRiH100A');
//define('EPP_CA','CA.pem');

// EPP Auth information
define('EPP_USER',          'GGGGGG');
define('EPP_PWD',           'KKKKKKKK');

// EPP Log settings
define('EPP_LOG',            true);
define('EPP_LOG_FILE',       'mikeslog.xml');
define('FORMAT_OUTPUT',      true);

define('XMLNS_EPP',         'urn:ietf:params:xml:ns:epp-1.0');
define('XSCHEMA_EPP',       'urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd');
define('XMLNS_XSCHEMA',     'http://www.w3.org/2001/XMLSchema-instance');
/*
$cert = dirname(__FILE__) ."/cert.pem";
require dirname(__FILE__) ."/Eppclass.php";
//$epp = new myfred($registry, $hostname, $port, $username, $password, $timeout, $protocol, $passphrase, $licensekey, $localkey);
//$response = $epp->Connect();
$con = new myfred($hostname, $port = 700, $timeout = 60,$cert);
//$my = $con->connect($hostname, $port = 700, $timeout = 60,$cert);
$kk = $con->eppLogin(username, password);
$min = $con->Process();

$epp = new myfredEPP();
$epp->Login(); // Add login XML
$epp->Process();
$kk=$epp->eppDomainInfo("cheki.mw","");
print_r($kk);
$me=$epp->request($kk);
//$epp->eppLogin(EPP_USER, EPP_PWD);
//print_r($epp->Process());
print_r($me);
*/

