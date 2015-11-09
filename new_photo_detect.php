<?php
	set_time_limit(0);
	require 'libs/LIB_http.php';
	require 'libs/LIB_download_images.php';
	require "libs/phpFlickr.php";

	define("host", "mysql:host=localhost;dbname=your-db-name");
	define("user_name", "your-name");
	define("user_pwd", "your-password");

	//Using curl libs http get url.
	function get_paging($url)
	{
		$result = http_get($url, $refer = "");
		$result = $result["FILE"];
		$result = json_decode($result, true);
		return $result;
	}

	//Using flickr api to upload pics.
	function upload_photo($path, $title) 
	{
		$apiKey = "your-flickr-key";
        		$apiSecret = "your-flickr-secret";
        		$token = "flickr-token";
         		$permissions  = "write";

         		$f = new phpFlickr($apiKey, $apiSecret, true);
         		$f->setToken($token);
         		$f->async_upload($path, $title);
         		@unlink("temp.jpg");
     	}

	function save_binary_file($url)
	{
		$binary_file = download_binary_file($url, $refer = "");
		file_put_contents("temp.jpg", $binary_file);
	}

	$link = new PDO(host, user_name, user_pwd);
	$link -> query("SET NAMES utf8");
	//using while.... until next link data is empty.
	$result = get_paging("https://graph.facebook.com/1450930895146846/feed?access_token=your-access-token&limit=250");
	
	$data_arr = $result["data"];
	$data_arr_len = count($data_arr);

	for($count=0;$count<$data_arr_len;$count++)
	{
		$message = $data_arr[$count]["message"];
		$object_id = $data_arr[$count]["object_id"];
		$sql = "SELECT COUNT(obj_id) FROM beauty WHERE obj_id = :object_id";
		$stmt = $link -> prepare($sql);
		$stmt -> execute(array(":object_id"=>$object_id));
		if($stmt -> fetchColumn() !== 0)
			break;
		if(mb_stristr($message, "正妹") === false)
			continue;

		save_binary_file("https://graph.facebook.com/".$object_id."/picture?type=normal");
		upload_photo("temp.jpg", $message);

		save_binary_file("https://graph.facebook.com/".$object_id."/picture?type=thumbnail");
		upload_photo("temp.jpg", $message);

		$sql = "INSERT INTO beauty(message,obj_id) VALUES(:message,:obj_id)";
		try
		{
			$stmt = $link -> prepare($sql);
			$stmt -> execute(array(":message"=>$message, ":obj_id"=>$object_id,));
		}
		catch (PDOException $e)
		{
			//check duplicate entries.
			if($e->errorInfo[0] == '23000' && $e->errorInfo[1] == '1062')
			{
				if(file_exists("error_log.log"))
					@unlink("error_log.log");
				file_put_contents("error_log.log", "Duplicate data\r\n");
			} 
			else 
			{
				file_put_contents("error_log.log", "Other errors.\r\n");
 			}
		}
	}
?>