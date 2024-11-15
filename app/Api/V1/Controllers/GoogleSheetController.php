<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\GoogleSheetService;

class GoogleSheetController extends Controller
{   
    public function googleOauth(Request $request, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->googleOauth($request);
        return response()->json($result);          
    }
    
    public function deleteGoogleOauth(Request $request, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->deleteGoogleOauth($request);
        return response()->json($result);          
    }
    
    public function getGoogleOauth(Request $request, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getGoogleOauth($request);
        return response()->json($result);          
    }

    public function getSheetVals(Request $request, $spreadsheet_id, $sheet_id, $google_fields, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getSheetVals($request, $spreadsheet_id, $sheet_id, $google_fields);
        return response()->json($result);          
    }
    
    public function getSheets(Request $request, $spreadsheet_id, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getSheets($request, $spreadsheet_id);
        return response()->json($result);          
    }
    
    public function getSpreadsheets(Request $request, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getSpreadsheets($request);
        return response()->json($result);          
    }
    
    public function getTitles(Request $request, $spreadsheet_id, $sheet_id, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getTitles($request, $spreadsheet_id, $sheet_id);
        return response()->json($result);          
    }
    
    public function getColumnRows(Request $request, $spreadsheet_id, $sheet_id, $column, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getColumnRows($request, $spreadsheet_id, $sheet_id, $column);
        return response()->json($result);          
    }
    
    public function getColumnRowsByOrder(Request $request, $spreadsheet_id, $sheet_id, $column, $order, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getColumnRowsByOrder($request, $spreadsheet_id, $sheet_id, $column, $order);
        return response()->json($result);          
    }
    
    public function getAllColsRows(Request $request, $spreadsheet_id, $sheet_id, GoogleSheetService $googleSheetService)
    {
        $result = $googleSheetService->getAllColsRows($request, $spreadsheet_id, $sheet_id);
        return response()->json($result);          
    }
}
