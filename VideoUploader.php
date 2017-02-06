<?php
require_once './vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Youtube Video Auto Uploader
 * The php script is using google oauth2 API v3 and Youtube API v3
 * 28,Jan,2016
 * A simple and modified examples that upload batches of video youtube by using php script.
 * @author Louis Choi (louis@simplylouis.com)
 */

	$config = Yaml::parse(file_get_contents('./config.yml'));

/**
 *	Upload the video files to youtube
 *	No matter success or fail, the result will be logged in logStatus()
 * 	@param String $videoPath 		Local video path (./1.mp4)
 *	@param String $videoTitle 		Video Title
 *	@param String $videoDescription Video Description, allow \n characters and links
 *	@param int 	  $videoCategory	Video Category ID, Please refer to the list - https://gist.github.com/dgp/1b24bf2961521bd75d6c
 *	@param String[] $videoTags		Keyword Tags array
 */
function uploadYoutube($videoPath, $videoTitle, $videoDescription, $videoCategory, $videoTags) {

	global $config;

	$OAUTH2_CLIENT_ID = $config['oauth2_client_id']; 
	$OAUTH2_CLIENT_SECRET = $config['oauth2_client_secret']; 
	$RESULT = array('refreshToken' =>  $config['refreshToken']); 
	$client = new Google_Client();
	$client->setClientId($OAUTH2_CLIENT_ID);
	$client->setClientSecret($OAUTH2_CLIENT_SECRET);
	$client->setScopes('https://www.googleapis.com/auth/youtube');
	$redirect = filter_var('http://localhost/authorize/');
	$client->setRedirectUri($redirect);
	$youtube = new Google_Service_YouTube($client);
	$client->refreshToken($RESULT['refreshToken']);
	$RESULT['accessToken'] = $client->getAccessToken()['access_token'];
	$client->authenticate($RESULT['accessToken']);
	if ($client->getAccessToken()) {
		try {
			logStatus('Video Start Upload - ' . $videoTitle);
			$snippet = new Google_Service_YouTube_VideoSnippet();
			$snippet->setTitle($videoTitle);
			$snippet->setDescription($videoDescription);
			$snippet->setTags($$videoTags);
			$snippet->setCategoryId($videoCategory);
			$status = new Google_Service_YouTube_VideoStatus();
			$status->privacyStatus = $config['privacy_type']; 
			$video = new Google_Service_YouTube_Video();
			$video->setSnippet($snippet);
			$video->setStatus($status);
			$chunkSizeBytes = 1 * 1024 * 1024;
			$client->setDefer(true);
			$insertRequest = $youtube->videos->insert("status,snippet", $video);
			$media = new Google_Http_MediaFileUpload(
				$client,
				$insertRequest,
				'video/*',
				null,
				true,
				$chunkSizeBytes
			);
			$media->setFileSize(filesize($videoPath));
			$status = false;
			$handle = fopen($videoPath, "rb");
			while (!$status && !feof($handle)) {
				$chunk = fread($handle, $chunkSizeBytes);
				$status = $media->nextChunk($chunk);
			}
			fclose($handle);
			$client->setDefer(false);
			logStatus('Video Uploaded -' . $status['snippet']['title'] . ' ' . $status['id']);
			saveYoutubePath($status['id']);
		} catch (Google_Service_Exception $e) {
			logStatus($e->getMessage());
			exit();
		} catch (Google_Exception $e) {
			logStatus($e->getMessage());
			exit();
		}
	} else {
		$state = mt_rand();
		$client->setState($state);
		$authUrl = $client->createAuthUrl();
		logStatus($authUrl);
		exit();
	}
}
/**
 *	Save the log the status during the program into logs.txt
 *	@param String 	$txt Log string of the input data
 */
function logStatus($txt) {
	global $config;
	echo $txt . "\n";
	file_put_contents($config['log_path'], $txt . PHP_EOL, FILE_APPEND);
}
/**
 *	Save the Youtube Key Identifier  https://www.youtube.com/watch?v="gkTb9GP9lVI"
 *	@param String 	$id Youtube Key Identifier
 */
function saveYoutubePath($id) {
	global $config;
	file_put_contents($config['uploaded_id_youtube_path'], $id . PHP_EOL, FILE_APPEND);
}
/**
 *	Run the upload script
 */
function run() {
	global $config;
	logStatus('Start command');
	
	$videoPath = $config['video_path'];
	$videoTitle = $config['video_title'];
	$videoDescription = $config['video_description'];
	$videoCategory = $config['video_categorie'];
	$videoTags = $config['video_tags'];
	uploadYoutube($videoPath, $videoTitle, $videoDescription, $videoCategory, $videoTags);

	logStatus('Successfully uploaded.' . $videoPath);
	logStatus('Finished command');
}
run();
?>