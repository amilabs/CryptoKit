<?php

namespace AmiLabs\CryptoKit\Net\RPC\Client;

class JSON extends \Deepelopment\Net\RPC\Client\JSON
{
    protected function patchResponse(&$response)
    {
        // : 123, ~ : 123}
        // [123, 123, 123]
        foreach(
            array(
                '/(\\\\"\:\s*)(-?[0-9\.]+)(\,|\})/u',
                '/("\:\s*)(-?[0-9\.]+)(\,|\})/u',
                '/((?:\[|\,)\s*)(-?[0-9\.]+)(\s*(?:\]|\,))/'
            ) as $index => $regExp
        ){
            if(preg_match_all($regExp, $response, $matches)){
                $prev = $response;###
                $quote = $index ? '"' : '\\"';
                foreach($matches[0] as $index => $searchString){
                    $response = str_replace(
                        $searchString,
                        $matches[1][$index] . $quote . $matches[2][$index] . $quote . $matches[3][$index],
                        $response
                    );
                }
                if(!json_decode($response)){
                    echo "{$prev}\n\n{$response}\n";die;###
                }
            }
        }
    }
}
