<?php
require dirname(__FILE__) ."/config.php";
require dirname(__FILE__) ."/Eppclass.php";
function fred_getConfigArray() {
	$configarray = array(
	 "Username" => array( "Type" => "text", "Size" => "20", "Description" => "Enter your username here", ),
	 "Password" => array( "Type" => "password", "Size" => "20", "Description" => "Enter your password here", ),
	 "TestMode" => array( "Type" => "yesno", ),
	);
	return $configarray;
}

function fred_GetNameservers($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    $xml=$epp->eppDomainInfo($params["sld"] . "." . $params["tld"], $params["transfersecret"]);
    
	$response = $epp->request($xml);
		$doc = new DOMDocument();
		$doc->loadXML($response);
		//if ($params["debug"] == "on") {
			logModuleCall("fredEPP", "EPP Domain Information", $xml, $response);
		//}
		$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
		$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
		if ($messagecode != "1000") {
			$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout", $xml, $response);
			}
			$values["error"] = $messagecode . " - " . $message;
			return $values;
		}
		//$ns = $doc->getElementsByTagName("hostObj");
		//Get the nsset id
		$nsset = $doc->getElementsByTagName("nsset")->item(0)->nodeValue;
		$hostarr = $epp->eppHostInfo($nsset);
		$hostinfo=$epp->request($hostarr);
        $doc = new DOMDocument();
        $doc->loadXML($hostinfo);
        $ns = $doc->getElementsByTagName('name');
		$i = 1;
		$values = array();
		foreach ($ns as $nn) {
			$values["ns" . $i] = $nn->nodeValue;
			++$i;
		}
	}
	catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
	$response = $epp->request($xml);
	//if ($params["debug"] == "on") {
		logModuleCall("fredEPP", "EPP Logout", $xml, $response);
	//}
	return $values;
}

function fred_SaveNameservers($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    $nameserver1 = $params["ns1"];
	$nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
	$nameserver4 = $params["ns4"];
	$domain = "$sld.$tld";
	try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    
    $xml=$epp->eppDomainInfo($params["sld"] . "." . $params["tld"], $params["transfersecret"]);
    $response = $epp->request($xml);
		$doc = new DOMDocument();
		$doc->loadXML($response);
		if ($params["debug"] == "on") {
			logModuleCall("fredEPP", "EPP Domain Information", $xml, $response);
		}
		$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
		$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
		//Get Registrant info
		$RegistrantContactID = $doc->getElementsByTagName("registrant")->item(0)->nodeValue;
		/*
		if ($messagecode != "1000") {
			$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout", $xml, $response);
			}
			$values["error"] = $messagecode . " - " . $message;
			return $values;
		}
		$nsarray = $doc->getElementsByTagName("hostObj");
		$currentns = array();
		foreach ($nsarray as $nn) {
			$currentns[] = $nn->nodeValue;
		}
		$newnsdiff = array_diff($ns, array_intersect($ns, $currentns));
		$oldnsdiff = array_diff($currentns, array_intersect($currentns, $ns));
		$newns = $newnsdiff;
		$oldns = $oldnsdiff;
			foreach ($newns as $hostname) {
				if (!($hostname != "")) {
					continue;
				}
				$xml = $epp->eppHostCheck($hostname);
				$response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Host Check", $xml, $response);
				}
				$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					}
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
				$available = $doc->getElementsByTagName("name")->item(0)->getAttribute("avail");
				if (!($available != "0" && $available != "false")) {
					continue;
				}
				$xml = $epp->eppHostCreate($hostname, "");
				$response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Host Create", $xml, $response);
				}
				$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if (!($messagecode != "1000")) {
					continue;
				}
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				}
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
		
		$xml = $epp->eppDomainDNSUpdate($params["sld"] . "." . $params["tld"], $newns, $oldns);
		$response = $epp->request($xml);
		$doc = new DOMDocument();
		$doc->loadXML($response);
		if ($params["debug"] == "on") {
			logModuleCall("fredEPP", "EPP Domain DNS Update", $xml, $response);
		}
		$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
		$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
		if ($messagecode != "1000" && $messagecode != "1001") {
			$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout", $xml, $response);
			}
			$values["error"] = $messagecode . " - " . $message;
			return $values;
		}
		*/


//Create New NSSET
 $hostID = $epp->eppContactId(16, "", "", 1);
					$xml = $epp->eppHostCheck($hostID);
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					//if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Host Check", $xml, $response);
					//}
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					$hostAvailable = $doc->getElementsByTagName("id")->item(0)->getAttribute("avail");
					if ($hostAvailable != "0" && $hostAvailable != "false") {
						/*$i = 0;
						foreach ($ns as $hostname) {
							$host[$i]["name"] = $hostname;
							$host[$i]["ip"] = array();
							++$i;
						}
						*/
	$nameservers=array();
    if (!empty($params["ns1"]))
        array_push($nameservers,$params["ns1"]);
    if (!empty($params["ns2"]))
        array_push($nameservers,$params["ns2"]);
    if(!empty($params["ns3"]))
        array_push($nameservers,$params["ns3"]);
    if(!empty($params["ns4"])) 
        array_push($nameservers,$params["ns4"]);
    if(!empty($params["ns5"])) 
        array_push($nameservers,$params["ns5"]);
						
						$xml = $epp->eppHostCreate($hostID, $nameservers, $RegistrantContactID);
						$response = $epp->request($xml);
						$doc = new DOMDocument();
						$doc->loadXML($response);
						
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Host Create", $xml, $response);
						//}
						$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
						$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
						if ($messagecode != "1000") {
							$xml = $epp->eppLogout();
							$response = $epp->request($xml);
							if ($params["debug"] == "on") {
								logModuleCall("fredEPP", "EPP Logout", $xml, $response);
							}
							$values["error"] = $messagecode . " - " . $message;
							return $values;
						}
					}
     //Update The domain with the new NSSET 
 $xml = $epp->eppDomainNSSETUpdate($domain, $hostID);
 $response = $epp->request($xml);
						$doc = new DOMDocument();
						$doc->loadXML($response);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Domain Update", $xml, $response);
						//}
						$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
						$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
						if ($messagecode != "1000") {
							$xml = $epp->eppLogout();
							$response = $epp->request($xml);
							if ($params["debug"] == "on") {
								logModuleCall("fredEPP", "EPP Logout", $xml, $response);
							}
							$values["error"] = $messagecode . " - " . $message;
							return $values;
						}
	}
	catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
	$response = $epp->request($xml);
	if ($params["debug"] == "on") {
		logModuleCall("fredEPP", "EPP Logout", $xml, $response);
	}
	
	return $values;
    
	
}

function fred_GetRegistrarLock($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	# Put your code to get the lock status here
	if ($lock=="1") {
		$lockstatus="locked";
	} else {
		$lockstatus="unlocked";
	}
	return $lockstatus;
}

function fred_SaveRegistrarLock($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	if ($params["lockenabled"]) {
		$lockstatus="locked";
	} else {
		$lockstatus="unlocked";
	}
	# Put your code to save the registrar lock here
	# If error, return the error message in the value below
	$values["error"] = $Enom->Values["Err1"];
	return $values;
}

function fred_GetEmailForwarding($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	# Put your code to get email forwarding here - the result should be an array of prefixes and forward to emails (max 10)
	foreach ($result AS $value) {
		$values[$counter]["prefix"] = $value["prefix"];
		$values[$counter]["forwardto"] = $value["forwardto"];
	}
	return $values;
}

function fred_SaveEmailForwarding($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	foreach ($params["prefix"] AS $key=>$value) {
		$forwardarray[$key]["prefix"] =  $params["prefix"][$key];
		$forwardarray[$key]["forwardto"] =  $params["forwardto"][$key];
	}
	# Put your code to save email forwarders here
}

function fred_GetDNS($params) {
    $username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    # Put your code here to get the current DNS settings - the result should be an array of hostname, record type, and address
    $hostrecords = array();
    $hostrecords[] = array( "hostname" => "ns1", "type" => "A", "address" => "192.168.0.1", );
    $hostrecords[] = array( "hostname" => "ns2", "type" => "A", "address" => "192.168.0.2", );
	return $hostrecords;

}

function fred_SaveDNS($params) {
    $username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    # Loop through the submitted records
	foreach ($params["dnsrecords"] AS $key=>$values) {
		$hostname = $values["hostname"];
		$type = $values["type"];
		$address = $values["address"];
		# Add your code to update the record here
	}
    # If error, return the error message in the value below
	$values["error"] = $Enom->Values["Err1"];
	return $values;
}

function fred_RegisterDomain($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$regperiod = $params["regperiod"];
	$ns[0] = $params["ns1"];
	$ns[1] = $params["ns2"];
        $ns[2] = $params["ns3"];
        $ns[3] = $params["ns4"];
	/*# Registrant Details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["phonenumber"];
	# Admin Details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminphonenumber"];
	*/
	# Put your code to register domain here
	# If error, return the error message in the value below
	$name = $params["original"]["firstname"] . " " . $params["original"]["lastname"];
	$companyname = $params["original"]["companyname"];
	$email = $params["email"];
	$address1 = $params["original"]["address1"];
	$address2 = $params["original"]["address2"];
	$city = $params["original"]["city"];
	$state = $params["original"]["state"];
	$postcode = $params["postcode"];
	$country = strtoupper($params["country"]);
	$callingcode = $countrycallingcodes[$country];
	$phonenumber = $params["phonenumberformatted"];
	
	try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    $xml = $epp->eppDomainCheck($params["original"]["sld"] . "." . $params["original"]["tld"]);
	$response = $epp->request($xml);
		
		$doc = new DOMDocument();
		$doc->loadXML($response);
		//if ($params["debug"] == "on") {
			logModuleCall("fredEPP", "EPP Domain Check - Registration", $xml, $response);
		//}
		$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
		$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
		if ($messagecode != "1000") {
			$xml = $registry->eppLogout();
			$response = $client->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout", $xml, $response);
			}
			$values["error"] = $messagecode . " - " . $message;
			return $values;
		}
    $domainAvailable = $doc->getElementsByTagName("name")->item(0)->getAttribute("avail");
    if ($domainAvailable != "0" && $domainAvailable != "false") {
		$registrantContactID = $epp->eppContactId(16, "", "", 1);
		$xml = $epp->eppContactCheck($registrantContactID);
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					//if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Contact Check", $xml, $response);
					//}
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						//}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					$contactAvailable = $doc->getElementsByTagName("id")->item(0)->getAttribute("avail");
					if ($contactAvailable != "0" && $contactAvailable != "false") {
					$postalInfo = array("loc" => array("name" => $name, "org" => $companyname, "street" => array(0 => $address1, 1 => $address2, 2 => ""), "city" => $city, "sp" => $state, "pc" => $postcode, "cc" => $country));
					$xml = $epp->eppContactCreate($registrantContactID, $postalInfo, $phonenumber, "", $email, $epp->eppKey(), "", $legalType, $identityNumber, $vatNumber);
					
						logModuleCall("fredEPP", "EPP Contact Create", $xml, "");
					
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					
						logModuleCall("fredEPP", "EPP Contact Create", $xml, $response);
					
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
				}
			
			
	$adminContactID = $epp->eppContactId(16, "", "", 1);
	$xml = $epp->eppContactCheck($adminContactID);
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					//if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Admin Contact Check", $xml, $response);
					//}
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					$contactAvailable = $doc->getElementsByTagName("id")->item(0)->getAttribute("avail");
					if ($contactAvailable != "0" && $contactAvailable != "false") {
						$postalInfo = array("" => array("name" => $name, "org" => $companyname, "street" => array(0 => $address1, 1 => $address2, 2 => ""), "city" => $city, "sp" => $state, "pc" => $postcode, "cc" => $country));
						$xml = $epp->eppContactCreate($adminContactID, $postalInfo, $phonenumber, "", $email, $epp->eppKey(), "", "", "", "");
						$response = $epp->request($xml);
						$doc = new DOMDocument();
						$doc->loadXML($response);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "Admin EPP Contact Create", $xml, $response);
						//}
						$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
						$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
						if ($messagecode != "1000") {
							$xml = $epp->eppLogout();
							$response = $epp->request($xml);
							if ($params["debug"] == "on") {
								logModuleCall("fredEPP", "EPP Logout", $xml, $response);
							}
							$values["error"] = $messagecode . " - " . $message;
							return $values;
						}
						$adminContact[0] = $adminContactID;
					}
	$techContact = "";
    $billingContact = "";
    //Create hosts
    $hostID = $epp->eppContactId(16, "", "", 1);
					$xml = $epp->eppHostCheck($hostID);
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					//if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Host Check", $xml, $response);
					//}
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					$hostAvailable = $doc->getElementsByTagName("id")->item(0)->getAttribute("avail");
					if ($hostAvailable != "0" && $hostAvailable != "false") {
						$i = 0;
						foreach ($ns as $hostname) {
							$host[$i]["name"] = $hostname;
							$host[$i]["ip"] = array();
							++$i;
						}
						$xml = $epp->eppHostCreate($hostID, $host, $adminContact[0]);
						$response = $epp->request($xml);
						$doc = new DOMDocument();
						$doc->loadXML($response);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Host Create", $xml, $response);
						//}
						$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
						$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
						if ($messagecode != "1000") {
							$xml = $epp->eppLogout();
							$response = $epp->request($xml);
							if ($params["debug"] == "on") {
								logModuleCall("fredEPP", "EPP Logout", $xml, $response);
							}
							$values["error"] = $messagecode . " - " . $message;
							return $values;
						}
					}
					//create the domain
				$xml = $epp->eppDomainCreate($params["original"]["sld"] . "." . $params["original"]["tld"], "y", $params["regperiod"], $hostID, $registrantContactID, $adminContactID, $techContact, $billingContact);
				$response = $epp->request($xml);
			$doc = new DOMDocument();
			$doc->loadXML($response);
			//if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Domain Create", $xml, $response);
			//}
			$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
			$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
			if ($messagecode != "1000") {
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				}
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
			//Synchronize records
			$xml = $epp->eppDomainInfo($params["original"]["sld"] . "." . $params["original"]["tld"], $params["transfersecret"]);
			$doc = new DOMDocument();
			$doc->loadXML($response);
			//if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Domain Information - Create", $xml, $response);
			//}
			$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
			$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
			if ($messagecode != "1000") {
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				}
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
			$domain = $params["original"]["sld"] . "." . $params["original"]["tld"];
			$regdate = gmdate("Y-m-d", strtotime($doc->getElementsByTagName("crDate")->item(0)->nodeValue));
			$expdate = gmdate("Y-m-d", strtotime($doc->getElementsByTagName("exDate")->item(0)->nodeValue));
			$query = "UPDATE tbldomains SET registrationdate='" . $regdate . "', expirydate='" . $expdate . "', status='Active' WHERE domain='" . $domain . "'";
			mysql_query($query);
		}
	}
	catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
	$response = $epp->request($xml);
	if ($params["debug"] == "on") {
		logModuleCall("fredEPP", "EPP Logout", $xml, $response);
	}
	//return $values;
}
function fred_GetContactDetails($params) {
# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
$domain = "$sld.$tld";
try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    $xml = $epp->eppDomainInfo($params["sld"] . "." . $params["tld"], $params["transfersecret"]);
    $response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				
					logModuleCall("fredEPP", "EPP Domain Information - Contact", $xml, $response);
				
	            $messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
		$RegistrantContactID = $doc->getElementsByTagName("registrant")->item(0)->nodeValue;
		$AdminContactID = $doc->getElementsByTagName("admin")->item(0)->nodeValue;
		//$TechContactID = $doc->getElementsByTagName("registrant")->item(0)->nodeValue;
		//$BillingContactID = $doc->getElementsByTagName("registrant")->item(0)->nodeValue;
			if ($RegistrantContactID != "") {
			$xml = $epp->eppContactInfo($RegistrantContactID);
			$response = $epp->request($xml);
			$doc = new DOMDocument();
			$doc->loadXML($response);
			
				logModuleCall("fredEPP", "EPP Registrant Contact Information", $xml, $response);
			
			$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
			$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
			if ($messagecode != "1000") {
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				if ($registrydata["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				}
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
			
					
					$values["Registrant"]["Name"] = $doc->getElementsByTagName("name")->item(0)->nodeValue;
						
					$values["Registrant"]["Company Name"] = $doc->getElementsByTagName("org")->item(0)->nodeValue;
						
					
					$values["Registrant"]["Email Address"] = $doc->getElementsByTagName("email")->item(0)->nodeValue;
					$values["Registrant"]["Address 1"] = $doc->getElementsByTagName("street")->item(0)->nodeValue;
					
					$values["Registrant"]["City"] = $doc->getElementsByTagName("city")->item(0)->nodeValue;
					
						$values["Registrant"]["State"] = $doc->getElementsByTagName("sp")->item(0)->nodeValue;
					
					
						$values["Registrant"]["Zip"] = $doc->getElementsByTagName("pc")->item(0)->nodeValue;
					
					$values["Registrant"]["Country"] = $doc->getElementsByTagName("cc")->item(0)->nodeValue;
				    $values["Registrant"]["Phone Number"] = $doc->getElementsByTagName("voice")->item(0)->nodeValue;
			
			
			
		}
		if ($AdminContactID != "") {
			$xml = $epp->eppContactInfo($AdminContactID);
			$response = $epp->request($xml);
			$doc = new DOMDocument();
			$doc->loadXML($response);
			
				logModuleCall("fredEPP", "EPP Admin Contact Information", $xml, $response);
			
			$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
			$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
			if ($messagecode != "1000") {
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
			
					$values["Admin"]["Name"] = $doc->getElementsByTagName("name")->item(0)->nodeValue;
					$values["Admin"]["Email Address"] = $doc->getElementsByTagName("email")->item(0)->nodeValue;
					$values["Admin"]["Address 1"] = $doc->getElementsByTagName("street")->item(0)->nodeValue;
					$values["Admin"]["Address 2"] = $doc->getElementsByTagName("street")->item(1)->nodeValue;
					$values["Admin"]["Address 3"] = $doc->getElementsByTagName("street")->item(2)->nodeValue;
					$values["Admin"]["City"] = $doc->getElementsByTagName("city")->item(0)->nodeValue;				
					$values["Admin"]["State"] = $doc->getElementsByTagName("sp")->item(0)->nodeValue;					
					$values["Admin"]["Zip"] = $doc->getElementsByTagName("pc")->item(0)->nodeValue;					
					$values["Admin"]["Country"] = $doc->getElementsByTagName("cc")->item(0)->nodeValue;
				    $values["Admin"]["Phone Number"] = $doc->getElementsByTagName("voice")->item(0)->nodeValue;
						
			
		}
		/*if ($TechContactID != "") {
			$xml = $epp->eppContactInfo($TechContactID);
			$response = $epp->request($xml);
			$doc = new DOMDocument();
			$doc->loadXML($response);
			
				logModuleCall("fredEPP", "EPP Tech Contact Information", $xml, $response);
			
			$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
			$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
			if ($messagecode != "1000") {
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
			
					$values["Tech"]["Name"] = $doc->getElementsByTagName("name")->item(0)->nodeValue;
					
					$values["Tech"]["Company Name"] = $doc->getElementsByTagName("org")->item(0)->nodeValue;
					
					$values["Tech"]["Email Address"] = $doc->getElementsByTagName("email")->item(0)->nodeValue;
					$values["Tech"]["Address 1"] = $doc->getElementsByTagName("street")->item(0)->nodeValue;
					$values["Tech"]["Address 2"] = $doc->getElementsByTagName("street")->item(1)->nodeValue;
					$values["Tech"]["Address 3"] = $doc->getElementsByTagName("street")->item(2)->nodeValue;
					$values["Tech"]["City"] = $doc->getElementsByTagName("city")->item(0)->nodeValue;
					
						$values["Tech"]["State"] = $doc->getElementsByTagName("sp")->item(0)->nodeValue;
					
					
						$values["Tech"]["Zip"] = $doc->getElementsByTagName("pc")->item(0)->nodeValue;
					
					$values["Tech"]["Country"] = $doc->getElementsByTagName("cc")->item(0)->nodeValue;
				
			list($values["Tech"]["Telephone Number CC"], $values["Tech"]["Telephone Number"]) = explode(".", $doc->getElementsByTagName("voice")->item(0)->nodeValue);
			$values["Tech"]["Telephone Number CC"] = preg_replace("#[^0-9]#i", "", $values["Tech"]["Telephone Number CC"]);
			
			list($values["Tech"]["Fax Number CC"], $values["Tech"]["Fax Number"]) = explode(".", $doc->getElementsByTagName("fax")->item(0)->nodeValue);
			$values["Tech"]["Fax Number CC"] = preg_replace("#[^0-9]#i", "", $values["Tech"]["Fax Number CC"]);
			
			
		}
		if ($BillingContactID != "") {
			$xml = $epp->eppContactInfo($BillingContactID);
			$response = $epp->request($xml);
			$doc = new DOMDocument();
			$doc->loadXML($response);
			
				logModuleCall("fredEPP", "EPP Billing Contact Information", $xml, $response);
			
			$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
			$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
			if ($messagecode != "1000") {
				$xml = $epp->eppLogout();
				$response = $epp->request($xml);
				
					logModuleCall("fredEPP", "EPP Logout", $xml, $response);
				
				$values["error"] = $messagecode . " - " . $message;
				return $values;
			}
			
			
					$values["Billing"]["Name"] = $doc->getElementsByTagName("name")->item(0)->nodeValue;
					$values["Billing"]["Company Name"] = $doc->getElementsByTagName("org")->item(0)->nodeValue;
					$values["Billing"]["Email Address"] = $doc->getElementsByTagName("email")->item(0)->nodeValue;
					$values["Billing"]["Address 1"] = $doc->getElementsByTagName("street")->item(0)->nodeValue;
					$values["Billing"]["Address 2"] = $doc->getElementsByTagName("street")->item(1)->nodeValue;
					$values["Billing"]["Address 3"] = $doc->getElementsByTagName("street")->item(2)->nodeValue;
					$values["Billing"]["City"] = $doc->getElementsByTagName("city")->item(0)->nodeValue;
					$values["Billing"]["State"] = $doc->getElementsByTagName("sp")->item(0)->nodeValue;
					$values["Billing"]["Zip"] = $doc->getElementsByTagName("pc")->item(0)->nodeValue;
					$values["Billing"]["Country"] = $doc->getElementsByTagName("cc")->item(0)->nodeValue;
				
			list($values["Billing"]["Telephone Number CC"], $values["Billing"]["Telephone Number"]) = explode(".", $doc->getElementsByTagName("voice")->item(0)->nodeValue);
			$values["Billing"]["Telephone Number CC"] = preg_replace("#[^0-9]#i", "", $values["Billing"]["Telephone Number CC"]);
			list($values["Billing"]["Fax Number CC"], $values["Billing"]["Fax Number"]) = explode(".", $doc->getElementsByTagName("fax")->item(0)->nodeValue);
			$values["Billing"]["Fax Number CC"] = preg_replace("#[^0-9]#i", "", $values["Billing"]["Fax Number CC"]);
			
		} */
	}
    catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout", $xml, $response);
			}
			return $values;
}	


function fred_TransferDomain($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$regperiod = $params["regperiod"];
	$transfersecret = $params["transfersecret"];
	$nameserver1 = $params["ns1"];
	$nameserver2 = $params["ns2"];
	# Registrant Details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["phonenumber"];
	# Admin Details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminphonenumber"];
	# Put your code to transfer domain here
	# If error, return the error message in the value below
	try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    $name = $params["original"]["firstname"] . " " . $params["original"]["lastname"];
	$companyname = $params["original"]["companyname"];
	$email = $params["email"];
	$address1 = $params["original"]["address1"];
	$address2 = $params["original"]["address2"];
	$city = $params["original"]["city"];
	$state = $params["original"]["state"];
	$postcode = $params["postcode"];
	$country = strtoupper($params["country"]);
				
				//The Domain Transfer Request
				
	$xml = $epp->eppDomainTransfer($params["original"]["sld"] . "." . $params["original"]["tld"], "y", $params["regperiod"], $params["transfersecret"]);
	$response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				//if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Domain Transfer", $xml, $response);
				//}
				$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000" && $messagecode != "1001") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					}
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
				$domain = $params["original"]["sld"] . "." . $params["original"]["tld"];
				$query = "UPDATE tbldomains SET status='Pending Transfer' WHERE domain='" . $domain . "'";
				mysql_query($query);

}
catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$values["error"] = $error;
	return $values;
}

function fred_RenewDomain($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$regperiod = $params["regperiod"];
	# Put your code to renew domain here
	# If error, return the error message in the value below
	try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    $xml = $epp->eppDomainInfo($params["sld"] . "." . $params["tld"], $params["transfersecret"]);
    $response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Domain Information", $xml, $response);
				}
	$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					}
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
				list($curExpDate) = explode("T", $doc->getElementsByTagName("exDate")->item(0)->nodeValue);
	$xml = $epp->eppDomainRenew($params["sld"] . "." . $params["tld"], $curExpDate, "y", $params["regperiod"]);
	$response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				//if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Domain Renew", $xml, $response);
				//}
				$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					}
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
			}
    catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout", $xml, $response);
			}
			return $values;
}
	

/*function fred_GetContactDetails($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	# Put your code to get WHOIS data here
	# Data should be returned in an array as follows
	$values["Registrant"]["First Name"] = $firstname;
	$values["Registrant"]["Last Name"] = $lastname;
	$values["Admin"]["First Name"] = $adminfirstname;
	$values["Admin"]["Last Name"] = $adminlastname;
	$values["Tech"]["First Name"] = $techfirstname;
	$values["Tech"]["Last Name"] = $techlastname;
	return $values;
}
*/
function fred_SaveContactDetails($params) {
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	# Data is returned as specified in the GetContactDetails() function
	$registrantName = $params["contactdetails"]["Registrant"]["Name"];
	$registrantCompany = $params["contactdetails"]["Registrant"]["Company Name"];
	$registrantEmail = $params["contactdetails"]["Registrant"]["Email Address"];
	$registrantStreet = $params["contactdetails"]["Registrant"]["Address 1"];
	$registrantCity = $params["contactdetails"]["Registrant"]["City"];
	$registrantState = $params["contactdetails"]["Registrant"]["State"];
	$registrantZip = $params["contactdetails"]["Registrant"]["Zip"];
	$registrantCountry = $params["contactdetails"]["Registrant"]["Country"];
	$registrantPhonenumber = $params["contactdetails"]["Registrant"]["Phone Number"];
	$adminName = $params["contactdetails"]["Admin"]["Name"];
	$adminCompany = $params["contactdetails"]["Admin"]["Company Name"];
	$adminEmail = $params["contactdetails"]["Admin"]["Email Address"];
	$adminStreet = $params["contactdetails"]["Admin"]["Address 1"];
	$adminCity = $params["contactdetails"]["Admin"]["City"];
	$adminState = $params["contactdetails"]["Admin"]["State"];
	$adminZip = $params["contactdetails"]["Admin"]["Zip"];
	$adminCountry = $params["contactdetails"]["Admin"]["Country"];
	$adminPhonenumber = $params["contactdetails"]["Admin"]["Phone Number"];
	# Put your code to save new WHOIS data here
	# If error, return the error message in the value below
	$domain=$params["sld"] . "." . $params["tld"];
try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    //Get old Contact IDs
    $xml = $epp->eppDomainInfo($params["sld"] . "." . $params["tld"], $params["transfersecret"]);
    $response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				
					logModuleCall("fredEPP", "EPP Domain Information - Contact Update", $xml, $response);
				
	            $messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
		$Registrant_old = $doc->getElementsByTagName("registrant")->item(0)->nodeValue;
		$Admin_old = $doc->getElementsByTagName("admin")->item(0)->nodeValue;
    //Generate new registrant Contact
        $registrantContactID = $epp->eppContactId(16, "", "", 1);
		$xml = $epp->eppContactCheck($registrantContactID);
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					//if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Contact Check", $xml, $response);
					//}
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						//}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					$contactAvailable = $doc->getElementsByTagName("id")->item(0)->getAttribute("avail");
					if ($contactAvailable != "0" && $contactAvailable != "false") {
					$postalInfo = array("loc" => array("name" => $registrantName, "org" => $registrantCompany, "street" => array(0 => $registrantStreet, 1 => "", 2 => ""), "city" => $registrantCity, "sp" => $registrantState, "pc" => $registrantZip, "cc" => $registrantCountry));
					$xml = $epp->eppContactCreate($registrantContactID, $postalInfo, $registrantPhonenumber, "", $registrantEmail, $epp->eppKey(), "", "", "", "");
					
						logModuleCall("fredEPP", "EPP Contact Create", $xml, "");
					
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					
						logModuleCall("fredEPP", "EPP Contact Create", $xml, $response);
					
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
				}
			
			
	$adminContactID = $epp->eppContactId(16, "", "", 1);
	$xml = $epp->eppContactCheck($adminContactID);
					$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					//if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Admin Contact Check", $xml, $response);
					//}
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					$contactAvailable = $doc->getElementsByTagName("id")->item(0)->getAttribute("avail");
					if ($contactAvailable != "0" && $contactAvailable != "false") {
						$postalInfo = array("" => array("name" => $adminName, "org" => $adminCompany, "street" => array(0 => $adminStreet, 1 => "", 2 => ""), "city" => $adminCity, "sp" => $adminState, "pc" => $adminZip, "cc" => $adminCountry));
						$xml = $epp->eppContactCreate($adminContactID, $postalInfo, $adminPhonenumber, "", $adminEmail, $epp->eppKey(), "", "", "", "");
						$response = $epp->request($xml);
						$doc = new DOMDocument();
						$doc->loadXML($response);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "Admin EPP Contact Create", $xml, $response);
						//}
						$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
						$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
						if ($messagecode != "1000") {
							$xml = $epp->eppLogout();
							$response = $epp->request($xml);
							if ($params["debug"] == "on") {
								logModuleCall("fredEPP", "EPP Logout", $xml, $response);
							}
							$values["error"] = $messagecode . " - " . $message;
							return $values;
						}
						$adminContact[0] = $adminContactID;
					}
				//Update Domain
				$newcontact[0]["type"] = "registrant";
                $newcontact[0]["id"]    =  $registrantContactID;
                $newcontact[1]["type"] = "admin";
                $newcontact[1]["id"] = $adminContactID; 
                $oldcontact[0]=$Registrant_old;
                $oldcontact[1]=$Admin_old; 
				$xml = $epp->eppDomainContactUpdate($domain, $newcontact, $oldContact);	  
				$response = $epp->request($xml);
					$doc = new DOMDocument();
					$doc->loadXML($response);
					
						logModuleCall("fredEPP", "EPP Domain Contact Update", $xml, $response);
					
					$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
					$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
					if ($messagecode != "1000") {
						$xml = $epp->eppLogout();
						$response = $epp->request($xml);
						//if ($params["debug"] == "on") {
							logModuleCall("fredEPP", "EPP Logout", $xml, $response);
						//}
						$values["error"] = $messagecode . " - " . $message;
						return $values;
					}
					
			}
    catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout Auth", $xml, $response);
			}
			return $values;
}

function fred_GetEPPCode($params) {
    $username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    try {
	//login
	$epp = new myfredEPP();
    $epp->Login(); // Add login XML
    $epp->Process();
    //end Login
    $xml = $epp->eppDomainInfo($params["sld"] . "." . $params["tld"], $params["transfersecret"]);
    $response = $epp->request($xml);
				$doc = new DOMDocument();
				$doc->loadXML($response);
				if ($params["debug"] == "on") {
					logModuleCall("fredEPP", "EPP Domain Information - EPP Code", $xml, $response);
				}
	$messagecode = $doc->getElementsByTagName("result")->item(0)->getAttribute("code");
				$message = $doc->getElementsByTagName("msg")->item(0)->nodeValue;
				if ($messagecode != "1000") {
					$xml = $epp->eppLogout();
					$response = $epp->request($xml);
					if ($params["debug"] == "on") {
						logModuleCall("fredEPP", "EPP Logout", $xml, $response);
					}
					$values["error"] = $messagecode . " - " . $message;
					return $values;
				}
$auth = $doc->getElementsByTagName("authInfo")->item(0)->nodeValue;										
$values["eppcode"] = $auth;				
			}
    catch (Exception $e) {
		$values["error"] = $e->getMessage();
		return $values;
	}
	$xml = $epp->eppLogout();
			$response = $epp->request($xml);
			if ($params["debug"] == "on") {
				logModuleCall("fredEPP", "EPP Logout Auth", $xml, $response);
			}
			return $values;
}

function fred_RegisterNameserver($params) {
    $username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    $nameserver = $params["nameserver"];
    $ipaddress = $params["ipaddress"];
    # Put your code to register the nameserver here
    # If error, return the error message in the value below
    $values["error"] = $error;
    return $values;
}

function fred_ModifyNameserver($params) {
    $username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    $nameserver = $params["nameserver"];
    $currentipaddress = $params["currentipaddress"];
    $newipaddress = $params["newipaddress"];
    # Put your code to update the nameserver here
    # If error, return the error message in the value below
    $values["error"] = $error;
    return $values;
}

function fred_DeleteNameserver($params) {
    $username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
    $nameserver = $params["nameserver"];
    # Put your code to delete the nameserver here
    # If error, return the error message in the value below
    $values["error"] = $error;
    return $values;
}

?>
