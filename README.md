Communication via OAuth 1.0 protocol with idioma TC system in PHP
=================================================================

PHP library using authorization via OAuth 1.0 protocol for communication with idioma TC system.

First steps
===========

**First** of all, download full library for managing authorization via OAuth 1.0 protocol. If you want to have a little less work to do, see it at https://github.com/sylar32/OAuth-PHP.

**Second**, it is needed to set up a few paths and authorization variables. Use something like this:

    // you will get these informations after registration at idioma as developer
    $consumer_key = "Your_consumer_key";
    $consumer_secret = "$=_ssD1H+%LD-B=UAY|r5*Fv@v0SMGU7";
    
    $url_base = "https://tc6.idioma.com/"; // use "http://icom.tc6.idioma.com/" in case of using sandbox
    $url_callback = "http://www.example.com/path/to/authorization/callback/";
    $url_service = "oauth/tokenservice";
    $url_grant = "oauth/tokengrant";
    $url_access = "oauth/tokenservice";

**Third** step, authorize your site. See example below:

    // 1) we have no token, so we need to get REQUEST_TOKEN
    $oauth = new TC_OAuth($consumer_key, $consumer_secret);
    $oauth->setBaseUrl($url_base);
    
    try {
      $oauth->request($url_service, array("oauth_callback" => $url_callback), "POST");
      parse_str($oauth->get_body(), $token);
    } catch(iComException $ue) { }
    
    // 2) now we already have REQUEST_TOKEN, let use it
    echo '<a href="' . $url_base . $url_grant . '?oauth_token=' . urlencode($token['oauth_token']) . '">Continue</a>';
    
    // 3) we successfully passed authorization directly on TC and last part is get ACCESS_TOKEN
    $oauth->setToken($token['oauth_token'], $token['oauth_token_secret']);
  	try {
  		$oauth->request($url_access, null, "POST");
  		parse_str($oauth->get_body(), $accessToken);
  		
      // save $accessToken array, you must use it in every single request to TC system
      
      echo "Hooray, we are authorized :)";
  	} catch(iComException $ue) { }

**Last** thing has to be done. Now, you can call any of requests of iCom API. Please, see closely full documentation at https://devel.idioma.com/projects/public/wiki/UHTI_API.

    $icom = new TC_OAuth(consumer_key, $consumer_secret, $accessToken['oauth_token'], $accessToken['oauth_token_secret']);
    $icom->setBaseUrl($url_base);
    
    try {
      $icom->request("api/uhti/carts", array('name' => $name, 'sourceLanguage' => $source_language), "POST");
    } catch (iComException $ie) { }
    
    var_dump($icom->get_code());
    var_dump($icom->get_location());
    var_dump($icom->get_body());
    var_dump($icom->get_error());
