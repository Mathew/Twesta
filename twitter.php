<?php

/** Module created by Mathew Taylor, 
http://mathewtaylor.co.uk - 14/08/2010 **/

require_once('twitteroauth/twitteroauth.php');

class TwitterAbrahamWrapper{

	private $consumer_key;
	private $consumer_secret;
	private $connection;
	private $oauth_token;
	private $oauth_token_secret;
	public $connected;

	public function __construct()
	{
		$this->load_app_keys();
		$this->load_tokens();
        $this->connection = null;
	}
	
	private function authenticate_user($callback_url)
	{
		$this->connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret);
		$request_token = $this->connection->getRequestToken($callback_url);
		$token = $request_token['oauth_token'];
		
		$_SESSION['oauth_token'] = $token;
		$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
		switch ($this->connection->http_code) {
			case 200:
			echo "200";
				/* Build authorize URL and redirect user to Twitter. */
				$url = $this->connection->getAuthorizeURL($token);
				header('Location: ' . $url); 
			break;
			default:
			print_r($this->connection);
				/* Show notification if something went wrong. */
				return FALSE;
		}
	}
	
	private function callback()
	{
		/* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
		$this->connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret, 
				$_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

		/* Request access tokens from twitter */
		$access_token = $this->connection->getAccessToken($_REQUEST['oauth_verifier']);

		/* Save the access tokens. Normally these would be saved in a database for future use. */
		$_SESSION['access_token'] = $access_token;

		/* Remove no longer needed request tokens */
		unset($_SESSION['oauth_token']);
		unset($_SESSION['oauth_token_secret']);

		/* If HTTP response is 200 continue otherwise send to connect page to retry */
		if (200 == $this->connection->http_code) {
		  /* The user has been verified and the access tokens can be saved for future use */
		  $_SESSION['status'] = 'verified';
		  if(!$this->save_tokens()){	
			return FALSE;
		  }
			return TRUE;
		} else {
			echo "http error";
			print_r($this->connection->http_code);
		  /* Save HTTP status for error dialog on connnect page.*/
		  return FALSE;
		}
	}
	
	private function save_tokens()
	{
		$query = 'UPDATE `'._DB_PREFIX_.'twitter`
				SET `oauth_token` = "'.$_SESSION['access_token']['oauth_token'].'",
					`oauth_token_secret` = "'. $_SESSION['access_token']['oauth_token_secret'] .'"';
		Db::getInstance()->Execute($query);
		//TODO: check update.
			return TRUE;
	}
	
	public function save_app_keys($consumer_key, $consumer_secret)
	{
		$this->consumer_key = $consumer_key;
		$this->consumer_secret = $consumer_secret;
	
		$query = 'UPDATE `'._DB_PREFIX_.'twitter`
					SET `consumer_key` = "'. $this->consumer_key .'",
						`consumer_secret` = "'. $this->consumer_secret .'"';
	
		Db::getInstance()->Execute($query);
		//TODO: check update.
		return TRUE;
	}
	
	private function load_app_keys()
	{
		$query = '
		SELECT `consumer_key`, `consumer_secret`
		FROM `'._DB_PREFIX_.'twitter` 
		';

		if(!$result = Db::getInstance()->ExecuteS($query)){
			$result = NULL;
		}else{
			foreach($result as $row){
				$this->consumer_key = $row['consumer_key'];
				$this->consumer_secret = $row['consumer_secret'];
			}
		}
	}
	
	private function load_tokens()
	{
		$query = '
		SELECT *
		FROM `'._DB_PREFIX_.'twitter` 
		';

		if(!$result = Db::getInstance()->ExecuteS($query)){
			$result = NULL;
			$this->connected = FALSE;
		}else{
			foreach($result as $row){
				$this->oauth_token = $row['oauth_token'];
				$this->oauth_token_secret = $row['oauth_token_secret'];
			}
			if($this->oauth_token && $this->oauth_token_secret){
				$this->connected = TRUE;
				$this->connect();
			}
		}
	}
	
	public function tweet($tweet)
	{
		$this->connect();
		$response = $this->connection->post('statuses/update', array('status' => $tweet));
		print_r($response);
	}
	
	private function connect()
	{
		$this->connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret,
			$this->oauth_token, $this->oauth_token_secret);
	}
	
	private function disconnect()
	{}
	
	public function handle_connect($operation){
		switch($operation){
			case 'connect':
				$callback = "http://www.{$_SERVER["SERVER_NAME"]}{$_SERVER['REQUEST_URI']}&operation=callback";
				$status = $this->authenticate_user($callback);
				
				if($status == FALSE){
					$error = "Could not connect to Twitter Service";
					return $error;
				}
				break;

			case 'callback':
				$oauth_token = $_SESSION['oauth_token'];
				$oauth_token_secret = $_SESSION['oauth_token_secret'];
			   
				if(isset($oauth_token) && isset($oauth_token_secret)){
					$result = $this->callback($oauth_token, $oauth_token_secret);
					if($result == TRUE){
						$twitter->connected = TRUE;
					}
				}else{
					$error = 'Please retry your connection';
					echo "error: " . $error;
					return $error;
				}
				break;
				
			case 'disconnect':
				$this->disconnect();
				break;
		}     
	}
}

?>