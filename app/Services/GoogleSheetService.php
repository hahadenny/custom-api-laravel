<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoogleSheetService
{    
    public function __construct(Request $request) { 
    }  
    
    public function googleOauth($request) {
        //header('Access-Control-Allow-Origin: *');
        //header('Access-Control-Allow-Methods: GET, POST');
        //header("Access-Control-Allow-Headers: X-Requested-With");        
        $now = date('Y-m-d H:i:s');
        DB::table('google_oauth')->insert(['company_id' => $request->company_id, 'access_token' => $request->access_token, 'refresh_token' => $request->refresh_token, 'created_at' => $now]);    
        echo "<script>window.close();</script>";
        exit;
        return array();
    }
    
    public function deleteGoogleOauth($request) {        
        //print_r($request->all()); exit;
        DB::table('google_oauth')->where('company_id', $request->company_id)->delete();        
        return array();
    }
    
    public function getGoogleOauth($request) { 
        $result = DB::table('google_oauth')->where('company_id', $request->company_id)->orderBy('id', 'desc')->get()->first();        
        return $result;
    }
    
    public function getSpreadsheets($request) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $endpoint = "https://www.googleapis.com/drive/v3/files?mimeType='application/vnd.google-apps.spreadsheet'";  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $sheets = [];
        
        if (!$this->isJson($output)) {            
            return $sheets;
        }
        else {
            $data = json_decode($output, true);   
            
            if (isset($data['error']))
                $sheets[] = $data['error']['message'];
            
            $sdata = isset($data['files']) ? $data['files'] : [];
            
            $i =0;
            foreach ($sdata as $sarr) {
                if (preg_match('/spreadsheet/i', $sarr['mimeType']) && preg_match('/google/i', $sarr['mimeType'])) {
                    $sheets[$i]['id'] = $sarr['id'];
                    $sheets[$i]['name'] = $sarr['name'];
                    $i++;
                }
            }
            
            usort($sheets, function($a, $b) { // anonymous function
                // compare numbers only
                return strcmp($a["name"], $b["name"]);
            });
            
            return $sheets;
        }
    }
    
    public function getSheets($request, $spreadsheet_id) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id;     
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $sheets = [];
        
        if (!$this->isJson($output)) {            
            return $sheets;
        }
        else {
            $data = json_decode($output, true);   
            
            $sdata = isset($data['sheets']) ? $data['sheets'] : [];
            foreach ($sdata as $sarr) {
                $sheets[] = $sarr['properties']['title'];
            }
            
            return $sheets;
        }
    }
    
    public function getSheetVals($request, $spreadsheet_id, $sheet_id, $google_fields) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id.'/values:batchGet?ranges='.urlencode($sheet_id).'!'.$google_fields;     
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $values = [];
        
        if (!$this->isJson($output)) {            
            return $values;
        }
        else {
            $data = json_decode($output, true);
            
            if (isset($data['valueRanges'])) {
                $val_arr = $data['valueRanges'][0]['values'];
                
                foreach ($val_arr as $row_arr) {
                    foreach ($row_arr as $val) {
                        if (trim($val) !== '')
                            $values[] = $val;
                    }
                }
                
                $values = array_unique($values);
                sort($values);
            }
            
            return $values;
        }
    }
    
    public function getTitleRow($request, $spreadsheet_id, $sheet_id) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id.'/values:batchGet?ranges='.urlencode($sheet_id)."!A1:ZZ1";  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $trow = 1;
        $titles = [];
        $empty = 0;
        if (!$this->isJson($output)) {            
            return $trow;
        }
        else {
            $data = json_decode($output, true);
            
            if (isset($data['valueRanges'])) {
                $val_arr = $data['valueRanges'][0]['values'];
                
                foreach ($val_arr as $row_arr) {
                    foreach ($row_arr as $val) {
                        $titles[] = $val;
                        if (!$val)
                            $empty += 1;
                    }
                }
            }
            
            //use 2nd row as title as too many empty column in the 1st row
            if (count($titles) && $empty/count($titles) >= 0.5)
                $trow = 2;
            
            return $trow;
        }
    }
    
    public function getTitles($request, $spreadsheet_id, $sheet_id) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $trow = $this->getTitleRow($request, $spreadsheet_id, $sheet_id);
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id.'/values:batchGet?ranges='.urlencode($sheet_id)."!A$trow:ZZ$trow";  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers); 
        
        $titles = [];
        if (!$this->isJson($output)) {            
            return $titles;
        }
        else {
            $data = json_decode($output, true);
            
            if (isset($data['valueRanges'])) {
                $val_arr = $data['valueRanges'][0]['values'];
                
                foreach ($val_arr as $row_arr) {
                    foreach ($row_arr as $val) {
                        $titles[] = str_replace("\n", ' ', $val);
                    }
                }
            }
            
            $letter_titles = array();
            $i = 0;
            foreach ($titles as $ind => $title) {
                $letter = $this->getNameFromNumber($ind);
                $letter_titles[$i]['id'] = "$letter: $title";
                $letter_titles[$i]['name'] = "$letter: $title";
                $i++;
            }
            
            return $letter_titles;
        }
    }
    
    public function getColumnRows($request, $spreadsheet_id, $sheet_id, $column) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $trow = $this->getTitleRow($request, $spreadsheet_id, $sheet_id);
        $frow = $trow + 1;
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id.'/values:batchGet?ranges='.urlencode($sheet_id).'!'.$column.$frow.':'.$column.'10001';  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $rows = [];
        
        if (!$this->isJson($output)) {            
            return $rows;
        }
        else {
            $data = json_decode($output, true);
            
            if (isset($data['valueRanges'])) {
                $val_arr = $data['valueRanges'][0]['values'];
                
                $i = $trow - 1;
                foreach ($val_arr as $row_arr) {
                    $val = isset($row_arr[0]) ? $row_arr[0] : '	&#40;empty&#41;';
                    $id = $i + 2;
                    $rows[$i]['id'] = "$id: $val";
                    $rows[$i]['name'] = "$id: $val";
                    $i++;
                }
            }
            
            return $rows;
        }
    }
    
    public function getColumnRowsByOrder($request, $spreadsheet_id, $sheet_id, $column, $order) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $trow = $this->getTitleRow($request, $spreadsheet_id, $sheet_id);
        $frow = $trow + 1;
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id.'/values:batchGet?ranges='.urlencode($sheet_id).'!'.$column.$frow.':'.$column.'10001';  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $rows = [];
        
        if (!$this->isJson($output)) {            
            return $rows;
        }
        else {
            $data = json_decode($output, true);
            
            if (isset($data['valueRanges'])) {
                $val_arr = $data['valueRanges'][0]['values'];
                
                $i = $trow - 1;
                foreach ($val_arr as $row_arr) {
                    $val = isset($row_arr[0]) ? $row_arr[0] : '';
                    $id = $i + 2;
                    $rows[$id] = "$val";
                    $i++;
                }
            }
            
            if ($order === 'desc') {
                arsort($rows);
            }
            else if ($order === 'asc') {
                asort($rows);
            }
            
            $final_results = array();
            foreach ($rows as $k => $row) {
                if (trim($row) !== '' && !in_array(trim($row), array('POSICIÓN', 'DORSAL', 'POS 28', 'SALE', 'PUNTOS', 'POS 29', 'NO SALE')))
                $final_results[] = "$k: $row";
            }
            
            return $final_results;
        }
    }
    
    public function getAllColsRows($request, $spreadsheet_id, $sheet_id) {
        $authorization = "Authorization: Bearer $request->token";        
        $headers = array();
        $headers[] = $authorization;
        
        $trow = $this->getTitleRow($request, $spreadsheet_id, $sheet_id);
        $frow = $trow + 1;
        
        $endpoint = 'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheet_id.'/values:batchGet?ranges='.urlencode($sheet_id).'!A'.$frow.':ZZ10001';  
        $output = $this->d3_curl($endpoint, 'GET', array(), $headers);
        
        $rows = [];
        
        if (!$this->isJson($output)) {            
            return $rows;
        }
        else {
            $data = json_decode($output, true);
            
            $rows_cols = [];
            if (isset($data['valueRanges'])) {
                $val_arr = $data['valueRanges'][0]['values'];                
               
                foreach ($val_arr as $key => $row_arr) {
                    $row = $key + $frow;
                    $col_val = [];
                    foreach ($row_arr as $ind => $val) {
                        $col = $this->getNameFromNumber($ind);
                        $col_val[$col] = $val;
                    }
                    $rows_cols[$row] = $col_val;
                }
            }
            
            return $rows_cols;
        }
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
