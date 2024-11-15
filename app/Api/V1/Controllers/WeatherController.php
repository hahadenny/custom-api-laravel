<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\WeatherService;

class WeatherController extends Controller
{      
    public function getCities(Request $request, WeatherService $weatherService)
    {        
        $result = $weatherService->getCities($request);
        return response()->json($result);          
    }
    
    public function getWeather(Request $request, $city, WeatherService $weatherService)
    {
        $result = $weatherService->getWeather($city);
        return response()->json($result);          
    }
}
