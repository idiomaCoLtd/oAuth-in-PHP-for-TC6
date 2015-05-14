<?php

class TC_OAuth {
	// OAuthSignatureMethod
	private $signatureMethod;
	// OAuthConsumer
	private $consumer;
	// OAuthToken
	private $token;
	// String - callback URL
	private $callback;
	// String - základní URL serveru
	private $base_url;
	// String - hlavička která se bude odesílat
	public $curl_header = NULL; //informacni promenna
  // String - postdata která se budou odesílat
	public $curl_postdata = NULL; //informacni promenna
  // Array - cURL informace které byly odeslány na server
	public $curl_info = NULL; //informacni prommena
	// Boolean - if set TRUE prints debug messages
	private $debug = FALSE;
	
	/**
	 * Creates object using consumer and access keys.
	 * @param  string  consumer key
	 * @param  string  app secret
	 * @param  string  optional access token
	 * @param  string  optinal access token secret
	 * @throws UhtiExceptionException when CURL extension is not loaded
	 * @throws TwitterAuthException to signalize individual authorization steps
	 */
	public function __construct($consumerKey = NULL, $consumerSecret = NULL, $tokenKey = NULL, $tokenSecret = NULL)
	{
		if (!extension_loaded('curl')) {
			throw new iComException('PHP extension cURL is not loaded.');
		}
		$this->signatureMethod = new OAuthSignatureMethod_HMAC_SHA1();
		$this->consumer = new OAuthConsumer($consumerKey, $consumerSecret);
		if(!is_null($tokenKey)){
			$this->token = $this->setToken($tokenKey, $tokenSecret);
		} else {
			$this->token = NULL;
		}
	}
    /**
     * Get Consumer token
     * @return OAuthConsumer
     */
	public function getConsumer(){
		return $this->consumer;
	}
    /**
     * Get Request/Acces token
     * @return OAuthConsumer
     */
	public function getToken(){
		return $this->token;
	}
    /**
     * Set Request/Acces token
     * @param String $tokenKey
     * @param String $tokenSecret
     * @return OAuthConsumer
     */
	public function setToken($tokenKey, $tokenSecret){
    $this->token = new OAuthToken($tokenKey, $tokenSecret);
		$this->debug_echo('Token was changed.<br>');
		return $this->token;
	}
    /**
     * Set callback URL
     * @param String $callback
     */
	public function setCallback($callback){
		$this->callback = $callback;
        $this->debug_echo('Callback url was set to '.$this->callback.'<br>');
	}
    /**
     * Set base URL to access API server
     * @param String $base_url
     */
	public function setBaseUrl($base_url){
		$this->base_url = $base_url;
        $this->debug_echo('Base url was set to '.$this->base_url.'<br>');
	}
   /**
    * Set debug enviroment
    * if set TRUE will print debugging messages
    * @param String (boolean) $debug
    */
	public function setDebug($debug){
		if(is_bool($debug)){
			$this->debug = $debug;
		}
	}
    /**
     * toString methods
     * @return String
     */
	public function __toString() {
      return __CLASS__ . ": [{Consumer: $this->consumer}, {Token: $this->token}, {callback: $this->callback}, {base url: $this->base_url}]\n";
  }
    /**
     * Process HTTP request.
     * @param  string  URL or twitter command
     * @param  string  HTTP method
     * @param  array   data
     * @return mixed
     * @throws UhtiException
     */
    public function request($request_url, $data = NULL, $method = 'POST')
    {
        if (!strpos($request_url, '://')) { //pokud neobsahuje '://' připoj před to base_url
            if(isset($this->base_url)){ //pokud je nastavena base_url
                $request_url = $this->base_url . $request_url;
            }else {
                throw new iComException('URL may be incomplete: ' . $request_url);
            }
        }


        if(!is_null($this->token)){
            $this->debug_echo('Token is NOT NULL => used to request to obtain a "oauth access token" or call api method<br>');
            if($method == "POST" || $method == "PUT")
                    $request = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $request_url);
            else
                    $request = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $request_url, $data);
            $request->sign_request($this->signatureMethod, $this->consumer, $this->token);
        }
        else {
            $this->debug_echo('Token is NULL => used to request to obtain a "oauth request token"<br>');
            if($method == "POST" || $method == "PUT"){
                $request = OAuthRequest::from_consumer_and_token($this->consumer, NULL, $method, $request_url);
            }
            else {
                $request = OAuthRequest::from_consumer_and_token($this->consumer, NULL, $method, $request_url, $data);
            }
            $request->sign_request($this->signatureMethod, $this->consumer, NULL);
        }

        $curl = curl_init();

        /* Pokud jsou data NULL, pošlu NULL */
        if(is_null($data) || empty($data)){
            $data_to_send = null;
        }else {
            /* Převod dat z pole do formátu JSON */
            $data_to_send = json_encode($data);
        }


        /* Nastavení hlaviček realm, Content-type, Accept a Content-Length */
        if($method == "POST" || $method == "PUT"){
            /* nastaveni callbacku do hlavicky requestu */
            if(isset($this->callback) && !empty($this->callback) && !is_null($this->callback)){
                $headers = array($request->to_header("iCom"), 'Content-Type: application/json', 'Accept: application/json', 'oauth_callback='.urlencode($this->callback), 'Content-Length: ' . strlen($data_to_send));
            }
            else {
                $headers = array($request->to_header("iCom"), 'Content-Type: application/json', 'Accept: application/json', 'Content-Length: ' . strlen($data_to_send));
            }
        }
        else {
            $headers = array($request->to_header("iCom"), 'Content-Type: application/json', 'Accept: application/json');
        }

        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // no echo, just return result

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_URL, $request_url);
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_to_send);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }else if($method === 'DELETE'){
            curl_setopt($curl, CURLOPT_URL, $request->get_normalized_http_url());
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        }else if($method === 'PUT'){
            curl_setopt($curl, CURLOPT_URL, $request_url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_to_send);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }else {
            curl_setopt($curl, CURLOPT_URL, $request->get_normalized_http_url());
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        /* Ulozeni dat do privatnich promennych */
        $this->curl_header = $headers;
        if($method == "POST")
            $this->curl_postdata = $data_to_send;
        else
            $this->curl_postdata = $request->to_postdata();

        $result = curl_exec($curl);
        $this->curl_info['raw_result'] = $result;
        $headers_array = $this->parse_headers($result);
        $this->curl_info['location'] = $headers_array['Location'];
        $this->curl_info['header'] = $headers_array['http_code'];
        $this->curl_info['url'] = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        $this->curl_info['header_out'] = curl_getinfo($curl, CURLINFO_HEADER_OUT);
        $this->curl_info['http_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->curl_info['content_type'] = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        if (curl_errno($curl)) {
            throw new iComException('cUrl error ' . curl_errno($curl) . ' - ' . curl_error($curl));
        }

            /*if (strpos($this->curl_info['content_type'], 'xml')) {
                $this->debug_echo('Result is XML<br>');
                $payload = @simplexml_load_string($result); // intentionally @
		} else*/if (strpos($this->curl_info['content_type'], 'json')) {

            $this->debug_echo('Result is JSON<br>');
			$this->curl_info['body'] = @json_decode($headers_array['Body']); // intentionally @

  		} else {
              $this->debug_echo('Result is RAW<br>');
  			$this->curl_info['body'] = $headers_array['Body'];
  		}

  		$this->debug_echo('HTTP CODE = ' . $this->curl_info['http_code'] . '<br><br>');
  		if ($this->curl_info['http_code'] >= 400) {
  		  //if (is_string($this->curl_info['body'])) {
          $this->curl_info['error'] = (!empty($this->curl_info['body']) ? (is_string($this->curl_info['body']) ? str_replace("Error: HttpError:", "", $this->curl_info['body']) : $this->curl_info['body']->responseStatus->message) : "Oops ... error. Please, try it again or later");
          throw new iComException($this->curl_info['error'], $this->curl_info['http_code'], $request_url);
        //}
  		}
      curl_close($curl);
    }

    public function get_header() {
        return $this->curl_info['header'];
    }

    public function get_code() {
        return $this->curl_info['http_code'];
    }

    public function get_location() {
        return $this->curl_info['location'];
    }

    public function get_body() {
      return $this->curl_info['body'];
    }

    public function get_error() {
      return $this->curl_info['error'];
    }
	private function parse_headers($headerContent) {
    $headerContent = str_replace("HTTP/1.1 100 Continue\r\n\r\n", "", $headerContent);

    $headers = array('Body' => '');
    $arrRequests = explode("\r\n\r\n", $headerContent);

    //for ($index = 0; $index < count($arrRequests) - 1; $index++) {
      foreach (explode("\r\n", $arrRequests[0]) as $i => $line) {
        if ($i === 0) {
          $headers['http_code'] = $line;
        } else {
          list ($key, $value) = explode(': ', $line);
          $headers[$key] = $value;
    } } //}
    if ($arrRequests[1]) $headers['Body'] = $arrRequests[1];

    return $headers;
  }
    /**
     * Print message only if debug property is TRUE
     * @param String $msg
     */
    private function debug_echo($msg){
        if($this->debug) echo($msg);
    }
    /**
     * Dump variables only if debug property is TRUE
     * @param String $var
     */
	private function debug_dump($var){
		if($this->debug) var_dump($var);
	}
}
/**
 * An exception generated by iCom.
 */
class iComException extends Exception
{
		// Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, $request_url = "", Exception $previous = null) {
        $token = get_option('icom_token');
        if (!empty($token)) {
				  echo '<span class="icom-error icom-exception">
                  iCom plugin WARNING<br><br>' .
                  ($code > 0 ? 'CODE: ' . $code . '<br>' : '') .
                  (!empty($request_url) ? 'REQUEST: ' . $request_url . '<br>' : '') .
                  'MESSAGE: ' . $message .
               '</span>';
				}

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
?>
