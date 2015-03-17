<?php
/*
*	Pinterest Private API Class
*	Twitter: @CodesStuff
*	Github: https://github.com/pilfer
*	
*	Disclaimer: I'm not responsible for how you use this. Don't be a dick and spam.
*	This file hasn't been updated in several months, so bugs may exist.
*/

class Pinterest {
	
	//Debug bool
	public $isDebug = true;
	
	/*
	* Pinterest Application Credentials
	*/
	public $baseUrl = "https://api.pinterest.com/v3";
	public $clientId = "1431602";
	public $clientSecret = "492124fd20e80e0f678f7a03344875f9b6234e2b";

	
	/*
	* Account-specific Variables
	*/
	public $username = NULL;
	public $password = NULL;
	public $email = NULL;
	public $accessToken = NULL;
	public $installId = NULL;
	
	/*
	* Pinterest Application Client Variables
	*/
	public $userAgent = "Pinterest for Android/2.6.1 (d2att; 4.4.2)";
	public $headers = NULL;
	public $proxy = NULL;
	
	//Stores all the Pinterest requests in a neat little object
	public $requests = NULL;
	
	
	/*
	* Class Constructor
	*/
	public function __construct(){

		//Generate the installId for this object
		$this->installId = $this->generateInstallId();
		//Set HTTP POST Headers
		$this->headers = array(
			"Content-Type: application/x-www-form-urlencoded",
			"Host: api.pinterest.com",
			"Connection: Keep-Alive",
			"User-Agent: " . $this->userAgent,
			"Accept-Encoding: *",
			"X-Pinterest-InstallId: " . $this->installId,
			"Accept-Language: en_US",
			"User-Agent: " . $this->userAgent
		);
	}
	
	/*
	* Silly debug string function - ghetto as fuck. Never do this.
	*/
	public function pdebug($str){
		if($this->isDebug == true){
			echo $str;
		}
	}
	
	/*
	* Load the Pinterest requests configuration
	*/
	public function loadConfig($configFile){
		if(file_exists($configFile)){
			$tmp = json_decode(file_get_contents($configFile));
			if($tmp){
				if(isset($tmp->requests)){
					$this->requests = $tmp->requests;
				}else{
					$this->pdebug("Requests object within config file doesn't exist.");
				}
			}else{
				$this->pdebug("Unable to load config file - it's not in the JSON format.");
			}
		}
	}
	
	/*
	* Generate an installId for the headers. This has to be random but it's specific to each device.
	* In the application, they generate a random UUID4, convert it to a hex string, then remove a character. No idea why.
	*/
	public function generateInstallId(){
		return substr(md5(mt_rand(0,0xffffff)),0,31);
	}
	
	/*
	* Wrapper for current Unix timestamp
	* Example: 1403177216
	*/
	public function generateTimestamp(){
		return time();
	}
	
	/*
	* Check to see when the access token for this account expires
	*/
	public function getTokenExpires(){
		$deflated = explode(":",base64_decode($this->accessToken));
		$tokenExpires = explode("|",$deflated[2]);
		return $tokenExpires[1];
	}
	
	/*
	* Check to see whether or not this token *should* be valid
	* Note: Pinterest doesn't really care if you use an old token
	*/
	public function checkTokenValidity(){
		$tokenExpires = $this->getTokenExpires;
		$now = $this->generateTimestamp();
		if($now > $tokenExpires){
			return true;
		}else{
			return false;
		}
	}
	
	/*
	* Basic HTTP request method
	*/
	public function request($method, $url, $headers = NULL, $data = NULL){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		if(($method != "get") || ($method == "GET")){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}
		if(($method == "post") || ($method == "POST")){ curl_setopt($ch, CURLOPT_POST, true); }
		if($data != NULL){ curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));}
		if($headers != NULL){ curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); }
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if(($this->proxy != '') || (!empty($this->proxy)) || ($this->proxy != NULL)){ curl_setopt($ch, CURLOPT_PROXY, $this->proxy);}
		$result = curl_exec($ch);
		
		//var_dump(curl_getinfo($ch));
		
		curl_close($ch);
		if($result){
			return $result;
		}else{
			return false;
		}
	}
	
	/*
	* Sign an API request
	* Note: It's only required for /login, /register, and a couple others.
	*/
	public function sign($method, $url, $timestamp, $data = NULL){
		if(!isset($data['client_id'])){
			$data['client_id'] = $this->clientId;
		}
		$data['timestamp'] = $timestamp;
		ksort($data);
		$data = array_filter($data);
		$url = urlencode($url);
		$clean_data = array();
		foreach($data as $key => $val){
			$clean_data[] = $key . "=".urlencode($val);
		}
		$clean_data = implode("&",$clean_data);
		$str = sprintf("%s&%s" . "&%s",$method,$url, $clean_data);
		$sig = hash_hmac('sha256', $str, $this->clientSecret, false);
		return $sig;
	}
	
	/*
	* Pinterest API method
	*/
	public function api($method, $url, $data = NULL){
		$response = $this->request($method, $this->baseUrl . $url, $this->headers, $data);
		if($response){
			$json_response = json_decode($response);
			if($json_response){
				return $json_response;
			}else{
				pdebug("The response from $url wasn't in a JSON format.");
				return false;
			}
		}else{
			return false;
		}
	}
	
	/*
	* Login to a Pinterest account
	*/
	public function login($username, $password){
		$ts_now = $this->generateTimestamp();
		$data = array(
			"password" => $password,
			"username_or_email" => $username
		);
		$signature = $this->sign("POST", $this->baseUrl . "/login/", $ts_now, $data);
		$query_string = array(
			"client_id" => $this->clientId,
			"timestamp" => $ts_now,
			"oauth_signature" => $signature
		);
		$url = "/login/?" . http_build_query($query_string);
		$response = $this->api("POST", $url, $data);
		if($response->status != "success"){
			$this->pdebug($response->message);
			return false;
		}else{
			if(isset($response->data->access_token)){
				$this->accessToken = $response->data->access_token;
				return true;
			}else{
				return false;
			}
		}
	}
	
	/*
	* Get all information on the current Pinterest user
	*/
	public function getMe(){
		$query_string = array(
			"access_token" => $this->accessToken,
			"fields" => "user"
		);
		$url = "/users/me/?" . http_build_query($query_string);
		$response = $this->api("GET", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Search for pins containing keyword
	*/
	public function searchPins($keyword, $bookmark = NULL){
		$query_string = array(
			"access_token" => $this->accessToken,
			"query" => $keyword,
			"page_size" => "50",
			"fields" => "pin.images[236x,736x],pin.id,pin.description,pin.image_medium_url,pin.image_medium_size_pixels,pin.created_at,pin.like_count,pin.repin_count,board.id,board.name,board.category,board.created_at,board.layout,board.image_thumbnail_url,user.id,user.username,user.first_name,user.last_name,user.full_name,user.gender,user.image_medium_url,pin.liked_by_me,pin.dominant_color,pin.rich_summary(),pin.embed(),pin.promoter(),pin.recommendation_reason,pin.board(),pin.pinner(),pin.via_pinner(),pin.place_summary()"
		);
		if($bookmark != NULL){
			$query_string['bookmark'] = $bookmark;
		}
		$url = "/search/pins/?" . http_build_query($query_string);
		$response = $this->api("GET", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Search for Pinterest boards
	* layout = default or places
	*/
	public function searchBoards($keyword, $layout, $bookmark = NULL){
		$query_string = array(
			"access_token" => $this->accessToken,
			"query" => $keyword,
			"layout" => $layout,
			"page_size" => "50",
			"fields" => "board.id,board.name,board.category,board.created_at,board.layout,board.image_cover_url,board.images[90x90],board.collaborated_by_me,board.is_collaborative,board.followed_by_me,board.privacy,board.owner(),board.pin_count",
		);
		if($bookmark != NULL){
			$query_string['bookmark'] = $bookmark;
		}
		$url = "/search/boards/?" . http_build_query($query_string);
		$response = $this->api("GET", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Search for Pinterest users
	*/
	public function searchUsers($keyword, $bookmark = NULL){
		$query_string = array(
			"access_token" => $this->accessToken,
			"query" => $keyword,
			"page_size" => "50",
			"fields" => "user.id,user.username,user.first_name,user.last_name,user.full_name,user.gender,user.image_medium_url,user.website_url,user.domain_verified,user.location,user.explicitly_followed_by_me,user.implicitly_followed_by_me,user.blocked_by_me"
		);
		if($bookmark != NULL){
			$query_string['bookmark'] = $bookmark;
		}
		$url = "/search/users/?" . http_build_query($query_string);
		$response = $this->api("GET", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Follow a Pinterest User
	*/
	public function followUser($userId){
		$query_string = array(
			"access_token" => $this->accessToken
		);
		$url = "/users/" . $userId . "/follow/?" . http_build_query($query_string);
		$response = $this->api("PUT", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Unollow a Pinterest User
	*/
	public function unFollowUser($userId){
		$query_string = array(
			"access_token" => $this->accessToken
		);
		$url = "/users/" . $userId . "/follow/?" . http_build_query($query_string);
		$response = $this->api("DELETE", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Follow a Pinterest board
	*/
	public function followBoard($boardId){
		$query_string = array(
			"access_token" => $this->accessToken
		);
		$url = "/boards/" . $boardId . "/follow/?" . http_build_query($query_string);
		$response = $this->api("PUT", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Unfollow a Pinterest board
	*/
	public function unFollowBoard($boardId){
		$query_string = array(
			"access_token" => $this->accessToken
		);
		$url = "/boards/" . $boardId . "/follow/?" . http_build_query($query_string);
		$response = $this->api("DELETE", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Get the pins this user has liked
	*/
	public function getLikedPins($userId, $bookmark = NULL){
		$query_string = array(
			"access_token" => $this->accessToken,
			"page_size" => "50",
			"fields" => "pin.images[236x,736x],pin.id,pin.description,pin.image_medium_url,pin.image_medium_size_pixels,pin.created_at,pin.like_count,pin.repin_count,board.id,board.name,board.category,board.created_at,board.layout,board.image_thumbnail_url,user.id,user.username,user.first_name,user.last_name,user.full_name,user.gender,user.image_medium_url,pin.liked_by_me,pin.dominant_color,pin.rich_summary(),pin.embed(),pin.promoter(),pin.recommendation_reason,pin.board(),pin.pinner(),pin.via_pinner(),pin.place_summary()"
		);
		if($bookmark != NULL){
			$query_string['bookmark'] = $bookmark;
		}
		$url = "/users/pins/liked/?" . http_build_query($query_string);
		$response = $this->api("GET", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Get the pins this user has pinned
	*/
	public function getUserPins($userId, $bookmark = NULL){
		$query_string = array(
			"access_token" => $this->accessToken,
			"page_size" => "50",
			"fields" => "pin.images[236x,736x],pin.id,pin.description,pin.image_medium_url,pin.image_medium_size_pixels,pin.created_at,pin.like_count,pin.repin_count,board.id,board.name,board.category,board.created_at,board.layout,board.image_thumbnail_url,user.id,user.username,user.first_name,user.last_name,user.full_name,user.gender,user.image_medium_url,pin.liked_by_me,pin.dominant_color,pin.rich_summary(),pin.embed(),pin.promoter(),pin.recommendation_reason,pin.board(),pin.pinner(),pin.via_pinner(),pin.place_summary()"
		);
		if($bookmark != NULL){
			$query_string['bookmark'] = $bookmark;
		}
		$url = "/users/pins/?" . http_build_query($query_string);
		$response = $this->api("GET", $url);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
	
	/*
	* Repin a pin
	*/
	public function repin($pinId, $boardId, $description){
		$query_string = array(
			"access_token" => $this->accessToken
		);
		
		$data = array(
			"board_id" => $board_id,
			"place" => "",
			"share_facebook" => "0",
			"share_twitter" => "0",
			"description" => $description
		);
		
		$url = "/users/pins/" . $pinId . "/repin/?" . http_build_query($query_string);
		$response = $this->api("POST", $url, $data);
		if($response->status != "success"){
			pdebug($response->message);
			return false;
		}else{
			if($response->message == "ok"){
				return $response;
			}else{
				pdebug($response->message);
				return false;
			}
		}
	}
}//End of Pinterest class
