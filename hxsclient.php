<?PHP
/**

	HXS API (v2) Webservices Client

	The HostingXS API Client is an open source library to implement products & services on own interfaces.

		W-Information:	http://api.hostingxs.nl
		E-Contact:	info@hostingxs.nl
		E-Support:	support@hostingxs.nl

	i)	Please note that this client requires a valid reseller login ID and password
	i)	Implementation of this API client without adhering to the requirements on api.hostingxs.nl is at your own risk
	i)	Costs forthcoming from mis-/abusing this client library are passed on to the client implementer

*/
class hxsclient {
	// URL is fixed; we highly recommend leaving it as is
	private $url		= "https://api.hostingxs.nl/v2/";
	private $apikey		= false;
	public $error		= false;
	
	function __construct( $un , $pw ) {
		$this -> continueSession();
		$this -> c			= curl_init();
		curl_setopt( $this -> c , CURLOPT_RETURNTRANSFER , true );
		if( !$this -> apikey ) {
			$this -> auth( $un , $pw );
		}
	}
	function checkDomain( $dom=false ) {
		return $this -> checkDomains( array( $dom ) );
	}
	function checkDomains( $doms=false ) {
		if( !$doms || !is_array( $doms ) || !count( $doms ) ) {
			# this is not possible
			$this -> error[]		= "No domains or no array of domains given.";
			return false;
		}
		$this -> constructURI( "domain/".implode(",",$doms) );
		curl_setopt( $this -> c , CURLOPT_HTTPGET , true );
		return json_decode(curl_exec( $this -> c ));
	}
	private function constructURI( $command ) {
		curl_setopt( $this -> c , CURLOPT_URL , $this -> url . $command . ($this -> apikey ? "?apikey=".$this -> apikey : "?ip=".$_SERVER['REMOTE_ADDR'] ));
		return true;
	}
	public function auth( $resellerid , $resellerpass ) {
		$this -> constructURI( "auth/login/" );
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , CURLAUTH_BASIC );
		curl_setopt( $this -> c , CURLOPT_HTTPGET , true );
		curl_setopt( $this -> c , CURLOPT_USERPWD , implode(":", array( (int) $resellerid , (string) $resellerpass )));
		$this -> auth		= json_decode(curl_exec( $this -> c ));
		if( $this -> error ) {
			return false;
		}
		$this -> apikey		= $this -> auth -> hash;
		
		curl_setopt( $this -> c , CURLOPT_HTTPAUTH , false );
		curl_setopt( $this -> c , CURLOPT_USERPWD , false );
	}
	private function continueSession() {
		@session_start();
		if(isset($_SESSION['hxsapikey'])) {
			$this -> apikey 		= $_SESSION['hxsapikey'];
		}
	}
	function __destruct() {
		if( $this -> apikey ) {
			@session_start();
			$_SESSION['hxsapikey']		= $this -> apikey;
		}
	}
} 