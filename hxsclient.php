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
	function domainState( $dom=false , $customerid=false ) {
		
		$this -> constructURI( sprintf("domain/status/%s/%d" , $dom , $customerid ));
		$this -> setGet();

		return $this -> call();
	}
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
		$this -> constructURI( sprintf( "auth/login/%s?ip=%s" , ($sandbox ? 1 : 0) , $_SERVER['REMOTE_ADDR'] ));
#		$this -> constructURI( "auth/login/?ip=".$_SERVER['REMOTE_ADDR'] );
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , CURLAUTH_BASIC );
		$this -> setGet();
		curl_setopt( $this -> c , CURLOPT_USERPWD , implode(":", array( (int) $this -> un , (string) $this -> pw )));
		$this -> auth				= json_decode( curl_exec( $this -> c ));
		if( $this -> error ) {
			return false;
		}
		$this -> apikey		= $this -> auth -> apikey;
		
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , false );
		curl_setopt( $this -> c , CURLOPT_USERPWD , false );
		self::$attempts++;
	}
	public function debug() {
		return curl_getinfo( $this -> c );
	}
	private function setGet() {
		curl_setopt( $this -> c , CURLOPT_CUSTOMREQUEST , null );
		curl_setopt( $this -> c , CURLOPT_HTTPGET , true );
	}
	private function setPost() {
		curl_setopt( $this -> c , CURLOPT_CUSTOMREQUEST , null );
		curl_setopt( $this -> c , CURLOPT_POST , true );
	}
	private function setDelete() {
		curl_setopt( $this -> c , CURLOPT_CUSTOMREQUEST , "DELETE" );
	}
	private function call() {
		
		$ret					= json_decode( curl_exec( $this -> c ));
		$debug					= $this -> debug();
		if( is_null($ret) && $debug['http_code'] == 410 ) {
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