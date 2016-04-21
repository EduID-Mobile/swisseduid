<?php
function request($url, $params, $method) {
	// create curl resource
	$ch = curl_init();

	if($method == 'GET'){
		// set url
		curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($params));
	} else {
		// set url
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
	}

	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// $output contains the output string
	$output = curl_exec($ch);

	// close curl resource to free up system resources
	curl_close($ch);

	return $output;
}
?>
