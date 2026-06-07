<?php

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.openai.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

curl_exec($ch);

if (!curl_errno($ch)) {
    echo "CURL WORKS";
} else {
    echo "CURL FAILED";
}

curl_close($ch);