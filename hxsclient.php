<?PHP
/**

	HXS API (v2) Webservices Client

	The HostingXS API Client is an open source library to implement products & services on own interfaces.

		W-Information:	http://api.hostingxs.nl
		E-Contact:	info@hostingxs.nl
		E-Support:	support@hostingxs.nl

	i)	Please note that this client requires a valid reseller login ID and password
	i)	Implementation of this API client without adhering to the requirements on api.hostingxs.nl is at your own risk
	i)	Costs forthcoming from mis-/abusing this client library are passed on to the (client) implementer
	
	@author		D. Klabbers - HostingXS B.V.
	@date		June 28 2012
*/
class hxsclient {
	// URL is fixed; we highly recommend leaving it as is
	private $url		= "https://api.hostingxs.nl/v2/";
	private $apikey		= false;
	public $error		= false;
	
	private $isreseller	= false;
	private $isaffiliate	= false;
	
	static private $attempts	= 0;
	
	function __construct( $un , $pw , $sandbox=false , $apikey=false ) {
		$this -> continueSession();
		$this -> c			= curl_init();
		curl_setopt( $this -> c , CURLOPT_RETURNTRANSFER , true );
		curl_setopt( $this -> c , CURLOPT_HTTPHEADER , array( "Content-Type: application/json" ));
		$this -> un			= $un;
		$this -> pw			= $pw;
		if( $apikey && !$this -> apikey ) {
			$this -> apikey		= $apikey;
			return true;
		}
		elseif( !$this -> apikey ) {
			return $this -> auth( $sandbox );
		}
	}
	/**
		Api client types
	*/
	public function isreseller() {
		return $this -> isreseller;
	}
	public function isaffiliate() {
		return $this -> isaffiliate;
	}
	/**
	*	Overrule the order (or any other call) to be treated as an affiliate, not as a reseller
	*	@note this only works if you are also an affiliate
	*/
	public function setaffiliate() {
		$this -> isaffiliate	= true;
		$this -> isreseller	= !$this -> isaffiliate;
	}
	/**
		Order functions
	*/
	function order( $customer , $domains=false , $products=false ) {
		$this -> constructURI( "order/" );
		$this -> setPost();
		$post				= array(
							"product" 	=> $products,
							"domain"	=> $domains,
							"customer"	=> $customer,
		);
		$this -> setPostVariables( $post );
		$ret				= $this -> call();
		$this -> unSetPostVariables();
		$this -> setGet();
		if( $this -> isaffiliate && !$this -> isreseller ) {
			$post['affiliatemode']	= 1;
		}
		return $ret;
	}
	/**
	*	Domain checking
	*
	*	@note	verifies the availability and pricing of a domain
	*	@param	dom	(string) the domain name
	*	@return false or a domain object with availability, prices and alternatives when not available
	*/
	function checkDomain( $dom=false ) {
		$ret  		= $this -> checkDomains( array( $dom ));
		if( $ret && count($ret)) {
			return array_shift($ret);
		}
		return false;
	}
	/**
	*	@param	doms	(array) of (strings) domain names
	*	@return false or array of domain objects which were validated
	*/
	function checkDomains( $doms=false ) {
		if( !$doms || !is_array( $doms ) || !count( $doms ) ) {
			# this is not possible
			$this -> error[]		= "No domains or no array of domains given.";
			return false;
		}
		$i	= 0;
		foreach( $doms as $d ) {
			$tmp 	= new hxsdomain( $d );
			if( !$tmp -> valid ) {
				$this -> error[]	= sprintf( "Domain is not valid %s" , $d );
			}
			$check[]	= $tmp -> name;
			$i++;
		}
		if( $i > 0 ) {
			$this -> constructURI( "domain/".implode(",",$check) );
			$this -> setGet();
			return $this -> call();
		}
		return false;
	}
	/**
	*	Get the current state of a domain and whether it's cancellable etc.	
	*	@param dom		the domain to check, this is a domain name, not the object
	*	@param customerid	the customerID this domain belongs to
	*	@return ( name , registered , expiration , creditable , periodicity , allow-removal , state , customerid )
	*/
	function domainState( $dom=false , $customerid=false ) {
		
		$this -> constructURI( sprintf("domain/status/%s/%d" , $dom , $customerid ));
		$this -> setGet();

		return $this -> call();
	}
	/**
	*	Cancel a domain name
	*	WARNING			THE RESELLER IS COMPLETELY RESPONSIBLE FOR CANCELLING DOMAINS SENSIBLY!
	*	@param dom		the domain name (not the object) which you want to cancel
	*	@param customerid	the customer ID of the domain, this is an extra security, if it mismatches the request will be denied
	*	@param date		the cancellation date in YYYY-MM-DD format; must be at least 1 month in the future (use domainState to check expirey of domains)
	*/
	function domainCancel( $dom , $customerid , $date ) {
		$this -> constructURI( sprintf( "domain/%s/%d/%s" , $dom , $customerid , $date ));
		$this -> setDelete();
		return $this -> call();
	}
	/**
	*	Customer functions
	*	@note	do not provide an id to receive a list; customers are always filtered based on reseller ID
	*/
	function getCustomer( $customerid=false ) {
		$this -> constructURI( "customer/".( $customerid ? (int) $customerid : false ) );
		$this -> setGet();
		return $this -> call();
	}
	/**
	*	Customer test function
	*	@note tests wether the entered form data is correct/sufficient
	*	@param	in		the array of form elements/values
	*	@return	false or customer object on success
	*/
	function testCustomer( $in=false ) {
		if( !$in ) { return false; }
		try {
			$c		= new hxs_customer( $in );
		} catch( Exception $e ) {
			$this -> error = $e;
			return false;
		}
		
		return $c;
	}
	/**
	*	Customer login function
	*	@note	allow visitors to verify themselves as HostingXS customers by logging into the control panel
	*	@param 	username	controlpanel username
	*	@param	password	controlpanel password
	*	@return	false or customer object
	*
	*/
	function loginCustomer( $username , $password ) {
		$this -> constructURI( sprintf( "customer/login/" ) );
		
		$this -> setBasicAuth( $username , $password );
		
		$ret 		= $this -> call();
		
		$this -> setGet();
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , false );
		curl_setopt( $this -> c , CURLOPT_USERPWD , false );
		
		return $ret;
	}
	/**
	*	Account (Control Panel) functions
	*	@note	needs either a Account ID or a Account Username
	*/
	function getAccount( $idorname=false ) {
		if( !$idorname ) {
			$this -> error[]		= "No Account ID or username given.";
			return false;
		}
		$this -> constructURI( "account/" . $idorname );
		$this -> setGet();
		return $this -> call();
	}
	/**
	*	Product functions
	*	@note	do not provide an id to receive a list; products are currently filtered by HostingXS, but will be based on preset price lists
	*	@param	id	the product ID; contact HostingXS for a list of the ID's available to you OR false to get a complete list
	*/
	function getProduct( $id=false ) {
		$this -> constructURI( "products/".( $id ? $id : false ) );
		$this -> setGet();
		return $this -> call();
	}
	/**
	*	Get APIKEY
	*	@note	get the apikey of the current session, might be useful to connect with your own orders etc
	*	@return apikey
	*/
	public function getApikey() {
		return $this -> apikey;
	}
	/**
	*	=========================
	*	
	*	NOTE Supporting functions
	*/
	/**
	*	Automatically Auth with the webservice and try 5 times max on 410 HTTP errorcode
	*
	*	@return a credentials object with the reseller-customer information and the authkey
	*/
	public function auth( $sandbox=false ) {
		if( self::$attempts > 5 ) { 
			throw new Exception(__CLASS__ . ": too many attempts to connect to remote API server"); 
		}
		$this -> constructURI( sprintf( "auth/login/%s" , ($sandbox ? 1 : false) ));
		$this -> setBasicAuth( (int) $this -> un , (string) $this -> pw );
		$this -> auth				= json_decode( curl_exec( $this -> c ));
		if( $this -> error || !isset($this -> auth -> apikey) ) {
			return false;
		}
		$this -> apikey				= $this -> auth -> apikey;
		$this -> isreseller			= $this -> auth -> info -> isreseller;
		$this -> isaffiliate			= !$this -> isreseller;
		$this -> setGet();
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , false );
		curl_setopt( $this -> c , CURLOPT_USERPWD , false );
		self::$attempts++;
	}
	public function debug() {
		return curl_getinfo( $this -> c );
	}
	// BASIC AUTH
	private function setBasicAuth( $un , $pw ) {
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , CURLAUTH_BASIC );
		curl_setopt( $this -> c , CURLOPT_USERPWD , implode(":", array( $un , $pw )));
	}
	// SAVE
	private function setPut() {
		curl_setopt( $this -> c , CURLOPT_CUSTOMREQUEST , "PUT" );
	}
	// LOAD
	private function setGet() {
		curl_setopt( $this -> c , CURLOPT_HTTPGET , true );
	}
	// INSERT
	private function setPost() {
		curl_setopt( $this -> c , CURLOPT_POST , true );
	}
	// ERASE
	private function setDelete() {
		curl_setopt( $this -> c , CURLOPT_CUSTOMREQUEST , "DELETE" );
	}
	private function setPostVariables($vars=false) {
		if( !$vars || !is_array($vars) || !count($vars)) {
			return false;
		}
		$this -> setPost();
		curl_setopt( $this -> c , CURLOPT_POSTFIELDS , json_encode($vars) );
	}
	private function unSetPostVariables() {
		curl_setopt( $this -> c , CURLOPT_POSTFIELDS , NULL );
		$this -> setGet();
	}
	private function call() {
		
		$ret					= json_decode( curl_exec( $this -> c ));
		$debug					= $this -> debug();
		if( is_null($ret) && $debug['http_code'] == 410 ) {
			$this -> apikey			= false;
			$this -> auth();
			return $this -> call();
			
		}
		if( $debug['http_code'] == 201 ) {
			return true;
		} 
		elseif( is_null($ret)) { $ret = false; }
		elseif( isset($ret -> errno) && isset($ret -> errmsg) ) { 
			$this -> error			= $ret -> errmsg;
			return false; 		
		}
		return $ret;
	}
	private function constructURI( $command ) {
		if( isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			curl_setopt( $this -> c , CURLOPT_HTTPHEADER , array( sprintf( "X_FORWARDED_FOR: %s" , $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		}
		
		curl_setopt( $this -> c , CURLOPT_URL , $this -> url . $command . ($this -> apikey ? "?apikey=".$this -> apikey : "?ip=".$_SERVER['REMOTE_ADDR'] ));
		return true;
	}
	private function continueSession() {
		if( session_id() == "" ) {
			@session_start();
		}
		if( isset($_SESSION['hxsapikey']) ) {
			$this -> apikey 		= $_SESSION['hxsapikey'];
			$this -> auth			= $_SESSION['auth'];
		}
	}
	function __destruct() {
		if( $this -> apikey ) {
			// probably unnecessary but do it anyway
			if( session_id() == "" ) {
				@session_start();
			}
			$_SESSION['auth']	 	= $this -> auth;
			$_SESSION['hxsapikey']		= $this -> apikey;
		}
	}
} 



class hxs_customer {
	/**
	*	
	*	@return 	customer object for use in API v2
	*	@var 		seed 	customer object received from API or an array from forms or false to create the object
	*/
	public function __construct( $seed=false ) {
		if( $seed && $c = $this -> validateSeed( $seed ) ) {
			return $c;
		} else { return false; }
	}
	/**
	*	@note		validates seeded object from api or array from form
	*
	*/
	private function validateSeed( $seed ) {
		// validate seed as either object or array
		if( !is_object( $seed ) && is_array( $seed )) {
			$tmp		= new hxs_customer;
			foreach( $seed as $field => $value ) {
				$tmp -> $field = $value;
			}
			$seed		= $tmp;
		} elseif( !is_object( $seed )) {
			throw new Exception( "Values submitted are not an object nor an array, cannot seed customer object."); 
		}
		$fields			= array(
								"firstname"	=> array(
												"required",
												"title"		=> "first name",
												"validates"	=> "string",
								),
								"lastname"	=> array(
												"required",
												"title"		=> "last name",
												"validates"	=> "string",
								),
								"initials"	=> array(
												"required",
												"validates"	=> "string",
								),
								"address"	=> array(
												"required",
												"validates"	=> "string",
								),
								"postal"	=> array(
												"required",
												"title"		=> "postal code",
												"validates"	=> "string",
								),
								"city"	=> array(
												"required",
												"validates"	=> "string",
								),
								"country"	=> array(
												"required",
												"validates"	=> "string",
												"length"	=> 2,
								),
								"phone"	=> array(
												"required",
												"title"		=> "phone number",
												"validates"	=> "string",
								),
								"email"	=> array(
												"required",
												"title"		=> "e-mail address",
												"validates"	=> "email",
								),
								// username is checked seperately
								"un"	=> array(
												"title"		=> "username",
								),
								// password is checked seperately
								"pw"	=> array(
												"title"		=> "password",
								),
								// dob only necessary for private person (legal form 9)
								"dob"	=> array(
												"title"		=> "date of birth",
												"validates"	=> "date",
								),
								"gender"	=> array(
												"required",
								),
								"houseno"	=> array(
												"required",
												"title"		=> "house number",
												"validates"	=> "integer",
								),
								"legal"	=> array(
												"required",
												"title"		=> "legal form",
												"validates"	=> "integer",
								),
								"company"	=> array(
												"title"		=> "company name",
												"validates"	=> "string",
								),
								"vat"	=> array(
												"title"		=> "VAT number",
												"validates"	=> "vat",
								),
								"coc"	=> array(
												"title"		=> "chamber of commerce",
												"validates"	=> "string",
								),
								"housenoext"	=> array(
												"title"		=> "house number extension",
												"validates"	=> "string",
								),
								"invoice_to_ref" => array(
												"title"		=> "send invoice to reseller",
												"validates"	=> "boolean",
								),
								"autodebit" 	=> array(
												"title"		=> "enable auto debit",
												"validates"	=> "boolean",
								),
								"autodebit_name"	=> array(
												"title"		=> "bank account name",
												"validates"	=> "string",
								),
								"autodebit_city"	=> array(
												"title"		=> "bank account city",
												"validates"	=> "string",
								),
								"autodebit_account"	=> array(
												"title"		=> "bank account account",
												"validates"	=> "string",
								),
								"autodebit_bank" 	=> array(
												"title"		=> "bank account type",
												"validates"	=> "boolean",
								),
		);
		foreach( $fields as $fieldname => $opts ) {
			// field is required
			if( isset( $opts['required'] ) && !isset( $seed -> $fieldname )) {
				throw new Exception( sprintf( "%s is required." , isset($opts['title']) ? $opts['title'] : $fieldname ) );
			}
			// field is not set and not required
			if( !isset( $opts['required'] ) && (!isset( $seed -> $fieldname ) || $seed -> $fieldname == "" )) {
				continue;
			}
			if( isset( $seed -> $fieldname ) && isset( $opts['validates'] )) {
				switch( $opts['validates'] ) {
					case "string":
						if( !is_string( $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper text." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "integer":
						if( !is_int( $seed -> $fieldname ) && !( (int) $seed -> $fieldname == $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper number." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						$seed -> $fieldname		= (int) $seed -> $fieldname;
						break;
					case "date":
						if( $fieldname == "dob" && empty($seed -> $fieldname) && $seed -> legal != 9 ) {
							$seed -> $fieldname	= "0000-00-00";
							break;
						}
						$regex	= "/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/i";
						if( !preg_match( $regex , $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper date, use YYYY-MM-DD." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "phone":
						$regex	= "/^([0-9-.+ ]{5,})$/";
						if( !preg_match( $regex , $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as a proper phone number, either use an international format or a national one." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "un":
						if( !isset($seed -> customerid) && !isset( $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s not entered, which is required for new customers." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						$regex	= "/^[a-z]([-_a-z0-9]){2,31}$/";
						if( !isset($seed -> customerid) && !preg_match( $regex , $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper username, between 3 and 32 in length, start with a letter and may only contain lowercase letters, numbers and _ or -." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "pw":
						if( !isset($seed -> customerid) && !isset( $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s not entered, which is required for new customers." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "email":
						$regex = "/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i";
						if( !preg_match( $regex , $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper e-mail address." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "vat":
						$regex = "/^([a-z]{2})?[0-9a-z]+$/i";
						if( !preg_match( $regex , $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper international VAT number." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "boolean":
						if( isset($seed -> fieldname) && !is_bool((bool)$seed -> $fieldname ) && !is_int((int)$seed -> $fieldname )) {
							throw new Exception( sprintf( "%s not enabled or disabled." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						} elseif( !isset($seed -> $fieldname) ) {
							$seed -> $fieldname	= false;
						} else {
							$seed -> $fieldname	= (bool) $seed -> $fieldname;
						}
						break;
				}
			}
			if( isset($opts['length']) && strlen( $seed -> $fieldname) != $opts['length'] ) {
				throw new Exception( sprintf( "%s is of an incorrect length, should be %d." , isset($opts['title']) ? $opts['title'] : $fieldname , $opts['length']));
			}
			$this -> $fieldname 	= $seed -> $fieldname;
		}
		return $this;
	}
	static public function pwstrength($value) {
		$matchlc = "/[a-z]/";
		$matchuc = "/[A-Z]/";
		$matchdig = "/\d/";
		$matchother = "/[^0-9A-Za-z]/";
		$match2good = "/[^a-z].*[^a-z]/";
		$points = 0;
		if (preg_match($matchlc,$value)) {
			$points += 2;
			$m[1]	= true;
		}
		if (preg_match($matchuc,$value)) {
			$points += 2;
			$m[2]	= true;
		}
		if (preg_match($matchdig,$value)) {
			$points += 3;
			$m[3]	= true;
		}
		if (preg_match($matchother,$value)) {
			$points += 4;
			$m[4]	= true;
		}
		if ((isset($m[1]) || isset($m[2])) && isset($m[3]) ) {
			$points += 2;
			$m[5]	= true;
		}
		if ((isset($m[1]) || isset($m[2])) && isset($tm[4])) {
			$points += 2;
			$tm[6]	= true;
		}
		$score		= $points * strlen($value);
		if (!(preg_match($match2good,$value) && strlen($value) >= 6) && $score >= 42)
			$score = 41;
			
		if( $score > 41 )
			return true;
		return false;
	}
}

class hxsdomain {
        private $domregex               = "/(www\.)?(?<hostname>[a-zA-Z0-9\-]+)(\.(?<tld>[a-zA-Z]{2,6}))?(\.(?<tld2>[a-zA-Z]{2,6}))?/i";
        public $valid 			= false;

        function __construct( $domain ) {
                preg_match($this -> domregex,$domain,$m);
                if(!isset($m['hostname']) || $m['hostname'] == '') {
                        $this -> valid 	= false;
                        return false;
                }
                if( !isset($m['tld']) || $m['tld'] == "" ) {
                        $this -> tld    = "nl";
                } elseif( isset( $m['tld2']) ) {
                        $this -> tld    = $m['tld'].".".$m['tld2'];
                } else {
                        $this -> tld    = $m['tld'];
                }
                $this -> name           = $m['hostname'].".".$this -> tld;

                $this -> valid          = true;
        }
}
