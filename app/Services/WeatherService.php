<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\CompanyIntegrations;

class WeatherService
{
    public $http = 'http://';
    
    public $ipsum_server = 'http://ipsum-staging.eastus.cloudapp.azure.com:3000';
    
    public function __construct(Request $request)
    {        
        $integration = CompanyIntegrations::query()
                        ->select('value')
                        ->join('companies', 'companies.id', 'company_integrations.company_id')
                        ->where([['companies.api_key', $request->api_key], ['company_integrations.type', 'ipsum_server']])
                        ->get()->first();
             
        if ($integration && $integration->value) {
            $this->ipsum_server = $integration->value;
            if (substr($this->ipsum_server, 0, 4) !== 'http')
                $this->ipsum_server = 'http://'.$this->ipsum_server;
        }
    }  
    
    public function getCities($request) {       
        $endpoint = "{$this->ipsum_server}/api/v1/porta/weather/";        
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);
            
            usort($data, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
            
            return $data;
        }
    }
    
    public function getWeather($city) {       
        //$endpoint = "{$this->ipsum_server}/api/v1/porta/weather/".str_replace(' ', '%20', $city); 
        $endpoint = "{$this->ipsum_server}/api/v1/porta/weather/".str_replace('%2C', ',', str_replace('+', '%20', urlencode($city))); 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);   
            $wdata = array();
            
            //DC: don't use weekly for now, only use daily
            //$period = 'weekly';
            $period = 'daily';
            if (!isset($data[0][$period]))
                $period = 'daily';
            
            if ($period === 'weekly')
                $pdata = $data[0][$period]['data']['report']['location']['forecast'];
            elseif ($period === 'daily')
                $pdata = $data[0][$period]['data'];
                
            $future_days = 7;
            $fdays = array();
            $fdays1 = array();
            for ($i = 0; $i <= $future_days; $i++) {               
                $fdays[$i] = date("mdy", strtotime("+$i day"));
                $fdays1[$i] = date("Y-m-d", strtotime("+$i day"));
            }
            
            $tdata = array('high_temp', 'low_temp', 'weather_symbol', 'weather_symbol_night', 'description', 'precip_desc', 'sky_desc', 'temp_desc', 'humidity', 'wind_speed', 'wind_direction', 'precip_prob', 'uv');
            
            foreach ($pdata as $forecast) {
                //print_r($forecast); exit;               
                $date_format1 = substr($forecast['date'], 0, 10);
                
                if ($forecast['date'] === $fdays[0] || $date_format1 === $fdays1[0])
                    $day = '0';
                elseif ($forecast['date'] === $fdays[1] || $date_format1 === $fdays1[1])
                    $day = '1';
                elseif ($forecast['date'] === $fdays[2] || $date_format1 === $fdays1[2])
                    $day = '2';
                elseif ($forecast['date'] === $fdays[3] || $date_format1 === $fdays1[3])
                    $day = '3';
                elseif ($forecast['date'] === $fdays[4]|| $date_format1 === $fdays1[4])
                    $day = '4';
                elseif ($forecast['date'] === $fdays[5] || $date_format1 === $fdays1[5])
                    $day = '5';
                elseif ($forecast['date'] === $fdays[6] || $date_format1 === $fdays1[6])
                    $day = '6';
                elseif ($forecast['date'] === $fdays[7] || $date_format1 === $fdays1[7])
                    $day = '7';
                else
                    continue;
                
                $wdata[$day]['date'] = ($period === 'weekly') ? date_format(date_create_from_format('mdy', $forecast['date']), 'Y-m-d') : $date_format1;
                $wdata[$day]['weekday'] = isset($forecast['weekday']) ? $forecast['weekday'] : date("l", strtotime($wdata[$day]["date"]));
                
                foreach ($tdata as $tval) 
                    $wdata[$day][$tval] = isset($forecast[$tval]) ? $forecast[$tval] : '';
            }
            
            if (!isset($wdata[0])) { //get from current
                $wdata[0]['date'] = date('Y-m-d');
                $wdata[0]['weekday'] = date('l');
                foreach ($tdata as $tval) 
                    $wdata[0][$tval] = isset($data[0]['current']['data'][0][$tval]) ? $data[0]['current']['data'][0][$tval] : '';
            }
            
            $wdata[0]['current_temp'] = $data[0]['current']['data'][0]['temperature'];
            
            ksort($wdata);
            
            return $wdata;
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
