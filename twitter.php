<?php

/** Module created by Mathew Taylor, 
http://mathewtaylor.co.uk - 14/08/2010 **/

require_once('twitteroauth/twitteroauth.php');
include_once('../config/config.inc.php');
include_once('../config/settings.inc.php');
include_once('../classes/Cookie.php');


class TwitterAbrahamWrapper{

	private $consumer_key;
	private $consumer_secret;
	private $connection;
	private static $instance;
	private $oauth_token;
	private $oauth_token_secret;
	private $cookie;
	public $connected;
	
	public $error = NULL;

	private function __construct()
	{
		$this->load_app_keys();
		$this->load_tokens();
		if($this->consumer_key && $this->consumer_secret)
		{
			$this->connect();
			$this->connected = TRUE;
			if($this->oauth_token && $this->oauth_token_secret){
				$this->verify_account();
			}
		}
		$this->cookie = new Cookie('ps');
	}
	
	public static function get_instance()
	{
		if(!self::$instance){
			self::$instance = new TwitterAbrahamWrapper();
		}
		return self::$instance;
	}
	
	private function authenticate_user($callback_url)
	{
		$this->connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret);
		$request_token = $this->connection->getRequestToken($callback_url);
		$token = $request_token['oauth_token'];
		
		//prestashop cookie
		$this->cookie->oauth_token = $token;
		$this->cookie->oauth_token_secret = $request_token['oauth_token_secret'];
		
		switch ($this->connection->http_code) {
			case 200:
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
				$this->cookie->oauth_token, $this->cookie->oauth_token_secret);

		/* Request access tokens from twitter */
		$access_token = $this->connection->getAccessToken($_REQUEST['oauth_verifier']);

		$this->cookie->oauth_token = $access_token['oauth_token'];
		$this->cookie->oauth_token_secret = $access_token['oauth_token_secret'];

		/* If HTTP response is 200 continue otherwise send to connect page to retry */
		if (200 == $this->connection->http_code) {
		  /* The user has been verified and the access tokens can be saved for future use */
		  $this->cookie->twitter_status = 'verified';
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
		$query = 'UPDATE `'._DB_PREFIX_.'twesta`
				SET `oauth_token` = "'. $this->cookie->oauth_token .'",
					`oauth_token_secret` = "'. $this->cookie->oauth_token_secret .'"';
		
		Db::getInstance()->Execute($query);
		//TODO: check update.
			return TRUE;
	}
	
	public function save_app_keys($consumer_key, $consumer_secret)
	{
		$this->consumer_key = $consumer_key;
		$this->consumer_secret = $consumer_secret;
	
		$query = 'INSERT INTO `'._DB_PREFIX_.'twesta`
					(consumer_key, consumer_secret)
					VALUES ("'.$this->consumer_key.'", "'.$this->consumer_secret.'")';
		
		Db::getInstance()->Execute($query);
		//TODO: check update.
		return TRUE;
	}
	
	private function load_app_keys()
	{
		$query = '
		SELECT `consumer_key`, `consumer_secret`
		FROM `'._DB_PREFIX_.'twesta` 
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
		FROM `'._DB_PREFIX_.'twesta` 
		';

		if(!$result = Db::getInstance()->ExecuteS($query)){
			$result = NULL;
		}else{
			foreach($result as $row){
				$this->oauth_token = $row['oauth_token'];
				$this->oauth_token_secret = $row['oauth_token_secret'];
			}
		}
	}
	
	public function verify_account(){
		$result = $this->connection->get('account/verify_credentials');
		if(isset($result->error)){
			$this->connected = FALSE;
			$this->connection = NULL;
			$this->error = $result->error;
			$this->disconnect();
		}
	}
	
	public function tweet($tweet)
	{
		$response = $this->connection->post('statuses/update', 
						array('status' => $tweet));
		print_r($response);
	}
	
	private function connect()
	{
		if($this->oauth_token && $this->oauth_token_secret){
			$this->connection = new TwitterOAuth($this->consumer_key, 
										$this->consumer_secret,
										$this->oauth_token, 
										$this->oauth_token_secret);
		}else{
			$this->connection = new TwitterOAuth($this->consumer_key, 
										$this->consumer_secret);
		}
	}
	
	public function disconnect(){
		$query = 'DELETE FROM`'._DB_PREFIX_.'twesta`';
		Db::getInstance()->Execute($query);
		return TRUE;
	}
	
	public function handle_connect($operation){
		switch($operation){
			case 'connect':
				$callback = "http://{$_SERVER["SERVER_NAME"]}{$_SERVER['REQUEST_URI']}&operation=callback";
				$status = $this->authenticate_user($callback);
				
				if($status == FALSE){
					$error = "Could not connect to Twitter Service";
					return $error;
				}
				break;

			case 'callback':
				$oauth_token = $this->cookie->oauth_token;
				$oauth_token_secret = $this->cookie->oauth_token_secret;
			
				if(isset($oauth_token) && isset($oauth_token_secret)){
					$result = $this->callback($oauth_token, $oauth_token_secret);
					if($result == TRUE){
						$this->connected = TRUE;
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