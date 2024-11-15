<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SportsSoccerService;

class SportsSoccerController extends Controller
{     
    public function matchSample(Request $request, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->matchSample($request);
        return response()->json($result);          
    }

    public function matchSchedule(Request $request, $lang, $date, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->matchSchedule($request, $lang, $date);
        return response()->json($result);          
    }
    
    public function matchScheduleToday(Request $request, $lang, SportsSoccerService $sportsSoccerService)
    {        
        $date = date('Y-m-d');
        $result = $sportsSoccerService->matchSchedule($request, $lang, $date);
        return response()->json($result);          
    }
    
    public function matchScheduleList(Request $request, $lang, SportsSoccerService $sportsSoccerService)
    {        
        $date = 'all';
        $result = $sportsSoccerService->matchSchedule($request, $lang, $date);
        return response()->json($result);          
    }
 
    public function matchData(Request $request, $lang, $matchId, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->matchData($request, $lang, $matchId);
        return response()->json($result);          
    }
    
    public function matchSingleData(Request $request, $lang, $matchId, $data, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->matchData($request, $lang, $matchId, $data);
        return response()->json($result);          
    }
    
    public function matchList(Request $request, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->matchList($request);
        return response()->json($result);          
    }
    
    public function matchLineups(Request $request, $matchId, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->matchLineups($request, $matchId);
        return response()->json($result);          
    }
    
    public function standingsStages(Request $request, $lang, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->standingsStages($request, $lang);
        return response()->json($result);          
    }
    
    public function standings(Request $request, $lang, $stage, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->standings($request, $lang, $stage);
        return response()->json($result);          
    }
    
    public function venues(Request $request, $lang, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->venues($request, $lang);
        return response()->json($result);          
    }
    
    public function venueData(Request $request, $lang, $venueId, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->venueData($request, $lang, $venueId);
        return response()->json($result);          
    }
    
    public function teams(Request $request, $lang, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->teams($request, $lang);
        return response()->json($result);          
    }
    
    public function players(Request $request, $lang, $tournament_team, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->players($request, $lang, $tournament_team);
        return response()->json($result);          
    }
    
    public function allPlayers(Request $request, $lang, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->players($request, $lang, 'all-all');
        return response()->json($result);          
    }
    
    public function playerStats(Request $request, $lang, $tournament_player, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->playerStats($request, $lang, $tournament_player);
        return response()->json($result);          
    }
    
    public function playerStatsData(Request $request, $lang, $tournament_player, $data, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->playerStats($request, $lang, $tournament_player, $data);
        return response()->json($result);          
    }
    
    public function bracket(Request $request, $lang, $tournament, SportsSoccerService $sportsSoccerService)
    {        
        $result = $sportsSoccerService->bracket($request, $lang, $tournament);
        return response()->json($result);          
    }
}
