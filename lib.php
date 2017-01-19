<?php

function request($url, $authorization) {
	// create curl resource
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	// set the headers
	curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Authorization: $authorization"));
	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// $output contains the output string
	$output = curl_exec($ch);

	// close curl resource to free up system resources
	curl_close($ch);

	return $output;
}
