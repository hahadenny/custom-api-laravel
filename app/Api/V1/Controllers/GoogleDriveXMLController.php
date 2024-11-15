<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\GoogleDriveXMLService;

class GoogleDriveXMLController extends Controller
{       
    public function getXMLs(Request $request, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLs($request);
        return response()->json($result);          
    }
    
    public function getXMLRows(Request $request, $xml_id, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLRows($request, $xml_id);
        return response()->json($result);          
    }
    
    public function getXMLTags(Request $request, $xml_id, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLTags($request, $xml_id);
        return response()->json($result);          
    }
    
    public function getXMLTagVals(Request $request, $xml_id, $tag, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLTagVals($request, $xml_id, $tag);
        return response()->json($result);          
    }
    
    public function getXMLTagVal(Request $request, $xml_id, $tag, $index, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLTagVal($request, $xml_id, $tag, $index);
        return response()->json($result);          
    }
    
    public function getXMLAllTagsVals(Request $request, $xml_id, $index, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLAllTagsVals($request, $xml_id, $index);
        return response()->json($result);          
    }
    
    public function getXMLTagValsByOrder(Request $request, $xml_id, $tag, $order, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLTagValsByOrder($request, $xml_id, $tag, $order);
        return response()->json($result);          
    }
    
    public function getXMLAllRowsTags(Request $request, $xml_id, GoogleDriveXMLService $googleDriveXMLService)
    {
        $result = $googleDriveXMLService->getXMLAllRowsTags($request, $xml_id);
        return response()->json($result);          
    }
}
