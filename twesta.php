<?php

/** Module created by Mathew Taylor, http://mathewtaylor.co.uk - 14/08/2010 **/

require_once('../modules/twitterupdate/twitter.php');

class Twesta extends Module{

	public function __construct()
	{	
		$this->name = 'twesta';
		$this->tab = 'Tools';
		$this->version = 0.1;
		
		parent::__construct();
		
		$this->page = basename(__FILE__, '.php');
		$this->displayName = $this->l('Twesta');
		$this->description = $this->l('Allows you to push an update to Twitter,
									storing the message in the database to be
									displayed in a block.');
	}
	
	public function install()
	{									
		if (parent::install() == False)
			return False;
 	 	return Db::getInstance()->Execute('CREATE TABLE `'._DB_PREFIX_.'twesta`(
												`oauth_token` char(50),
												`oauth_token_secret` char(50),
												`consumer_key` char(50), 
												`consumer_secret` char(50),
												`message` char(140)
											)');								
	}
	
	public function uninstall()
	{
		if(parent::uninstall())
			return False;
			
		$query = '
			DROP `'._DB_PREFIX_.'twesta`
		';
		return Db::getInstance()->Execute($query);
	}
	
	public function getContent()
	{
		$twitter = new TwitterAbrahamWrapper();
	
		if(isset($_GET['operation'])){
			$operation = $_GET['operation'];
            $error = $twitter->handle_connect($operation);
			if(is_string($error)){
				$this->_display_connect_form();
				$this->_html .= $error;
			}else{
				$this->_display_tweet_form();
			}
        }else{
			/* display the module name */
			$this->_html = '<h2'.$this->displayName.'</h2>';
			$errors = '';
			
			if(isset($_POST['connect']) && isset($_POST['consumer_key'])
				&& isset($_POST['consumer_secret'])){
				
				$consumer_key = $_POST['consumer_key'];
				$consumer_secret = $_POST['consumer_secret'];
				$twitter->save_app_keys($consumer_key, $consumer_secret);
				//save the above two in the database and begin connect.
				$twitter->handle_connect('connect');
			}
			
			if(isset($_POST['tweet'])){
				if(isset($_POST['message'])){
					$message = $_POST['message'];
					$twitter->tweet($message);
				}else{
					$error = "Please specify a message";
				}
			}
			
			if($twitter->connected){
				$this->_display_tweet_form();
			}else{
				$this->_display_connect_form();
			}
			return $this->_html;
		}
	}
	
	private function _display_connect_form()
	{
		$this->_html .= '
		<form method="post" action="'.$_SERVER['REQUEST_URI'].'" enctype="multipart/form-data">
			<fieldset style="width: 900px;">
				<legend><img src="'.$this->_path.'logo.gif" alt="" title="" /> '.$this->displayName.'</legend>
				<label>'.$this->l('App Consumer Key').'</label>
				<div class="margin-form">
					<input type="text" name="consumer_key"/>
				</div>
				
				<label>'.$this->l('App Consumer Secret').'</label>
				<div class-"margin-form">
					<input type="text" name="consumer_secret"/>
				</div>
				
				<div class="clear pspace"></div>
				<div class="margin-form clear"><input type="submit" name="connect" value="'.$this->l('Connect').'" class="button" /></div>
			</fieldset>
		</form>';
	}
	
	private function _display_tweet_form()
	{
		$this->_html .= '
		<form method="post" action="'.$_SERVER['REQUEST_URI'].'" enctype="multipart/form-data">
			<fieldset style="width: 900px;">
				<legend><img src="'.$this->_path.'logo.gif" alt="" title="" /> '.$this->displayName.'</legend>
				
				<label>'.$this->l('Message').'<label>
				<div class="margin-form">
					<input type="text" name="message"/>
				</div>
				
				<div class="clear pspace"></div>
				<div class="margin-form clear">
					<a href="'. $_SERVER['REQUEST_URI'] .'&operation=disconnect">Disconnect</a>
					<input type="submit" name="tweet" value="'.$this->l('Tweet').'" class="button" />
				</div>
			</fieldset>
		</form>';
	}

	public function tweet($message){
		$twitter = new Twitter();
		if($twitter->connect()){
			if($twitter->tweet($message)){
				echo "tweeted successfully";
			}else{
				echo "an error occurred tweeting";
			}
		}else{
			echo "Error creating twitter connection";
		}
	}

}

?>