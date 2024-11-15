<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\D3Service;

class D3Controller extends Controller
{    
    public function D3APICall(Request $request, D3Service $d3Service)
    {
        $result = $d3Service->D3APICall($request);
        return response()->json($result);
    }
    
    public function getTracks(Request $request, D3Service $d3Service)
    {        
        $result = $d3Service->getTracks($request);
        return response()->json($result);          
    }
    
    public function getTransports(Request $request, D3Service $d3Service)
    {        
        $result = $d3Service->getTransports($request);
        return response()->json($result);          
    }
    
    public function tagList(Request $request, $track, $type, D3Service $d3Service)
    {
        $result = $d3Service->tagList($request, $track, $type);
        return response()->json($result);        
    }
    
    public function allTagList(Request $request, $type, D3Service $d3Service)
    {
        $result = $d3Service->allTagList($request, $type);
        return response()->json($result);          
    }   
    
    public function indirectionList(Request $request, $type, D3Service $d3Service)
    {
        $result = $d3Service->indirectionList($request, $type);
        return response()->json($result);          
    }
    
    public function resourceList(Request $request, $type, D3Service $d3Service)
    {
        $result = $d3Service->resourceList($request, $type);
        return response()->json($result);          
    }
    
    public function convertToH264(Request $request, D3Service $d3Service)
    {
        $result = $d3Service->convertToH264($request);
        return response()->json($result);          
    }
    
    public function updatePageMediaID(Request $request, D3Service $d3Service)
    {
        $result = $d3Service->updatePageMediaID($request);
        return response()->json($result);          
    }
}
