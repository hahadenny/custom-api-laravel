<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class D3Service
{
    public $http = 'http://';
    
    public function __construct()
    {        
    }
    
    public function D3APICall($request) 
    {
        $data = json_decode($request->data, true);
        
        if (isset($data['transport_0']))
            $result = $this->transportControl($request);
        elseif (isset($data['indirection_0']))
            $result = $this->indirection($request);
        else
            $result = array();
        return $result;
    }
    
    public function transportControl($request)
    {
        $data = json_decode($request->data, true);        
        
        if (!$data['d3_ip'])
            return false;
        
        $http = $this->http;
        $port = '';
        if ($data['d3_port']);
            $port = ":$data[d3_port]";
        $domain = "$http$data[d3_ip]$port/api/v1/transportcontrol";
        
        $result = array();
        
        // 1. feed outputs (for sreens)
        if (isset($data['feed_outputs']) && $data['feed_outputs'] && $data['feed_outputs'] !== 'default') {
            $url = "$domain/$data[feed_outputs]";
            $result = $this->d3_curl($url, 'POST');
        }
        
        // 2. run each track
        if (!isset($data['track_no']))
            $data['track_no'] = 1;
        
        $actions = array();
        for ($i = 0; $i < $data['track_no']; $i++) {
            // 3. engage transport
            if ($data["transport_$i"]) {
                $url = "$domain/transports/".$data["transport_$i"]."/engage";
                $result = $this->d3_curl($url, 'POST');
                
                // 3a. stop the track first if jump is required
                if (isset($data["jump_to_$i"]) && $data["jump_to_$i"] !== 'default' && $data["action_$i"] !== 'none') {
                    $url = "$domain/transports/".$data["transport_$i"]."/stop";
                    $result = $this->d3_curl($url, 'POST');
                }
                
                // 4. select track
                if ($data["track_$i"]) {                    
                    $url = "$domain/transports/".$data["transport_$i"]."/gototrack/".$data["track_$i"];
                    $result = $this->d3_curl($url, 'POST');
                }
                
                // 5. set brightness and volume
                if (isset($data["brightness_$i"])) {
                    $url = "$domain/transports/".$data["transport_$i"]."/brightness/".$data["brightness_$i"];
                    $result = $this->d3_curl($url, 'POST');
                }                
                if (isset($data["volume_$i"])) {
                    $url = "$domain/transports/".$data["transport_$i"]."/volume/".$data["volume_$i"];
                    $result = $this->d3_curl($url, 'POST');
                }
                
                // 6. jump to
                if (isset($data["jump_to_$i"])) {
                    if (in_array($data["jump_to_$i"], array('TC', 'MIDI', 'CUE')) && $data["jump_to_tag_$i"]) {
                        $url = "$domain/transports/".$data["transport_$i"]."/gotocuetag/".$data["jump_to_$i"]."/".$data["jump_to_tag_$i"];
                        $sdata['uid'] = $data["transport_$i"];
                        $sdata['type'] = $data["jump_to_$i"];
                        $sdata['tag'] = (string) $data["jump_to_tag_$i"];
                        $sdata['allowGlobalJump'] = true;
                        $sdata_json = json_encode($sdata);
                        $headers = array(
                                        'Content-Type:application/json',
                                        'Content-Length: ' . strlen($sdata_json)
                                    );
                        $result = $this->d3_curl($url, 'POST', $sdata_json, $headers);
                    }
                    elseif ($data["jump_to_$i"] === 'time' && $data["jump_to_time_$i"]) {
                        $url = "$domain/transports/".$data["transport_$i"]."/gototimecode/".$data["jump_to_time_$i"].':00';
                        $result = $this->d3_curl($url, 'POST');
                    }
                    elseif ($data["jump_to_$i"] !== 'default') {
                        $url = "$domain/transports/".$data["transport_$i"]."/".$data["jump_to_$i"];
                        $result = $this->d3_curl($url, 'POST');
                    }
                }
                
                // 7. action
                if (isset($data["action_$i"]) && $data["action_$i"] !== 'none') {
                    $url = "$domain/transports/".$data["transport_$i"]."/".$data["action_$i"];
                    //move out to minimize delay
                    $actions[] = $url;
                }
            }
        }    

        /*foreach ($actions as $action) {
            $result = $this->d3_curl($action, 'POST');
        }*/
        
        if (count($actions))
            $result = $this->d3_bulk_curl($actions, 'POST');
        
        return $result;
    }
    
    public function indirection($request)
    {
        $data = json_decode($request->data, true);        
        
        if (!$data['d3_ip'])
            return false;
        
        $http = $this->http;
        $port = '';
        if ($data['d3_port']);
            $port = ":$data[d3_port]";
        $url = "$http$data[d3_ip]$port/api/v1/indirections/set";
        
        $result = array();
        
        for ($i = 0; $i < $data['indirection_no']; $i++) {
            $sdata['assignments'][$i]['uid'] = explode('-', $data["indirection_$i"])[1];
            $sdata['assignments'][$i]['resourceUid'] = $data["resource_$i"];
        }        
        
        $sdata_json = json_encode($sdata);
        
        $headers = array(
                        'Content-Type:application/json',
                        'Content-Length: ' . strlen($sdata_json)
                    );
                    
        $result = $this->d3_curl($url, 'POST', $sdata_json, $headers);
        
        return $result;
    }
    
    public function getTransports($request) {
        $d3_ip = $request->d3_ip;
        $d3_port = $request->d3_port ? ":$request->d3_port" : '';
        
        $endpoint = "{$this->http}{$d3_ip}{$d3_port}/api/v1/transportcontrol/transports";        
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);
            
            //filter type
            $transports = array();
            if (isset($data['transports'])) {
                $i = 0;
                foreach ($data['transports'] as $tdata) {
                    $transports[$i]['name'] =  $tdata['name'];
                    $transports[$i]['uid'] =  $tdata['uid'];
                    $i++;
                }
            }
            
            usort($transports, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
            
            return $transports;
        }
    }
    
    public function getTracks($request) {
        $d3_ip = $request->d3_ip;
        $d3_port = $request->d3_port ? ":$request->d3_port" : '';
        
        $endpoint = "{$this->http}{$d3_ip}{$d3_port}/api/v1/transportcontrol/tracks";        
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);
            
            //filter type
            $tracks = array();
            if (isset($data['tracks'])) {
                $i = 0;
                foreach ($data['tracks'] as $tdata) {
                    $tracks[$i]['name'] =  $tdata['name'];
                    $tracks[$i]['uid'] =  $tdata['uid'];
                    $i++;
                }
            }
            
            usort($tracks, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
            
            return $tracks;
        }
    }
    
    public function tagList($request, $track, $type) {
        $d3_ip = $request->d3_ip;
        $d3_port = $request->d3_port ? ":$request->d3_port" : '';
        
        $endpoint = "{$this->http}{$d3_ip}{$d3_port}/api/v1/transportcontrol/tracks/{$track}/cues";        
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);
            
            //filter type
            $tags = array();
            if (isset($data['cues'])) {
                $i = 0;
                foreach ($data['cues'] as $tdata) {
                    if (isset($tdata['tag']) && $tdata['tag']['type'] === $type) {
                        $tags[$i]['name'] =  $tdata['tag']['tagText'];
                        $tags[$i]['tid'] =  $tdata['tag']['tagText'];
                        $i++;
                    }                    
                }
            }
            
            usort($tags, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
            
            return $tags;
        }
    }
    
    public function allTagList($request, $type) {
        $d3_ip = $request->d3_ip;
        $d3_port = $request->d3_port ? ":$request->d3_port" : '';
        
        //get all tracks first
        $endpoint = "{$this->http}{$d3_ip}{$d3_port}/api/v1/transportcontrol/tracks";
        $output = $this->d3_curl($endpoint);
        
        $tracks = json_decode($output, true);
        $tags = array();
        $i = 0;
        if (isset($tracks['tracks'])) {
            foreach ($tracks['tracks'] as $track) {  
                $tendpoint = "$endpoint/{$track['uid']}/cues";
                
                $output = $this->d3_curl($tendpoint);
                
                if (!$this->isJson($output)) {
                    $result['status'] = 'failed';
                    $result['message'] = $output;
                    return $result;
                }
                else {
                    $data = json_decode($output, true);
                    
                    //filter type
                    if (isset($data['cues'])) {                    
                        foreach ($data['cues'] as $tdata) {
                            if (isset($tdata['tag']) && $tdata['tag']['type'] === $type) {
                                $tags[$i]['name'] =  $track['name'].' - '.$tdata['tag']['tagText'];
                                $tags[$i]['tid'] =  $tdata['tag']['tagText'];
                                $i++;
                            }        
                        }
                    }               
                }
            }
        }
        
        usort($tags, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        
        return $tags;
    }
    
    public function indirectionList($request, $type) {
        $d3_ip = $request->d3_ip;
        $d3_port = $request->d3_port ? ":$request->d3_port" : '';
        
        $endpoint = "{$this->http}{$d3_ip}{$d3_port}/api/v1/indirections";        
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);
            
            //filter type
            $inds = array();
            if (isset($data['result'])) {
                $i = 0;
                foreach ($data['result'] as $ind) {
                    if (isset($ind[$type])) {
                        $inds[$i]['name'] =  $ind['name'];
                        $inds[$i]['uid'] =  $ind['resourceType'].'-'.$ind['uid'];
                        $i++;
                    }                    
                }
            }
            
            usort($inds, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
            
            return $inds;
        }
    }
    
    public function resourceList($request, $type) {
        $d3_ip = $request->d3_ip;
        $d3_port = $request->d3_port ? ":$request->d3_port" : '';
        
        $endpoint = "{$this->http}{$d3_ip}{$d3_port}/api/v1/resources?type=$type";        
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $data = json_decode($output, true);
            
            //filter type
            $resources = array();
            if (isset($data['result'])) {
                $i = 0;
                foreach ($data['result'] as $resource) {
                    if ($resource['type'] === $type) {
                        $resources[$i]['name'] =  "$resource[name] ($resource[path])";
                        $resources[$i]['uid'] =  $resource['uid'];
                        $i++;
                    }                    
                }
            }
            
            usort($resources, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
            
            return $resources;
        }
    }
    
    public function convertToH264($request) {
        $authUser = Auth::guard()->user();       
        $h264 = $authUser->company->media()->where('h264_preview', $request->id)->get();
        if (count($h264))
            return $h264->first();
        
        ini_set('max_execution_time', 0);
        $data = array(
            'url' => $request->url,
            'media_id' => $request->id
            );            
        $app_tools_url = env('APP_TOOLS_URL', 'https://tools.porta.solutions');
        $result = $this->d3_curl($app_tools_url.'/api/convertH264', 'POST', $data);
        $rdata = json_decode($result); 
        $rdata->original_url = $app_tools_url.$rdata->filename;
        
        $path = base_path();        
        $cmd = "/usr/bin/php $path/artisan SaveMedia $authUser->id $request->id $rdata->original_url > /dev/null 2>&1 &";
        exec($cmd);
        
        return $rdata;
        
        /*$authUser = Auth::guard()->user();       
        $h264 = $authUser->company->media()->where('h264_preview', $request->id)->get();
        if (count($h264))
            return $h264->first();
        $filename = 'h264_preview_'.basename($request->url);   
        $cmd = "ffmpeg -i $request->url -vcodec libx264 -acodec aac -pix_fmt yuv420p $filename";
        exec($cmd);   
        $rs = $authUser->company->addMedia($filename)->toMediaCollection(Company::MEDIA_COLLECTION_NAME); //comment out for testing
        $authUser->company->media()->find($rs->id)->update(['h264_preview' => $request->id]); //comment out for testing
        $cmd = "rm $filename";
        exec($cmd);        
        return $authUser->company->media()->find($rs->id);*/
        //return $authUser->company->media()->find($request->id); //for testing
    }
    
    public function updatePageMediaID($request) {
        $page = DB::table('pages')->where('id', $request->page_id)->get()->first();
        $data = json_decode($page->data, true);
        $data['media_id_'.$request->ind] = $request->uid;
        if (!is_numeric($request->uid))
            $data['media_url_'.$request->ind] = '';
        $updates = array('data' => json_encode($data));
        DB::table('pages')
            ->where('id', $request->page_id)
            ->update($updates);
        return $data;
    }
    
    public function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public function d3_curl($url, $method = 'GET', $post_data = array(), $headers = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // SSL important
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $timeout = 10;
        if ($method === 'POST')
            $timeout = 9999999;
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout in seconds

        $output = curl_exec($ch);        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            echo $error_msg; exit;
        }
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
