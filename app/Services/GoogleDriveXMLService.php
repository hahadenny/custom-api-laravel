<?php

namespace App\Services;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Drive;

class GoogleDriveXMLService
{    
    public function __construct(Request $request) { 
    }  
    
    public function getXMLs($request) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $endpoint = "https://www.googleapis.com/drive/v3/files?mimeType='application/vnd.google-apps.spreadsheet'";  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $xmls = [];
        
        if (!$this->isJson($output)) {            
            return $xmls;
        }
        else {
            $data = json_decode($output, true);   
            
            if (isset($data['error']))
                $xmls[] = $data['error']['message'];
            
            $sdata = isset($data['files']) ? $data['files'] : [];
            
            $i =0;
            foreach ($sdata as $sarr) {
                if (preg_match('/\/xml/i', $sarr['mimeType'])) {
                    $xmls[$i]['id'] = $sarr['id'];
                    $xmls[$i]['name'] = $sarr['name'];
                    $i++;
                }
            }
            
            usort($xmls, function($a, $b) { // anonymous function
                // compare numbers only
                return strcmp($a["name"], $b["name"]);
            });
            
            return $xmls;
        }
    }
    
    public function getXMLRows($request, $xml_id) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);   
        
        $rows_arr = array();
        if (isset($xml_array['ordering_1']) && isset($xml_array['ordering_1']['element'])) {
            foreach ($xml_array as $key => $ordering) {
                $rows_arr[] = str_replace('ordering_', '', $key);
            }
        }
        
        $rows = array();
        $i = 0;
        foreach ($rows_arr as $row) {
            $rows[$i]['id'] = $row;
            $rows[$i]['name'] = $row;
            $i++;
        }
        
        return $rows;
    }
    
    public function getXMLTags($request, $xml_id) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);          
        
        $tags_arr = array();
        if (isset($xml_array['ordering_1']) && isset($xml_array['ordering_1']['element'])) {
            $tags_arr = array_keys($xml_array['ordering_1']['element']);
        }
        
        $tags = array();
        $i = 0;
        foreach ($tags_arr as $tag) {
            $tags[$i]['id'] = $tag;
            $tags[$i]['name'] = $tag;
            $i++;
        }
        
        return $tags;
    }
    
    public function getXMLTagVals($request, $xml_id, $tag) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);                    
        
        $values = array();
        $i = 0;
        if (isset($xml_array['ordering_1']) && isset($xml_array['ordering_1']['element'])) {
            foreach ($xml_array as $k => $orderings) {
                if (isset($orderings['element']) && isset($orderings['element']['position']) && isset($orderings['element'][$tag]) && !is_array($orderings['element']['position'])) {
                    $val = $orderings['element'][$tag];
                    if (is_array($val))
                        $val = '';
                    $values[$i]['id'] = $orderings['element']['position'].': '.$val;
                    $values[$i]['name'] = $orderings['element']['position'].': '.$val;
                    $i++;
                }
            }
        }
        
        return $values;
    }
    
    public function getXMLTagVal($request, $xml_id, $tag, $index) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);                     
        
        $value['value'] = '';
        $i = 0;
        if (isset($xml_array['ordering_1']) && isset($xml_array['ordering_1']['element'])) {
            foreach ($xml_array as $k => $orderings) {
                if (isset($orderings['element']) && isset($orderings['element']['position']) && isset($orderings['element'][$tag]) && !is_array($orderings['element']['position']) && $orderings['element']['position'] == $index) {
                    $value['value'] = $orderings['element'][$tag];
                    break;
                }
            }
        }
        
        return $value;
    }
    
    public function getXMLAllTagsVals($request, $xml_id, $index) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);      
        
        $values = array();
        $i = 0;
        if (isset($xml_array['ordering_1']) && isset($xml_array['ordering_1']['element'])) {
            foreach ($xml_array as $k => $orderings) {
                if (isset($orderings['element']) && isset($orderings['element']['position']) && !is_array($orderings['element']['position']) && $orderings['element']['position'] == $index) {
                    foreach ($orderings['element'] as &$val) {
                        if (is_array($val))
                            $val = '';
                    }
                    $values = $orderings['element'];
                }
            }
        }
        
        return $values;
    }
    
    public function getXMLTagValsByOrder($request, $xml_id, $tag, $order) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);       

        $rows = array();
        foreach ($xml_array as $mtag => $orderings) {
            if (preg_match("/ordering_/i", $mtag)) {
                $ind = str_replace('ordering_', '', $mtag);
                if (isset($orderings['element']) && isset($orderings['element'][$tag]) && $orderings['element'][$tag]) {
                    $rows[$ind] = $orderings['element'][$tag];
                }
            }
        }
        
        if ($order === 'desc') {
            arsort($rows);
        }
        elseif ($order === 'asc') {
            asort($rows);
        }
        
        $final_results = array();
        $i = 0;
        foreach ($rows as $k => $row) {
            if (trim($row) !== '') {
                foreach ($xml_array["ordering_$k"]['element'] as &$val) {
                    if (is_array($val))
                        $val = '';
                }
                $final_results[$i]['key'] = "$k: $row";
                $final_results[$i]['data'] = $xml_array["ordering_$k"]['element'];
                $i++;
            }
        }
        
        return $final_results;
    }
    
    public function getXMLAllRowsTags($request, $xml_id) {
        $xml_array = $this->getXMLContentArray($request->token, $xml_id);      
        
        $values = array();
        $i = 0;
        if (isset($xml_array['ordering_1']) && isset($xml_array['ordering_1']['element'])) {
            foreach ($xml_array as $k => $orderings) {
                $key = str_replace('ordering_', '', $k);
                if (isset($orderings['element'])) {
                    foreach ($orderings['element'] as &$val) {
                        if (is_array($val))
                            $val = '';
                    }
                    $values[$key] = $orderings['element'];
                }
            }
        }
        
        return $values;
    }
    
    public function getXMLContentArray($token, $xml_id) {
        $client = new Google_Client();
        $client->setAccessToken($token);
        
        $service = new Google_Service_Drive($client);
        
        $content = $service->files->get($xml_id, array("alt" => "media"));
        
        $xml_content = '';
        while (!$content->getBody()->eof()) {
            $xml_content .= $content->getBody()->read(1024);
        }
        
        $xml = simplexml_load_string($xml_content, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json, TRUE); 
        
        return $array;
    }
    
    public function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }
        
    public function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public function d3_curl($url, $method = 'GET', $post_data = array(), $headers = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // SSL important
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); //timeout in seconds
        curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');

        $output = curl_exec($ch);
        curl_close($ch);
        
        return $output;        
    }
    
    public function d3_bulk_curl($urls, $method = 'GET', $post_data = array(), $headers = array()) {
        $c = count($urls);
        
        if (!$c)
            return false;
        
        //create the multiple cURL handle
        $mh = curl_multi_init();
        
        $ch = array();
        for($i=0; $i<$c; $i++) {
            $ch[$i] = curl_init();
            curl_setopt($ch[$i], CURLOPT_URL, $urls[$i]);
            curl_setopt($ch[$i], CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$i], CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch[$i], CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch[$i], CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch[$i], CURLOPT_TIMEOUT, 10); //timeout in seconds
            
            curl_multi_add_handle($mh, $ch[$i]);
        }
        
        //execute the multi handle
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status == CURLM_OK);
        
        //close the handles
        for($i=0; $i<$c; $i++) {
            curl_multi_remove_handle($mh, $ch[$i]);
        }
        curl_multi_close($mh);
        
        //return last output only
        $output = curl_multi_getcontent($ch[$c-1]);
        return $output;
    }
}
