<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\CompanyIntegrations;

class SportsSoccerService
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
    
    //Sample Only
    public function matchSample($request) {     
        $sample = '{"match":[{"matchInfo":{"id":"bsu6pjne1eqz2hs8r3685vbhl","coverageLevel":"13","date":"2016-02-20Z","time":"12:00:00Z","localDate":"2016-02-20","localTime":"13:00:00","week":"22","numberOfPeriods":2,"periodLength":45,"lastUpdated":"2022-08-10T03:46:18Z","description":"Karlsruher SC vs Eintracht Braunschweig","sport":{"id":"289u5typ3vp4ifwh5thalohmq","name":"Soccer"},"ruleset":{"id":"79plas4983031idr6vw83nuel","name":"Men"},"competition":{"id":"722fdbecxzcq9788l6jqclzlw","name":"2. Bundesliga","competitionCode":"2.B","competitionFormat":"Domestic league","country":{"id":"36min0qztu8eydwvpv8t1is0m","name":"Germany"}},"tournamentCalendar":{"id":"408bfjw6uz5k19zk4am50ykmh","startDate":"2015-07-24Z","endDate":"2016-05-15Z","name":"2015/2016"},"stage":{"id":"6tca7sbnh37w596ws64rrez8p","formatId":"e2q01r9o9jwiq1fls93d1sslx","startDate":"2015-07-24Z","endDate":"2016-05-15Z","name":"Regular Season"},"contestant":[{"id":"aojwbjr39s1w2mcd9l2bf2dhk","name":"Karlsruher SC","shortName":"Karlsruhe","officialName":"Karlsruher SC","code":"KSC","position":"home","country":{"id":"36min0qztu8eydwvpv8t1is0m","name":"Germany"}},{"id":"kxpw3rqn4ukt7nqmtjj62lbn","name":"Eintracht Braunschweig","shortName":"Braunschweig","officialName":"Braunschweiger TSV Eintracht 1895","code":"EBS","position":"away","country":{"id":"36min0qztu8eydwvpv8t1is0m","name":"Germany"}}],"venue":{"id":"3kjfhyt2g4y2enxkdxrdfscxi","neutral":"no","longName":"BBBank Wildpark","shortName":"BBBank Wildpark"}},"liveData":{"matchDetails":{"periodId":14,"matchStatus":"Played","winner":"draw","matchLengthMin":93,"matchLengthSec":14,"period":[{"id":1,"start":"2016-02-20T12:00:02Z","end":"2016-02-20T12:46:09Z","lengthMin":46,"lengthSec":7},{"id":2,"start":"2016-02-20T13:02:40Z","end":"2016-02-20T13:49:47Z","lengthMin":47,"lengthSec":7}],"scores":{"ht":{"home":1,"away":1},"ft":{"home":2,"away":2},"total":{"home":2,"away":2}}},"goal":[{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","periodId":1,"timeMin":22,"timeMinSec":"21:31","lastUpdated":"2016-08-04T08:27:12Z","type":"G","scorerId":"6bzvqgnj0ld4gerxnpk3w7idx","scorerName":"M. Boland","assistPlayerId":"dik7igz9kbpqrn6urdcw5mtp1","assistPlayerName":"O. Ademi","optaEventId":"32476800","homeScore":0,"awayScore":1},{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":1,"timeMin":29,"timeMinSec":"28:55","lastUpdated":"2017-08-24T12:09:22Z","type":"PG","scorerId":"47wnj6a5qgqa7j7em7sgjij85","scorerName":"Manuel Torres","optaEventId":"1027817148","homeScore":1,"awayScore":1},{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","periodId":2,"timeMin":47,"timeMinSec":"46:51","lastUpdated":"2018-09-25T15:01:34Z","type":"G","scorerId":"9c9bcgsg10k7j2l8n0xh6laz9","scorerName":"S. Decarli","optaEventId":"1905090533","homeScore":1,"awayScore":2},{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":2,"timeMin":65,"timeMinSec":"64:41","lastUpdated":"2020-03-31T19:09:17Z","type":"G","scorerId":"yuw4a34cpasw5e4vqsg6ex1x","scorerName":"D. Diamantakos","assistPlayerId":"b40xhpgxf8cvruo6vumzu3u1h","assistPlayerName":"E. Valentini","optaEventId":"458000905","homeScore":2,"awayScore":2}],"card":[{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":1,"timeMin":35,"timeMinSec":"34:02","lastUpdated":"2016-08-04T07:17:54Z","type":"YC","playerId":"2175hvbfk4jn4lnj3cetfpp1","playerName":"Y. Sallahi","optaEventId":"393368356","cardReason":"Foul"},{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":2,"timeMin":60,"timeMinSec":"59:38","lastUpdated":"2016-08-04T07:17:54Z","type":"Y2C","playerId":"2175hvbfk4jn4lnj3cetfpp1","playerName":"Y. Sallahi","optaEventId":"1245554540","cardReason":"Foul"},{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","periodId":2,"timeMin":83,"timeMinSec":"82:04","lastUpdated":"2019-08-31T02:02:21Z","type":"YC","playerId":"f35u25047d9vmzt6j20fd29cl","playerName":"S. Khelifi","optaEventId":"1149333485","cardReason":"Foul"}],"substitute":[{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":2,"timeMin":57,"timeMinSec":"56:03","lastUpdated":"2020-03-31T19:09:17Z","playerOnId":"yuw4a34cpasw5e4vqsg6ex1x","playerOnName":"D. Diamantakos","playerOffId":"49797zk0b4wmp4tevwmaeeh91","playerOffName":"H. Yamada","subReason":"Tactical"},{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":2,"timeMin":64,"timeMinSec":"63:46","lastUpdated":"2022-07-28T11:01:20Z","playerOnId":"57c808184l80uuuv7z3flcu6t","playerOnName":"G. Prömel","playerOffId":"e5bdaf9c6tsfxrga1zrxeiz85","playerOffName":"G. Krebs","subReason":"Tactical"},{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","periodId":2,"timeMin":67,"timeMinSec":"66:47","lastUpdated":"2022-07-11T09:22:49Z","playerOnId":"2al4ukl1akx2790weoa2p7e8l","playerOnName":"M. Pfitzner","playerOffId":"778pjwtaf4v2ytk8fef3hdtqt","playerOffName":"J. Hochscheidt","subReason":"Tactical"},{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","periodId":2,"timeMin":67,"timeMinSec":"66:52","lastUpdated":"2017-08-24T13:07:11Z","playerOnId":"1gnphfgkgd9fgbf5vu9dekn9x","playerOnName":"P. Schönfeld","playerOffId":"502gg2gz0abul3s4rn3023sgl","playerOffName":"A. Matuszczyk","subReason":"Tactical"},{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","periodId":2,"timeMin":69,"timeMinSec":"68:02","lastUpdated":"2016-02-20T13:26:09Z","playerOnId":"3xdq68t1w4zcksan8e5t7u1h","playerOnName":"S. Traut","playerOffId":"e3bdoni6do5qjgipeya8d932t","playerOffName":"E. Hoffer","subReason":"Tactical"},{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","periodId":2,"timeMin":83,"timeMinSec":"82:42","lastUpdated":"2017-08-24T13:05:34Z","playerOnId":"294c2a3xoi1gg29au703xkbmd","playerOnName":"H. Zuck","playerOffId":"2m7zps8hqiq6o09preuwz8frp","playerOffName":"J. Baffoe","subReason":"Tactical"}],"lineUp":[{"contestantId":"aojwbjr39s1w2mcd9l2bf2dhk","formationUsed":"4231","player":[{"playerId":"evf5jibid9ybzcdi7bp9po0d1","firstName":"Renè","lastName":"Vollath","shortFirstName":"René","shortLastName":"Vollath","matchName":"R. Vollath","shirtNumber":24,"position":"Goalkeeper","positionSide":"Centre","formationPlace":"1"},{"playerId":"2175hvbfk4jn4lnj3cetfpp1","firstName":"Ylli","lastName":"Sallahi","matchName":"Y. Sallahi","shirtNumber":20,"position":"Defender","positionSide":"Left","formationPlace":"3"},{"playerId":"e8dpcth2x1y0zbaovljbgk5hx","firstName":"Manuel","lastName":"Gulde","shortFirstName":"Manuel","shortLastName":"Gulde","matchName":"M. Gulde","shirtNumber":14,"position":"Defender","positionSide":"Left/Centre","formationPlace":"6"},{"playerId":"5os8k5ibx2v9vks50z7wjeeol","firstName":"Martin","lastName":"Stoll","shortFirstName":"Martin","shortLastName":"Stoll","matchName":"M. Stoll","shirtNumber":4,"position":"Defender","positionSide":"Centre/Right","formationPlace":"5"},{"playerId":"b40xhpgxf8cvruo6vumzu3u1h","firstName":"Enrico","lastName":"Valentini","shortFirstName":"Enrico","shortLastName":"Valentini","matchName":"E. Valentini","shirtNumber":22,"position":"Defender","positionSide":"Right","formationPlace":"2"},{"playerId":"apdrig6xt1hxub1986s3uh1x","firstName":"Jonas","lastName":"Meffert","shortFirstName":"Jonas","shortLastName":"Meffert","matchName":"J. Meffert","shirtNumber":23,"position":"Defensive Midfielder","positionSide":"Left/Centre","formationPlace":"4"},{"playerId":"e5bdaf9c6tsfxrga1zrxeiz85","firstName":"Gaëtan","lastName":"Krebs","shortFirstName":"Gaétan","shortLastName":"Krebs","matchName":"G. Krebs","shirtNumber":21,"position":"Defensive Midfielder","positionSide":"Centre/Right","formationPlace":"8"},{"playerId":"264rfp2mhsxw4s9fuvhpdfaol","firstName":"Mohamed","lastName":"Gouaida","shortFirstName":"Mohamed","shortLastName":"Gouaida","matchName":"M. Gouaida","shirtNumber":17,"position":"Attacking Midfielder","positionSide":"Left/Centre"},{"playerId":"49797zk0b4wmp4tevwmaeeh91","firstName":"Hiroki","lastName":"Yamada","shortFirstName":"Hiroki","shortLastName":"Yamada","matchName":"H. Yamada","shirtNumber":10,"position":"Attacking Midfielder","positionSide":"Centre","formationPlace":"10"},{"playerId":"47wnj6a5qgqa7j7em7sgjij85","firstName":"Manuel","lastName":"Torres Jimenez","shortFirstName":"Manuel Torres","shortLastName":"Jiménez","knownName":"Manuel Torres","matchName":"Manuel Torres","shirtNumber":18,"position":"Attacking Midfielder","positionSide":"Centre/Right"},{"playerId":"e3bdoni6do5qjgipeya8d932t","firstName":"Erwin","lastName":"Hoffer","shortFirstName":"Erwin","shortLastName":"Hoffer","matchName":"E. Hoffer","shirtNumber":8,"position":"Striker","positionSide":"Centre","formationPlace":"9"},{"playerId":"5s4anpx21xp59nevhwmsqlsl","firstName":"Boubacar","lastName":"Barry","shortFirstName":"Boubacar","shortLastName":"Barry","matchName":"B. Barry","shirtNumber":15,"position":"Substitute","subPosition":"Midfielder"},{"playerId":"yuw4a34cpasw5e4vqsg6ex1x","firstName":"Dimitrios","lastName":"Diamantakos","shortFirstName":"Dimitrios","shortLastName":"Diamantakos","matchName":"D. Diamantakos","shirtNumber":9,"position":"Substitute","subPosition":"Attacker"},{"playerId":"arpyxey4c28pox7dwd31pmlsl","firstName":"Dimitrij","lastName":"Nazarov","shortFirstName":"Dimitrij","shortLastName":"Nazarov","matchName":"D. Nazarov","shirtNumber":11,"position":"Substitute","subPosition":"Attacker"},{"playerId":"6jerp4x6ruyb5byycmcwmfvth","firstName":"Dirk","lastName":"Orlishausen","shortFirstName":"Dirk","shortLastName":"Orlishausen","matchName":"D. Orlishausen","shirtNumber":1,"position":"Substitute","subPosition":"Goalkeeper"},{"playerId":"c86u8qk3fjl4rhic396nn8ixh","firstName":"Dominic","lastName":"Peitz","shortFirstName":"Dominic","shortLastName":"Peitz","matchName":"D. Peitz","shirtNumber":13,"position":"Substitute","subPosition":"Midfielder"},{"playerId":"57c808184l80uuuv7z3flcu6t","firstName":"Grischa","lastName":"Prömel","shortFirstName":"Grischa","shortLastName":"Prömel","matchName":"G. Prömel","shirtNumber":19,"position":"Substitute","subPosition":"Midfielder"},{"playerId":"3xdq68t1w4zcksan8e5t7u1h","firstName":"Sascha","lastName":"Traut","shortFirstName":"Sascha","shortLastName":"Traut","matchName":"S. Traut","shirtNumber":7,"position":"Substitute","subPosition":"Defender"}],"teamOfficial":{"id":"3u410q38jpg2rpjf00sjy3mxh","firstName":"Markus","lastName":"Kauczinski","type":"manager"}},{"contestantId":"kxpw3rqn4ukt7nqmtjj62lbn","formationUsed":"424","player":[{"playerId":"61xxo4zsk6hby0swa756l3wlx","firstName":"Rafał","lastName":"Gikiewicz","shortFirstName":"Rafal","shortLastName":"Gikiewicz","matchName":"R. Gikiewicz","shirtNumber":33,"position":"Goalkeeper","positionSide":"Centre","formationPlace":"1"},{"playerId":"7snb6fw0mbkrlgxgcbuqaq51","firstName":"Ken","lastName":"Reichel","shortFirstName":"Ken","shortLastName":"Reichel","matchName":"K. Reichel","shirtNumber":19,"position":"Defender","positionSide":"Left","formationPlace":"3"},{"playerId":"2m7zps8hqiq6o09preuwz8frp","firstName":"Joseph","lastName":"Baffoe","shortFirstName":"Joseph","shortLastName":"Baffoe","matchName":"J. Baffoe","shirtNumber":4,"position":"Defender","positionSide":"Left/Centre","formationPlace":"6"},{"playerId":"9c9bcgsg10k7j2l8n0xh6laz9","firstName":"Saulo Igor","lastName":"Decarli","shortFirstName":"Saulo","shortLastName":"Decarli","matchName":"S. Decarli","shirtNumber":3,"position":"Defender","positionSide":"Centre/Right","formationPlace":"5"},{"playerId":"52a5br8e27u4mj5f4m3djjkd1","firstName":"Philemon","lastName":"Ofosu-Ayeh","shortFirstName":"Phil","shortLastName":"Ofosu-Ayeh","matchName":"P. Ofosu-Ayeh","shirtNumber":17,"position":"Defender","positionSide":"Right","formationPlace":"2"},{"playerId":"6bzvqgnj0ld4gerxnpk3w7idx","firstName":"Mirko","lastName":"Boland","shortFirstName":"Mirko","shortLastName":"Boland","matchName":"M. Boland","shirtNumber":10,"position":"Defensive Midfielder","positionSide":"Left/Centre","formationPlace":"4"},{"playerId":"502gg2gz0abul3s4rn3023sgl","firstName":"Adam","lastName":"Matuszczyk","shortFirstName":"Adam","shortLastName":"Matuschyk","matchName":"A. Matuszczyk","shirtNumber":8,"position":"Defensive Midfielder","positionSide":"Centre/Right","formationPlace":"8"},{"playerId":"3nins3wtqjz9vt8q06s087vth","firstName":"Gerrit Stephan","lastName":"Barba Holtmann","shortFirstName":"Gerrit","shortLastName":"Holtmann","matchName":"G. Holtmann","shirtNumber":38,"position":"Attacking Midfielder","positionSide":"Left/Centre"},{"playerId":"778pjwtaf4v2ytk8fef3hdtqt","firstName":"Jan","lastName":"Hochscheidt","shortFirstName":"Jan","shortLastName":"Hochscheidt","matchName":"J. Hochscheidt","shirtNumber":11,"position":"Attacking Midfielder","positionSide":"Centre","formationPlace":"10"},{"playerId":"f35u25047d9vmzt6j20fd29cl","firstName":"Salim","lastName":"Khelifi","shortFirstName":"Salim","shortLastName":"Khelifi","matchName":"S. Khelifi","shirtNumber":22,"position":"Attacking Midfielder","positionSide":"Centre/Right"},{"playerId":"dik7igz9kbpqrn6urdcw5mtp1","firstName":"Orhan","lastName":"Ademi","shortFirstName":"Orhan","shortLastName":"Ademi","matchName":"O. Ademi","shirtNumber":18,"position":"Striker","positionSide":"Centre","formationPlace":"9"},{"playerId":"byab0p1k46q76he4qdotdadp1","firstName":"Julius","lastName":"Düker","shortFirstName":"Julius","shortLastName":"Düker","matchName":"J. Düker","shirtNumber":26,"position":"Substitute","subPosition":"Attacker"},{"playerId":"71h4rx7893dz4aw3lfoz7h05","firstName":"Jasmin","lastName":"Fejzić","shortFirstName":"Jasmin","shortLastName":"Fejzic","matchName":"J. Fejzić","shirtNumber":16,"position":"Substitute","subPosition":"Goalkeeper"},{"playerId":"47wbeeo0jqxvgowrknvwk8lw5","firstName":"Niko","lastName":"Kijewski","shortFirstName":"Niko","shortLastName":"Kijewski","matchName":"N. Kijewski","shirtNumber":27,"position":"Substitute","subPosition":"Defender"},{"playerId":"4qwkz81tf5zv3bopcimbsacb9","firstName":"Nik","lastName":"Omladič","shortFirstName":"Nik","shortLastName":"Omladic","matchName":"N. Omladič","shirtNumber":12,"position":"Substitute","subPosition":"Midfielder"},{"playerId":"2al4ukl1akx2790weoa2p7e8l","firstName":"Marc","lastName":"Pfitzner","shortFirstName":"Marc","shortLastName":"Pfitzner","matchName":"M. Pfitzner","shirtNumber":31,"position":"Substitute","subPosition":"Midfielder"},{"playerId":"1gnphfgkgd9fgbf5vu9dekn9x","firstName":"Patrick","lastName":"Schönfeld","shortFirstName":"Patrick","shortLastName":"Schönfeld","matchName":"P. Schönfeld","shirtNumber":21,"position":"Substitute","subPosition":"Midfielder"},{"playerId":"294c2a3xoi1gg29au703xkbmd","firstName":"Hendrick","lastName":"Zuck","shortFirstName":"Hendrick","shortLastName":"Zuck","matchName":"H. Zuck","shirtNumber":30,"position":"Substitute","subPosition":"Midfielder"}],"teamOfficial":{"id":"95ryh79yvae3qbb5e3rfi7rh1","firstName":"Torsten","lastName":"Lieberknecht","type":"manager"}}],"matchDetailsExtra":{"attendance":"12746","matchOfficial":[{"id":"5jncj8i1pzgcvmkp2zufh8dsl","type":"Main","firstName":"Robert","lastName":"Kampka"},{"id":"efvjrgi8sxkc1n3xzb4iolg45","type":"Lineman 1","firstName":"Tobias","lastName":"Reichel"},{"id":"dklhp3meedvpmy4g3yn1cukt1","type":"Lineman 2","firstName":"Jonas","lastName":"Weickenmeier"},{"id":"3wlgme2da228dbve370hzo7v9","type":"Fourth official","firstName":"Torsten","lastName":"Bauer"}]}}}]}';
        
        $data = json_decode($sample, true);
        
        return $data;
    }
    
    public function matchData($request, $lang, $matchId, $sdata = false) {      
        //get team name with id
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/teams"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $teams = array();
            foreach($mdata as $k => $md) {
                if ($md['language'] === 'en-us') {
                    foreach ($md['data']['contestant'] as $contestant) {
                        $teams[$contestant['id']] = $contestant['name'];
                    }
                }
            }
        }
    
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/matchstats/$matchId"; 
        //$endpoint = "{$this->ipsum_server}/api/v1/sports/opta/matches"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);  
            
            $data = array();
            $msdata = array();
            foreach($mdata as $k => $md) {
                if ($md['id'] === $matchId && $md['language'] === $lang) {
                    $data = $md['data'];
                    
                    if (!isset($data['match'])) { //for matchstats api only
                        $mmdata['match'][0] = $data;
                        $data = $mmdata;
                    }
                    
                    if ($sdata && $data['match'][0]['liveData']['lineUp'] && $data['match'][0]['liveData']['lineUp'][0]['stat']) {                        
                        foreach ($data['match'][0]['liveData']['lineUp'][0]['stat'] as $stat) {
                            if ($stat['type'] === $sdata) {
                                $msdata['T1'] = $stat['value'];
                                break;
                            }
                        }
                        foreach ($data['match'][0]['liveData']['lineUp'][1]['stat'] as $stat) {
                            if ($stat['type'] === $sdata) {
                                $msdata['T2'] = $stat['value'];
                                break;
                            }
                        }
                    }
                    
                    if (isset($data['match'][0]['matchInfo']['contestant'])) {
                        $data['match'][0]['matchInfo']['contestant'][0]['englishName'] = $teams[$data['match'][0]['matchInfo']['contestant'][0]['id']];
                        $data['match'][0]['matchInfo']['contestant'][1]['englishName'] = $teams[$data['match'][0]['matchInfo']['contestant'][1]['id']];
                    }
                    
                    //goal, card, substitute, penaltyShot
                    if (isset($data['match'][0]['liveData']['goal'])) {
                        $matchGoals = array();                        
                        foreach($data['match'][0]['liveData']['goal'] as $goal) {
                            if (!isset($matchGoals[$goal['contestantId']]))
                                $matchGoals[$goal['contestantId']] = 0;
                            $matchGoals[$goal['contestantId']] += 1;
                        }
                        
                        if ($sdata && $sdata === 'matchGoals') {
                            foreach ($matchGoals as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchGoals[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchGoals[$mkey];
                            }
                            return $msdata;
                        }
                        
                        $data['match'][0]['liveData']['matchGoals'] = $matchGoals;
                    }
                    
                    if (isset($data['match'][0]['liveData']['card'])) {
                        $matchYellowCards = array();      
                        $matchSecondYellowCards = array();      
                        $matchRedCards = array();                        
                        foreach($data['match'][0]['liveData']['card'] as $card) {
                            if ($card['type'] === 'YC') {
                                if (!isset($matchYellowCards[$card['contestantId']]))
                                    $matchYellowCards[$card['contestantId']] = 0;
                                $matchYellowCards[$card['contestantId']] += 1;
                            }
                            
                            if ($card['type'] === 'Y2C') {
                                if (!isset($matchSecondYellowCards[$card['contestantId']]))
                                    $matchSecondYellowCards[$card['contestantId']] = 0;
                                $matchSecondYellowCards[$card['contestantId']] += 1;
                            }
                            
                            if ($card['type'] === 'RC') {
                                if (!isset($matchRedCards[$card['contestantId']]))
                                    $matchRedCards[$card['contestantId']] = 0;
                                $matchRedCards[$card['contestantId']] += 1;
                            }
                        }
                        
                        if ($sdata && $sdata === 'matchSecondYellowCards') {
                            foreach ($matchSecondYellowCards as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchSecondYellowCards[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchSecondYellowCards[$mkey];
                            }
                            return $msdata;
                        }
                        
                        if ($sdata && $sdata === 'matchYellowCards') {
                            foreach ($matchYellowCards as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchYellowCards[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchYellowCards[$mkey];
                            }
                            return $msdata;
                        }
                        
                        if ($sdata && $sdata === 'matchRedCards') {
                            foreach ($matchRedCards as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchRedCards[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchRedCards[$mkey];
                            }
                            return $msdata;
                        }
                        
                        $data['match'][0]['liveData']['matchSecondYellowCards'] = $matchSecondYellowCards;
                        $data['match'][0]['liveData']['matchYellowCards'] = $matchYellowCards;
                        $data['match'][0]['liveData']['matchRedCards'] = $matchRedCards;
                    }
                    
                    if (isset($data['match'][0]['liveData']['substitute'])) {
                        $matchSubstitute = array();                        
                        foreach($data['match'][0]['liveData']['substitute'] as $substitute) {
                            if (!isset($matchSubstitute[$substitute['contestantId']]))
                                $matchSubstitute[$substitute['contestantId']] = 0;
                            $matchSubstitute[$substitute['contestantId']] += 1;
                        }
                        
                        if ($sdata && $sdata === 'matchSubstitute') {
                            foreach ($matchSubstitute as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchSubstitute[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchSubstitute[$mkey];
                            }
                            return $msdata;
                        }
                        
                        $data['match'][0]['liveData']['matchSubstitute'] = $matchSubstitute;
                    }    

                    if (isset($data['match'][0]['liveData']['penaltyShot'])) {
                        $matchPenaltyShots = array();
                        $matchPenalyGoals = array();
                        foreach($data['match'][0]['liveData']['penaltyShot'] as $penaltyShot) {
                            if (!isset($matchPenaltyShots[$penaltyShot['contestantId']]))
                                $matchPenaltyShots[$penaltyShot['contestantId']] = 0;
                            $matchPenaltyShots[$penaltyShot['contestantId']] += 1;
                            
                            if ($penaltyShot['outcome'] === 'scored') {
                                if (!isset($matchPenalyGoals[$penaltyShot['contestantId']]))
                                    $matchPenalyGoals[$penaltyShot['contestantId']] = 0;
                                $matchPenalyGoals[$penaltyShot['contestantId']] += 1;
                            }
                        }
                        
                        if ($sdata && $sdata === 'matchPenaltyShots') {
                            foreach ($matchPenaltyShots as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchPenaltyShots[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchPenaltyShots[$mkey];
                            }
                            return $msdata;
                        }
                        
                        if ($sdata && $sdata === 'matchPenalyGoals') {
                            foreach ($matchPenalyGoals as $mkey => $mgoals) {
                                if ($mkey === $data['match'][0]['matchInfo']['contestant'][0]['id'])
                                    $msdata['T1'] = $matchPenalyGoals[$mkey];
                                elseif ($mkey === $data['match'][0]['matchInfo']['contestant'][1]['id'])
                                    $msdata['T2'] = $matchPenalyGoals[$mkey];
                            }
                            return $msdata;
                        }
                        
                        $data['match'][0]['liveData']['matchPenaltyShots'] = $matchPenaltyShots;
                        $data['match'][0]['liveData']['matchPenalyGoals'] = $matchPenalyGoals;
                    }
                    
                    if ($sdata)
                        return $msdata;
                    
                    break;
                }
            } 
            
            return $data;
        }
    }
    
    public function matchSchedule($request, $lang, $date) {
        $lang = 'en-us';  //DC: Language is always english for match list
        $date = substr($date, 0, 10);       
        
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/schedule"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $matchIds = array();
            $ldata = array();
            $i = 0;
            $got_data = false;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    foreach ($md['data']['matchDate'] as $mDate) {
                        if ($mDate['date'] === $date.'Z' || $date === 'all') {
                            foreach ($mDate['match'] as $match) {
                                if (isset($matchIds[$match['id']]) || !isset($match['homeContestantName'])) { //no contestants info yet
                                    continue;
                                }                                
                                $ldata[$i]['id'] = $match['id'];    
                                $matchIds[$match['id']] = 1;
                                
                                $desc = '';                                
                                if ($date === 'all')
                                    $desc .= substr($mDate['date'], 0, -1) . ' - ';
                                $desc .= $match['homeContestantName'] . ' vs ' . $match['awayContestantName'];
                                $ldata[$i]['description'] = $desc;
                                
                                $i++;
                                
                                $got_data = true;
                            }
                            
                            if ($got_data && $date !== 'all') {                                
                                break;
                            }
                        }
                    }
                }
                if ($got_data && $date !== 'all')
                    break;
            }
            
            if ($date === 'all')
               $ldata = array_reverse($ldata);
            
            return $ldata;
        }
    }
    
    //Sample Only
    public function matchList($request) {       
        $endpoint = "{$this->http}ec2-18-233-168-4.compute-1.amazonaws.com:8080/api/sports-soccer-match-sample?api_key=".$request->api_key; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $ldata = array();
            foreach($mdata['match'] as $k => $md) {
                $ldata[$k]['id'] = $md['matchInfo']['id'];
                $ldata[$k]['description'] = $md['matchInfo']['description'];
                $ldata[$k]['contestant'] = $md['matchInfo']['contestant'];
                $ldata[$k]['lineup'] = $md['liveData']['lineUp'];
            }
            
            return $ldata;
        }
    }
    
    //Sample Only
    public function matchLineups($request, $matchId) {       
        $endpoint = "{$this->http}ec2-18-233-168-4.compute-1.amazonaws.com:8080/api/sports-soccer-match-sample?api_key=".$request->api_key; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $ldata = array();
            foreach($mdata['match'] as $k => $md) {
                $ldata[$k]['id'] = $md['matchInfo']['id'];
                $ldata[$k]['description'] = $md['matchInfo']['description'];
                $ldata[$k]['contestant'] = $md['matchInfo']['contestant'];
                $ldata[$k]['lineup'] = $md['liveData']['lineUp'];
            }
            
            return $ldata;
        }
    }
    
    public function standingsStages($request, $lang) {  
        $lang = 'en-us';  //DC: Language is always english for group list
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/standings"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $stages = array();
            $i = 0;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    foreach ($md['data']['stage'] as $stage) {
                        if ($stage['division'] && $stage['division'][0] && $stage['division'][0]['groupName']) {
                            foreach ($stage['division'] as $division) {
                                if ($division['type'] === 'total') {
                                    $stages[$i]['id'] = $stage['id'] . ' - '. $division['groupId'];
                                    $stages[$i]['name'] = $md['data']['tournamentCalendar']['name'] . ' - ' . $stage['name'] . ' - '. $division['groupName'];
                                    $i++;
                                }
                            }
                        }
                        else {                        
                            $stages[$i]['id'] = $stage['id'];
                            $stages[$i]['name'] = $stage['name'];
                            $i++;
                        }
                    }
                }
            }
            
            return $stages;
        }
    }
    
    public function standings($request, $lang, $sstage) {  
        //get team name with id
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/teams"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $teams = array();
            foreach($mdata as $k => $md) {
                if ($md['language'] === 'en-us') {
                    foreach ($md['data']['contestant'] as $contestant) {
                        $teams[$contestant['id']] = $contestant['name'];
                    }
                }
            }
        }
    
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/standings"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $stage_name = '';
            $group_name = '';
            if (preg_match('/ - /', $sstage)) {
                $stage_arr = explode(' - ', $sstage);
                $stage_id = $stage_arr[0];
                $group_id = $stage_arr[1];
            }
            
            $standings = array();
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    foreach ($md['data']['stage'] as $stage) {
                        if ($stage['id'] === $stage_id) {
                            if ($stage['division'] && $stage['division'][0]) {
                                foreach ($stage['division'] as $i => $division) {
                                    if ($division['groupId'] && $division['groupId'] === $group_id && $division['type'] === 'total') {
                                        if ($division['ranking']) {
                                            $standings = $division['ranking'];                                            
                                            foreach ($standings as &$standing) {
                                                $standing['englishName'] = $teams[$standing['contestantId']];
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }        
                }
            }
            
            return $standings;
        }       
    }
    
    public function bracket($request, $lang, $tournament) { 
        //get group ranking first
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/standings"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $standings = array();
            $gRanks = array();
            foreach($mdata as $k => $md) {
                if ($md['language'] === 'en-us' && $md['tournamentName'] === $tournament) {
                    foreach ($md['data']['stage'] as $stage) {
                        foreach ($stage['division'] as $i => $division) {
                            if ($division['type'] === 'total') {
                                $group = str_replace('Group ', '', $division['groupName']);
                                foreach ($division['ranking'] as $ranking) {                                
                                    $gRanks[$ranking['contestantId']] = $ranking['rank'].$group;
                                }
                            }
                        }
                    }     
                    break;
                }
            }
            
            //get team name in different languages
            $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/teams"; 
            $output = $this->d3_curl($endpoint);
            
            if (!$this->isJson($output)) {
                $result['status'] = 'failed';
                $result['message'] = $output;
                return $result;
            }
            else {
                $mdata = json_decode($output, true);   
                
                $teams = array();
                foreach($mdata as $k => $md) {
                    if ($md['language'] !== 'en-us' && $md['tournamentName'] === $tournament) {
                        foreach ($md['data']['contestant'] as $contestant) {
                            $teams[$contestant['id']][$md['language']] = $contestant['name'];
                        }
                    }
                }
            }
                 
            //get brackets
            $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/matches"; 
            $output = $this->d3_curl($endpoint);
            
            if (!$this->isJson($output)) {
                $result['status'] = 'failed';
                $result['message'] = $output;
                return $result;
            }
            else {
                $mdata = json_decode($output, true);   
                
                $bracket = array();
                
                foreach($mdata as $k => $md) {
                    if ($md['language'] === 'en-us' && $md['tournamentName'] === $tournament && isset($md['data']['match'][0]['matchInfo']['contestant'])) {
                        $stage = $md['data']['match'][0]['matchInfo']['stage']['name'];
                        
                        $winner = false;
                        if (isset($md['data']['match'][0]['liveData']['matchDetails']['winner'])) {
                            $winner = $md['data']['match'][0]['liveData']['matchDetails']['winner'];
                        }
                        
                        //8th Finals
                        if ($stage === '8th Finals') {                           
                            for ($i = 0; $i < 2; $i++) {
                                $teamid[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['id'];
                                $teamid[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['id'];
                                $team[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['name']; //use english name for ID
                                $team[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                if ($lang === 'en-us') {
                                    $teamName = $md['data']['match'][0]['matchInfo']['contestant'][$i]['name'];
                                    $t1Name = $md['data']['match'][0]['matchInfo']['contestant'][0]['name'];
                                    $t2Name = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                }
                                else {
                                    $teamName = $teams[$teamid[$i]][$lang];
                                    $t1Name = $teams[$teamid[0]][$lang];
                                    $t2Name = $teams[$teamid[1]][$lang];
                                }
                                
                                if ($gRanks[$teamid[$i]] === '1A') {
                                    $field = 's8thGroupAT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupAResultS'] = $t1Name;
                                        $bracket['s8thGroupAResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupAResultS'] = $t2Name;
                                        $bracket['s8thGroupAResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2B') 
                                    $field = 's8thGroupAT2S';
                                elseif ($gRanks[$teamid[$i]] === '1C') {
                                    $field = 's8thGroupCT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupCResultS'] = $t1Name;
                                        $bracket['s8thGroupCResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupCResultS'] = $t2Name;
                                        $bracket['s8thGroupCResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2D')
                                    $field = 's8thGroupCT2S';
                                elseif ($gRanks[$teamid[$i]] === '1E') {
                                    $field = 's8thGroupET1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupEResultS'] = $t1Name;
                                        $bracket['s8thGroupEResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupEResultS'] = $t2Name;
                                        $bracket['s8thGroupEResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2F')
                                    $field = 's8thGroupET2S';
                                elseif ($gRanks[$teamid[$i]] === '1G') {
                                    $field = 's8thGroupGT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupGResultS'] = $t1Name;
                                        $bracket['s8thGroupGResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupGResultS'] = $t2Name;
                                        $bracket['s8thGroupGResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2H')
                                    $field = 's8thGroupGT2S';
                                elseif ($gRanks[$teamid[$i]] === '1B') {
                                    $field = 's8thGroupBT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupBResultS'] = $t1Name;
                                        $bracket['s8thGroupBResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupBResultS'] = $t2Name;
                                        $bracket['s8thGroupBResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2A')
                                    $field = 's8thGroupBT2S';
                                elseif ($gRanks[$teamid[$i]] === '1D') {
                                    $field = 's8thGroupDT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupDResultS'] = $t1Name;
                                        $bracket['s8thGroupDResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupDResultS'] = $t2Name;
                                        $bracket['s8thGroupDResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2C')
                                    $field = 's8thGroupDT2S';
                                elseif ($gRanks[$teamid[$i]] === '1F') {
                                    $field = 's8thGroupFT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupFResultS'] = $t1Name;
                                        $bracket['s8thGroupFResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupFResultS'] = $t2Name;
                                        $bracket['s8thGroupFResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2E')
                                    $field = 's8thGroupFT2S';
                                elseif ($gRanks[$teamid[$i]] === '1H') {
                                    $field = 's8thGroupHT1S';
                                    if ($winner === 'home') {
                                        $bracket['s8thGroupHResultS'] = $t1Name;
                                        $bracket['s8thGroupHResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['s8thGroupHResultS'] = $t2Name;
                                        $bracket['s8thGroupHResultIDS'] = $team[1];
                                    }
                                }
                                elseif ($gRanks[$teamid[$i]] === '2G')
                                    $field = 's8thGroupHT2S';
                                    
                                $bracket[substr($field, 0, -1).'IDS'] = $team[$i];
                                $bracket[$field] = $teamName;
                            }
                        }
                        elseif ($stage === 'Quarter-finals') {
                            for ($i = 0; $i < 2; $i++) {          
                                $teamid[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['id'];
                                $teamid[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['id'];
                                $team[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['name']; //use english name for ID
                                $team[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                if ($lang === 'en-us') {
                                    $teamName = $md['data']['match'][0]['matchInfo']['contestant'][$i]['name'];
                                    $t1Name = $md['data']['match'][0]['matchInfo']['contestant'][0]['name'];
                                    $t2Name = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                }
                                else {
                                    $teamName = $teams[$teamid[$i]][$lang];
                                    $t1Name = $teams[$teamid[0]][$lang];
                                    $t2Name = $teams[$teamid[1]][$lang];
                                }
                                
                                if (in_array($gRanks[$teamid[$i]], array('1A', '2B'))) {
                                    $field = 'sQuarterGroupAT1S';
                                    if ($winner === 'home') {
                                        $bracket['sQuarterGroupAResultS'] = $t1Name;
                                        $bracket['sQuarterGroupAResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sQuarterGroupAResultS'] = $t2Name;
                                        $bracket['sQuarterGroupAResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1C', '2D'))) 
                                    $field = 'sQuarterGroupAT2S';
                                elseif (in_array($gRanks[$teamid[$i]], array('1E', '2F'))) {
                                    $field = 'sQuarterGroupET1S';
                                    if ($winner === 'home') {
                                        $bracket['sQuarterGroupEResultS'] = $t1Name;
                                        $bracket['sQuarterGroupEResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sQuarterGroupEResultS'] = $t2Name;
                                        $bracket['sQuarterGroupEResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1G', '2H'))) 
                                    $field = 'sQuarterGroupET2S';
                                elseif (in_array($gRanks[$teamid[$i]], array('1B', '2A'))) {
                                    $field = 'sQuarterGroupBT1S';
                                    if ($winner === 'home') {
                                        $bracket['sQuarterGroupBResultS'] = $t1Name;
                                        $bracket['sQuarterGroupBResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sQuarterGroupBResultS'] = $t2Name;
                                        $bracket['sQuarterGroupBResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1D', '2C'))) 
                                    $field = 'sQuarterGroupBT2S';
                                elseif (in_array($gRanks[$teamid[$i]], array('1F', '2E'))) {
                                    $field = 'sQuarterGroupFT1S';
                                    if ($winner === 'home') {
                                        $bracket['sQuarterGroupFResultS'] = $t1Name;
                                        $bracket['sQuarterGroupFResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sQuarterGroupFResultS'] = $t2Name;
                                        $bracket['sQuarterGroupFResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1H', '2G'))) 
                                    $field = 'sQuarterGroupFT2S';
                                    
                                $bracket[substr($field, 0, -1).'IDS'] = $team[$i];
                                $bracket[$field] = $teamName;
                            }
                        }
                        elseif ($stage === 'Semi-finals') {
                            for ($i = 0; $i < 2; $i++) {
                                $teamid[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['id'];
                                $teamid[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['id'];
                                $team[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['name']; //use english name for ID
                                $team[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                if ($lang === 'en-us') {
                                    $teamName = $md['data']['match'][0]['matchInfo']['contestant'][$i]['name'];
                                    $t1Name = $md['data']['match'][0]['matchInfo']['contestant'][0]['name'];
                                    $t2Name = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                }
                                else {
                                    $teamName = $teams[$teamid[$i]][$lang];
                                    $t1Name = $teams[$teamid[0]][$lang];
                                    $t2Name = $teams[$teamid[1]][$lang];
                                }
                                
                                if (in_array($gRanks[$teamid[$i]], array('1A', '2B', '1C', '2D'))) {
                                    $field = 'sSemiGroupAT1S';
                                    if ($winner === 'home') {
                                        $bracket['sSemiGroupAResultS'] = $t1Name;
                                        $bracket['sSemiGroupAResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sSemiGroupAResultS'] = $t2Name;
                                        $bracket['sSemiGroupAResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1E', '2F', '1G', '2H'))) 
                                    $field = 'sSemiGroupAT2S';
                                elseif (in_array($gRanks[$teamid[$i]], array('1B', '2A', '1D', '2C'))) {
                                    $field = 'sSemiGroupBT1S';
                                    if ($winner === 'home') {
                                        $bracket['sSemiGroupBResultS'] = $t1Name;
                                        $bracket['sSemiGroupBResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sSemiGroupBResultS'] = $t2Name;
                                        $bracket['sSemiGroupBResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1F', '2E', '1H', '2G'))) 
                                    $field = 'sSemiGroupBT2S';
                                    
                                $bracket[substr($field, 0, -1).'IDS'] = $team[$i];
                                $bracket[$field] = $teamName;
                            }
                        }
                        elseif ($stage === 'Final') {
                            for ($i = 0; $i < 2; $i++) {
                                $teamid[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['id']; 
                                $teamid[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['id'];
                                $team[0] = $md['data']['match'][0]['matchInfo']['contestant'][0]['name'];  //use english name for ID
                                $team[1] = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                if ($lang === 'en-us') {
                                    $teamName = $md['data']['match'][0]['matchInfo']['contestant'][$i]['name'];
                                    $t1Name = $md['data']['match'][0]['matchInfo']['contestant'][0]['name'];
                                    $t2Name = $md['data']['match'][0]['matchInfo']['contestant'][1]['name'];
                                }
                                else {
                                    $teamName = $teams[$teamid[$i]][$lang];
                                    $t1Name = $teams[$teamid[0]][$lang];
                                    $t2Name = $teams[$teamid[1]][$lang];
                                }
                                
                                if (in_array($gRanks[$teamid[$i]], array('1A', '2B', '1C', '2D', '1E', '2F', '1G', '2H'))) {
                                    $field = 'sFinalGroupAT1S';
                                    if ($winner === 'home') {
                                        $bracket['sFinalGroupAResultS'] = $t1Name;
                                        $bracket['sFinalGroupAResultIDS'] = $team[0];
                                    }
                                    elseif ($winner === 'away') {
                                        $bracket['sFinalGroupAResultS'] = $t2Name;
                                        $bracket['sFinalGroupAResultIDS'] = $team[1];
                                    }
                                }
                                elseif (in_array($gRanks[$teamid[$i]], array('1B', '2A', '1D', '2C', '1F', '2E', '1H', '2G'))) 
                                    $field = 'sFinalGroupAT2S';
                                    
                                $bracket[substr($field, 0, -1).'IDS'] = $team[$i];
                                $bracket[$field] = $teamName;
                            }
                        }                        
                    }
                }
                
                return $bracket;
            }
        }       
    }
    
    public function venues($request, $lang) {   
        $lang = 'en-us';  //DC: Language is always english for venue list
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/venues"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $venues = array();
            $i = 0;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    foreach ($md['data']['venue'] as $venue) {
                        $name = '';
                        if ($venue['countryCode'] === 'RUS')
                            $name = "2018 $venue[country] - ";
                        elseif ($venue['countryCode'] === 'QAT')
                            $name = "2022 $venue[country] - ";
                        
                        $venues[$i]['id'] = $venue['id'];
                        $venues[$i]['name'] = $name . $venue['name'];
                        $i++;
                    }
                }
            }
            
            return $venues;
        }
    }
    
    public function venueData($request, $lang, $venueId) {       
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/venues"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $vdata = array();
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    foreach ($md['data']['venue'] as $venue) {
                        if ($venue['id'] === $venueId) {
                            $vdata = $venue;                            
                            $vdata['mapsGeoCodeLatLong'] = $venue['mapsGeoCodeLatitude'] . ', ' . $venue['mapsGeoCodeLongitude'];                            
                            $vdata['location'] = (isset($venue['address']) ? $venue['address'] . ', ' : '') . $venue['city'];                            
                            break;
                        }
                    }
                }
            }
            
            return $vdata;    
        }
    }
    
    public function teams($request, $lang) {       
        $lang = 'en-us';  //DC: Language is always english for team list
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/teams"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $teams = array();
            $all['2018'] = false;
            $all['2022'] = false;
            $i = 0;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    $tournamentName = $md['tournamentName'];
                    $tournamentId = $md['id'];
                    $tournamentYear = substr($tournamentName, 0, 4);
                    
                    if (!$all[$tournamentYear]) {
                        $all[$tournamentYear] = true;
                        $teams[$i]['id'] = $tournamentId . '-all';
                        $teams[$i]['name'] = $tournamentName . ' - All';
                        $i++;
                    }                        
                    
                    foreach ($md['data']['contestant'] as $team) {                            
                        $teams[$i]['id'] = $tournamentId . '-' . $team['id'];
                        $teams[$i]['name'] = $tournamentName . ' - ' . $team['name'];
                        $i++;
                    }
                }
            }
            
            usort($teams, function($a, $b) { // anonymous function
                // compare numbers only
                return strcmp($a["name"], $b["name"]);
            });
            
            return $teams;
        }
    }
    
    public function teams_old($request, $lang) {       
        $lang = 'en-us';  //DC: Language is always english for team list
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/topperformers"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $mdata = json_decode($output, true);   
            
            $teams = array();
            $all['2018'] = false;
            $all['2022'] = false;
            $i = 0;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    if (isset($md['data']['teamTopPerformers']['ranking'][0]['team'])) {
                        $tournamentName = $md['data']['tournamentCalendar']['name'];
                        $tournamentId = $md['data']['tournamentCalendar']['id'];
                        $tournamentYear = substr($md['data']['tournamentCalendar']['startDate'], 0, 4);
                        
                        if (!$all[$tournamentYear]) {
                            $all[$tournamentYear] = true;
                            $teams[$i]['id'] = $tournamentId . '-all';
                            $teams[$i]['name'] = $tournamentName . ' - All';
                            $i++;
                        }
                            
                        
                        foreach ($md['data']['teamTopPerformers']['ranking'][0]['team'] as $team) {                            
                            $teams[$i]['id'] = $tournamentId . '-' . $team['id'];
                            $teams[$i]['name'] = $tournamentName . ' - ' . $team['name'];
                            $i++;
                        }
                    }
                }
            }
            
            usort($teams, function($a, $b) { // anonymous function
                // compare numbers only
                return strcmp($a["name"], $b["name"]);
            });
            
            return $teams;
        }
    }
    
    public function players($request, $lang, $tournament_team) {       
        $lang = 'en-us';  //DC: Language is always english for player list
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/playercareer"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $stournament = explode('-', $tournament_team)[0];
            $steam = explode('-', $tournament_team)[1];
            
            $mdata = json_decode($output, true); 

            $tournamentNames['2022 Qatar'] = '2a8elwzsufmguwymxbshcx756';
            $tournamentNames['2018 Russia'] = 'bkvkz42omnou98nkjgb3dh0b9';
            
            $players = array();
            $i = 0;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    //$tournamentName = $md['tournamentName'];
                    //$tournamentId = $tournamentNames[$tournamentName];
                    
                    //DC: ignoring tournament becoz api is ignoring tournament
                    if (1 || $tournamentId === $stournament || $stournament === 'all') {  
                        $player = $md['data'];
                        $player_name = isset($player['shortFirstName']) ? $player['shortFirstName'] . ' ' . $player['shortLastName'] : $player['matchName'];
                    
                        if ($stournament === 'all') {
                            $players[$i]['id'] = $stournament . '-' . $player['id'];
                            $players[$i]['name'] = /*$tournamentName . ' - ' . */$player['membership'][0]['contestantName'] . ' - ' . $player_name;
                            $i++;
                        }
                        elseif ($steam === 'all') {
                            $players[$i]['id'] = $stournament . '-' . $player['id'];
                            $players[$i]['name'] = $player['membership'][0]['contestantName'] . ' - ' . $player_name;
                            $i++;
                        }
                        elseif ($player['membership'][0]['contestantId'] === $steam) {
                            $players[$i]['id'] = $stournament . '-' . $player['id'];
                            $players[$i]['name'] = $player_name;
                            $i++;
                        }
                    }
                }
            }
            
            usort($players, function($a, $b) { // anonymous function
                // compare numbers only
                return strcmp($a["name"], $b["name"]);
            });
            
            return $players;
        }
    }
    
    public function players_old($request, $lang, $tournament_team) {       
        $lang = 'en-us';  //DC: Language is always english for player list
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/topperformers"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $stournament = explode('-', $tournament_team)[0];
            $steam = explode('-', $tournament_team)[1];
            
            $mdata = json_decode($output, true);   
            
            $players = array();
            $i = 0;
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    if (isset($md['data']['teamTopPerformers']['ranking'][0]['team'])) {
                        $tournamentId = $md['data']['tournamentCalendar']['id'];
                        
                        if ($tournamentId === $stournament || $stournament === 'all') {                            
                            foreach ($md['data']['playerTopPerformers']['ranking'][0]['player'] as $player) {   
                                if ($stournament === 'all') {
                                    $tournamentName = $md['data']['tournamentCalendar']['name'];
                                    $players[$i]['id'] = $stournament . '-' . $player['id'];
                                    $players[$i]['name'] = $tournamentName . ' - ' . $player['contestantName'] . ' - ' . $player['shortFirstName'] . ' ' . $player['shortLastName'];
                                    $i++;
                                }
                                elseif ($steam === 'all') {
                                    $players[$i]['id'] = $stournament . '-' . $player['id'];
                                    $players[$i]['name'] = $player['contestantName'] . ' - ' . $player['shortFirstName'] . ' ' . $player['shortLastName'];
                                    $i++;
                                }
                                elseif ($player['contestantId'] === $steam) {
                                    $players[$i]['id'] = $stournament . '-' . $player['id'];
                                    $players[$i]['name'] = $player['shortFirstName'] . ' ' . $player['shortLastName'];
                                    $i++;
                                }
                            }
                            
                            if ($stournament !== 'all')
                                break;
                        }
                    }
                }
            }
            
            usort($players, function($a, $b) { // anonymous function
                // compare numbers only
                return strcmp($a["name"], $b["name"]);
            });
            
            return $players;
        }
    }
    
    public function playerStats($request, $lang, $tournament_player, $data = false) {       
        $stournament = explode('-', $tournament_player)[0];
        $splayer = explode('-', $tournament_player)[1];
    
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/playercareer/$splayer"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {           
            $infos = array('goals', 'assists', 'penaltyGoals', 'appearances', 'yellowCards', 'secondYellowCards', 'redCards', 'substituteIn', 'substituteOut', 'minutesPlayed');
            $pinfos = array('shortFirstName', 'shortLastName', 'position', 'nationality', 'dateOfBirth', 'height', 'weight', 'foot');
            
            if ($data) {                
                if (in_array($data, $infos))
                    $infos = array($data);
                else
                    $infos = array();
                
                if (in_array($data, $pinfos))
                    $pinfos = array($data);
                else
                    $pinfos = array();
            }
            
            $mdata = json_decode($output, true);   
            
            $stats = array();
            $shirtNum = array();
            
            //get english contestant name as contestant id
            if (!$data) {
                $contestantId = '';
                foreach($mdata as $k => $md) {
                    if ($md['language'] === 'en-us') {      
                        $contestantId = str_replace(' ', '', $md['data']['membership'][0]['contestantName']);
                        break;
                    }
                }
            }
            
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {                                             
                    $player = $md['data'];
                    
                    foreach ($player['membership'][0]['stat'] as $stat) {
                        foreach ($infos as $info) {
                            if (!isset($stats[$info]))
                                $stats[$info] = 0;
                            if (isset($stat[$info]))
                                $stats[$info] += $stat[$info];
                        }
                        
                        if ($stat['tournamentCalendarId'] === 'bkvkz42omnou98nkjgb3dh0b9')  //2018 Russia
                            $shirtNum['2018'] = isset($stat['shirtNumber']) ? $stat['shirtNumber'] : '';
                        if ($stat['tournamentCalendarId'] === '2a8elwzsufmguwymxbshcx756')  //2022 Qatar
                            $shirtNum['2022'] = isset($stat['shirtNumber']) ? $stat['shirtNumber'] : '';
                        
                        
                        if (!$data && $stournament === $stat['tournamentCalendarId'] && isset($stat['shirtNumber']))
                            $stats['shirtNumber'] = $stat['shirtNumber'];
                    }                   
                    
                    foreach($pinfos as $pinfo) {
                        if ($pinfo === 'shortFirstName' && $lang !== 'en-us')
                            $stats[$pinfo] = $player['firstName'];
                        elseif ($pinfo === 'shortLastName' && $lang !== 'en-us')
                            $stats[$pinfo] = $player['lastName'];
                        else
                            $stats[$pinfo] = isset($player[$pinfo]) ? $player[$pinfo] : '';
                    }
                    
                    if (!$data) {
                        $stats['englishName'] = $player['shortFirstName']  . ' ' . $player['shortLastName'];
                        $stats['contestantName'] = $player['membership'][0]['contestantName'];
                        $stats['contestantId'] = $contestantId;
                        $stats['tournamentId'] = $stournament;
                        $stats['playerId'] = $player['id'];
                        
                        if (!isset($stats['shirtNumber'])) {
                            if (isset($shirtNum['2022']) && $shirtNum['2022'])
                                $stats['shirtNumber'] = $shirtNum['2022'];
                            elseif (isset($shirtNum['2018']) && $shirtNum['2018'])
                                $stats['shirtNumber'] = $shirtNum['2018'];
                        }
                    }
                    
                    break;
                }
            }
            
            foreach ($infos as $info) {
                if (!isset($stats[$info])) 
                    $stats[$info] = 0;
            }
            
            return $stats;
        }
    }
    
    public function playerStats_old($request, $lang, $tournament_player, $data = false) {       
        $endpoint = "{$this->ipsum_server}/api/v1/sports/opta/topperformers"; 
        $output = $this->d3_curl($endpoint);
        
        if (!$this->isJson($output)) {
            $result['status'] = 'failed';
            $result['message'] = $output;
            return $result;
        }
        else {
            $stournament = explode('-', $tournament_player)[0];
            $splayer = explode('-', $tournament_player)[1];
            
            $infos = array('TotalGames', 'Goals', 'Assists');
            $pinfos = array('shortFirstName', 'shortLastName', 'contestantName');
            
            if ($data) {                
                if (in_array($data, $infos))
                    $infos = array($data);
                else
                    $infos = array();
                $pinfos = array();
            }
            
            $mdata = json_decode($output, true);   
            
            $stats = array();
            foreach($mdata as $k => $md) {
                if ($md['language'] === $lang) {
                    if (isset($md['data']['teamTopPerformers']['ranking'][0]['team'])) {
                        $tournamentId = $md['data']['tournamentCalendar']['id'];
                        
                        if ($tournamentId === $stournament) {                            
                            foreach ($md['data']['playerTopPerformers']['ranking'] as $ranking) { 
                                if (in_array($ranking['name'], $infos)) {
                                    foreach ($ranking['player'] as $player) {
                                        if ($player['id'] === $splayer) {
                                            $stats[$ranking['name']] = $player['value'];
                                            
                                            foreach($pinfos as $pinfo) {
                                                if (!isset($stats[$pinfo]))
                                                    $stats[$pinfo] = $player[$pinfo];
                                            }
                                            
                                            break;
                                        }
                                    }                                        
                                }
                            }
                            
                            break;
                        }
                    }
                }
            }
            
            foreach ($infos as $info) {
                if (!isset($stats[$info])) 
                    $stats[$info] = 0;
            }
            
            return $stats;
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
