<?php

namespace AmiLabs\CryptoKit\Net\RPC\Client;

class JSON extends \Deepelopment\Net\RPC\Client\JSON
{
    protected function patchResponse(&$response)
    {
        if (preg_match_all('/((?:"\:|\[|\,)\s*)(-?[0-9\.]+)/u', $response, $matches)){
            foreach($matches[0] as $index => $searchString){
                $response = str_replace(
                    $searchString,
                    $matches[1][$index] . '"' . $matches[2][$index] . '"',
                    $response
                );
            }
        }
    }
}
