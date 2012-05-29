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
	@date		22 Mai 2012
*/
class hxsclient {
	// URL is fixed; we highly recommend leaving it as is
	private $url		= "https://api.hostingxs.nl/v2/";
	private $apikey		= false;
	public $error		= false;
	static private $attempts	= 0;
	
	function __construct( $un , $pw , $sandbox=false ) {
		$this -> continueSession();
		$this -> c			= curl_init();
		curl_setopt( $this -> c , CURLOPT_RETURNTRANSFER , true );
		curl_setopt( $this -> c , CURLOPT_HTTPHEADER , array( "Content-Type: application/json" ));
		$this -> un			= $un;
		$this -> pw			= $pw;
		if( !$this -> apikey ) {
			$this -> auth( $sandbox );
		}
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
							"customer"	=> $customer
		);
		curl_setopt( $this -> c , CURLOPT_POSTFIELDS , json_encode($post) );
		$ret				= $this -> call();
		$this -> setGet();
		return $ret;
	}
	/**
		Domain checking
	*/
	function checkDomain( $dom=false ) {
		return array_shift($this -> checkDomains( array( $dom ) ));
	}
	function checkDomains( $doms=false ) {
		if( !$doms || !is_array( $doms ) || !count( $doms ) ) {
			# this is not possible
			$this -> error[]		= "No domains or no array of domains given.";
			return false;
		}
		$this -> constructURI( "domain/".implode(",",$doms) );
		$this -> setGet();
		return $this -> call();
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
	*	@param date		the cancellation date in YYYY-MM-DD format; must be at least 1 month before expiry date or after expiry date of domain (use domainState to check)
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
	*/
	function getProduct( $id=false ) {
		$this -> constructURI( "product/".( $id ? (int) $id : false ) );
		$this -> setGet();
		return $this -> call();
	}
	/**
		Supporting functions
	*/
	public function auth( $sandbox=false ) {
		if( self::$attempts > 5 ) { exit(__CLASS__ . ": too many attempts to connect to remote API server"); }
		$this -> constructURI( sprintf( "auth/login/%s" , ($sandbox ? 1 : false) ));
#		$this -> constructURI( "auth/login/?ip=".$_SERVER['REMOTE_ADDR'] );
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , CURLAUTH_BASIC );
#		$this -> setGet();
		curl_setopt( $this -> c , CURLOPT_USERPWD , implode(":", array( (int) $this -> un , (string) $this -> pw )));
		$this -> auth				= json_decode( curl_exec( $this -> c ));
		if( $this -> error ) {
			return false;
		}
		$this -> apikey		= $this -> auth -> apikey;
		$this -> setGet();
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , false );
		curl_setopt( $this -> c , CURLOPT_USERPWD , false );
		self::$attempts++;
	}
	public function debug() {
		return curl_getinfo( $this -> c );
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
	private function call() {
		
		$ret					= json_decode( curl_exec( $this -> c ));
		$debug					= $this -> debug();
		if( is_null($ret) && $debug['http_code'] == 410 ) {
			$this -> apikey			= false;
			$this -> auth();
			return $this -> call();
			
		}
		if( is_null($ret)) { $ret = false; }

		return $ret;
	}
	private function constructURI( $command ) {
		curl_setopt( $this -> c , CURLOPT_URL , $this -> url . $command . ($this -> apikey ? "?apikey=".$this -> apikey : "?ip=".$_SERVER['REMOTE_ADDR'] ));
		return true;
	}
	private function continueSession() {
		@session_start();
		if( isset($_SESSION['hxsapikey']) ) {
			$this -> apikey 		= $_SESSION['hxsapikey'];
		}
	}
	function __destruct() {
		if( $this -> apikey ) {
			// probably unnecessary but do it anyway
			@session_start();
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
												"validates"	=> "phone",
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
		);
		foreach( $fields as $fieldname => $opts ) {
			// field is required
			if( isset( $opts['required'] ) && !isset( $seed -> $fieldname )) {
				throw new Exception( sprintf( "%s is required." , isset($opts['title']) ? $opts['title'] : $fieldname ) );
			}
			// field is not set and not required
			if( !isset( $opts['required'] ) && !isset( $seed -> $fieldname )) {
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
						if( $fieldname == "dob" && !isset($seed -> $fieldname) && $seed -> legal != 9 ) { continue; }
						$regex	= "/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/i";
						if( !preg_match( $regex , $seed -> $fieldname )) {
							throw new Exception( sprintf( "%s did not validate as proper date, use YYYY-MM-DD." , isset($opts['title']) ? $opts['title'] : $fieldname ));
						}
						break;
					case "phone":
						$regex	= "/^((\+|00)[0-9]{2})([0-9-\. ]{5,})?$/i";
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
						if( !is_bool($seed -> fieldname ) && !is_int($seed -> $fieldname ) ) {
							throw new Exception( sprintf( "%s not enabled or disabled." , isset($opts['title']) ? $opts['title'] : $fieldname ));
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
}
