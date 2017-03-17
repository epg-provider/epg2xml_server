#!/usr/bin/env php
<?php
define("VERSION", "1.1.3");

# Set My Configuration
$default_icon_url = ""; # TV channel icon url (ex : http://www.example.com/Channels)
$default_rebroadcast = "y"; # 제목에 재방송 정보 출력
$default_episode = "n"; # 제목에 회차정보 출력
$default_verbose = "n"; # 자세한 epg 데이터 출력
$default_fetch_limit = 2; # epg 데이터 가져오는 기간
$default_xml_filename = "xmltv.xml"; # epg 저장시 기본 저장 이름 (ex: /home/tvheadend/xmltv.xml)
$default_xml_socket = "xmltv.sock"; # External XMLTV 사용시 기본 소켓 이름 (ex: /home/tvheadend/xmltv.sock)

# Set variable
$debug = False;
$ua = "'User-Agent': 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36', 'accept': '*/*'";
define("CHANNEL_ERROR", " 존재하지 않는 채널입니다.");
define("CONTENT_ERROR ", " EPG 정보가 없습니다.");
define("HTTP_ERROR", " EPG 정보를 가져오는데 문제가 있습니다.");
define("SOCKET_ERROR", "xmltv.sock 파일을 찾을 수 없습니다.");
define("JSON_FILE_ERROR", " Channel.json 파일을 읽을 수 없습니다.");
define("JSON_SYNTAX_ERROR",  "Channel.json 파일 형식이 잘못되었습니다.");
//사용방법
$usage = <<<USAGE
usage: epg2xml.php [-h] -i {KT,LG,SK}
                  (-v | -d | -o [xmltv.xml] | -s [xmltv.sock]) [-l 1-7]
                  [--icon http://www.example.com/icon] [--verbose y, n]

USAGE;
//도움말
$help = <<<HELP
usage: epg2xml.py [-h] -i {KT,LG,SK}
                  (-v | -d | -o [xmltv.xml] | -s [xmltv.sock]) [-l 1-7]
                  [--icon http://www.example.com/icon] [--verbose y, n]

EPG 정보를 출력하는 방법을 선택한다

optional arguments:
  -h, --help            show this help message and exit
  -v, --version         show programs version number and exit
  -d, --display         EPG 정보 화면출력
  -o [xmltv.xml], --outfile [xmltv.xml]       EPG 정보 저장
  -s [xmltv.sock], --socket [xmltv.sock]      xmltv.sock(External: XMLTV)로 EPG정보 전송

  IPTV 선택

  -i {KT,LG,SK}         사용하는 IPTV : KT, LG, SK

추가옵션:
  -l 1-7, --limit 1-7   EPG 정보를 가져올 기간, 기본값: 2
  --icon http://www.example.com/icon
                        채널 아이콘 URL, 기본값:
  --verbose y, n        EPG 정보 추가 출력

HELP;

//옵션 처리
$shortargs  = "";
$shortargs .= "i:";
$shortargs .= "v";
$shortargs .= "d";
$shortargs .= "o:s:";
$shortargs .= "l:";
$shortargs .= "h";

$longargs  = array(
    "version",
    "display",
    "outfile:",
    "socket:",
    "limit::",
    "icon:",
    "episode:",
    "rebroadcast:",
    "verbose:",
    "help"
);
$args = getopt($shortargs, $longargs);

if($args['h'] === False || $args['help'] === False)://도움말 출력
    print($help);
    exit;
elseif($args['v'] === False || $args['version'] === False)://버전 정보 출력
    printf("epg2xml.php version : %s\n", VERSION);
    exit;
else :
    if(empty($args['i'])) : //ISP 선택없을 시 사용법 출력
        print($usage);
        print("epg2xml.php: error: argument -i: expected one argument\n");
        exit;
    else :
        if(in_array($args['i'], array("KT", "LG", "SK"))) : //ISP 선택
            $MyISP = $args['i'];
            if($args['d'] === False || $args['display'] === False ) :
                if($args['o'] || $args['outfile'] || $args['s'] || $args['socket']) :
                    print($usage);
                    print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                    exit;
                endif;
                $output = "display";
            elseif(empty($args['o']) === False || empty($args['outfile']) === False) :
                if($args['d'] === False || $args['display'] === False || $args['s'] || $args['socket']) :
                    print($usage);
                    print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                    exit;
                endif;
                $output = "file";
                $outfile = $args['o'] ?: $args['outfile'];
            elseif(empty($args['s']) === False || empty($args['socket']) === False) :
                if($args['d'] === False || $args['display'] === False || $args['o'] || $args['outfile']) :
                    print($usage);
                    print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                    exit;
                endif;
                $output = "socket";
                $socket = $args['s'] ?: $args['socket'];
            else :
                print($usage);
                print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                exit;
            endif;
            if(empty($args['l']) === False || empty($args['limit']) === False) :
                if(in_array($args['l'], array(1, 2, 3, 4, 5, 6, 7)) || in_array($args['limit'], array(1, 2, 3, 4, 5, 6, 7))) :
                    $period = $args['l'] ?: $args['limit'];
                else :
                    print($usage);
                    print("epg2xml.php: error: argument -l/--limit: invalid choice: ".$args['l']." (choose from 1, 2, 3, 4, 5, 6, 7)\n");
                    exit;
                endif;
            endif;
            if(empty($args['icon']) === False) :
                $IconUrl = $args['icon'];
            endif;
            if(empty($args['episode']) === False) :
                if(in_array($args['episode'], array("y", "n"))) :
                    $episode = $args['episode'];
                else :
                    print($usage);
                    print("epg2xml.php: argument --episode: invalid choice: 'a' (choose from 'y', 'n')\n");
                    exit;
                endif;
             endif;
            if(empty($args['rebroadcast']) === False) :
                if(in_array($args['rebroadcast'], array("y", "n"))) :
                    $rebroadcast = $args['rebroadcast'];
                else :
                    print($usage);
                    print("epg2xml.php: argument --rebroadcast: invalid choice: 'a' (choose from 'y', 'n')\n");
                    exit;
                endif;
             endif;
            if(empty($args['verbose']) === False) :
                if(in_array($args['verbose'], array("y", "n"))) :
                    $verbose = $args['verbose'];
                else :
                    print($usage);
                    print("epg2xml.php: argument --verbose: invalid choice: 'a' (choose from 'y', 'n')\n");
                    exit;
                endif;
             endif;
        else :
            print($usage);
            print("epg2xml.php: error: argument -i: invalid choice: '".$args1['i']."' (choose from 'KT', 'LG', 'SK')\n");
            exit;
        endif;
    endif;
endif;
// 옵션 처리
$period = $period ?: $default_fetch_limit;
$IconUrl = $IconUrl ?: $default_icon_url;
$addepisode = $episode ?: $default_episode;
$addrebroadcast = $rebroadcast ?: $default_rebroadcast;
$verbose = $verbose ?: $default_verbose;
if($outfile) :
    $outfile = $outfile ?: $default_xml_filename;
elseif($socket):
    $socket = $socket ?: $default_xml_socket;
endif;

getEpg();

function getEPG() {
    $MyISP = $GLOBALS['MyISP'];
    $Channelfile = __DIR__."/Channel.json";
    try {
        $f = @file_get_contents($Channelfile); // Read Channel Information file
        if($f === False) :
            throw new Exception(JSON_FILE_ERROR);
        else :
            try {
                $Channeldatas = json_decode($f, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
            }
            catch(Exception $e) {
                printError($e->getMessage());
                exit;
            }
        endif;
    }
    catch(Exception $e) {
        printError($e->getMessage());
        exit;
    }

    printf("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    printf("<!DOCTYPE tv SYSTEM \"xmltv.dtd\">\n\n");

    printf("<tv generator-info-name=\"epg2xml.py %s\">\n", VERSION);

    foreach ($Channeldatas as $Channeldata) : #Get Channel & Print Channel info
        if($Channeldata['Enabled'] == 1) :
            $ChannelId = $Channeldata['Id'];
            $ChannelName = $Channeldata['Name'];
            $ChannelSource = $Channeldata['Source'];
            $ChannelServiceId = $Channeldata['ServiceId'];
            $ChannelISPName = "[".$Channeldata[$MyISP.'Ch']."]".$Channeldata[$MyISP." Name"];
            $ChannelIconUrl = $Channeldata['Icon_url'];

            if($Channeldata[$MyISP.'Ch'] != Null):
                $ChannelInfos[] = array($ChannelId,  $ChannelName, $ChannelSource, $ChannelServiceId);
                printf("  <channel id=\"%s\">\n", $ChannelId);
                printf("    <display-name>%s</display-name>\n", $ChannelName);
                printf("    <display-name>%s</display-name>\n", $ChannelISPName);
                if($IconUrl) :
                    printf("    <icon src=\"%s/%s.png\" />\n", $IconUrl, $ChannelId);
                else :
                    printf("    <icon src=\"%s\" />\n", $ChannelIconUrl);
                endif;
                printf("  </channel>\n");
            endif;
        endif;
    endforeach;

    # Print Program Information
    foreach ($ChannelInfos as $ChannelInfo) :
        $ChannelId = $ChannelInfo[0];
        $ChannelName =  $ChannelInfo[1];
        $ChannelSource =  $ChannelInfo[2];
        $ChannelServiceId =  $ChannelInfo[3];
        if($GLOBALS['debug']) printLog($ChannelName.' 채널 EPG 데이터를 가져오고 있습니다');
        if($ChannelSource == 'EPG') :
            //GetEPGFromEPG($ChannelInfo);
        elseif($ChannelSource == 'KT') :
            //GetEPGFromKT($ChannelInfo);
        elseif($ChannelSource == 'LG') :
            GetEPGFromLG($ChannelInfo);
        elseif($ChannelSource == 'SK') :
            //GetEPGFromSK($ChannelInfo);
        elseif($ChannelSource == 'SKY') :
            GetEPGFromSKY(ChannelInfo);
        elseif($ChannelSource == 'NAVER') :
            GetEPGFromNaver(ChannelInfo);
        endif;
    endforeach;
    print("</tv>\n");
}
function GetEPGFromEPG($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'user-agent' => $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://www.epg.co.kr/epg-cgi/extern/cnm_guide_type_v070530.cgi";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $params = array(
            'beforegroup' => '100',
            'checkchannel' => $ServiceId,
            'select_group' => '100',
            'start_date' => $day
        );
        $params = http_build_query($params);
        $url = $url."?".$params;
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                throw new Exception ($ChannelName.HTTP_ERROR);
            else :
                $response = str_replace("charset=euc-kr", "charset=utf-8", $response);
                $dom = new DomDocument;
                libxml_use_internal_errors(true);
                $dom->loadHTML(mb_convert_encoding($response, "UTF-8", "EUC-KR"));
                $xpath = new DomXPath($dom);
                for($i = 2; $i < 5; $i++) :
                    $thisday = $day;
                    $query = "//table[contains(@style,'margin-bottom:30')][".$i."]//td[contains(@colspan,'2')]/following::td[1]/table[1]//td[2]";
                    $programs = $xpath->query($query);
                    foreach($programs as $program) :
                        $hour = $xpath->query("parent::*/parent::*/parent::*/parent::*/td[1]", $program)->item(0);
                        $hour = str_replace("시", "", trim($hour->nodeValue));
                        $minute = $xpath->query("preceding-sibling::td[1]", $program)->item(0);
                        $hour = $hour.":".str_replace(array("[", "]"), array("",""), trim($minute->nodeValue));
                        switch ($i) :
                            case 2 :
                                $hour = $hour." AM";
                                break;
                            case 3 :
                                $hour = $hour." PM";
                                break;
                            case 4 :
                                if($hour > 5 ) :
                                    $hour = $hour." PM";
                                else :
                                    $hour = $hour." AM";
                                    $thisday = date("Ymd", strtotime("+1 days"));
                                endif;
                                break;
                        endswitch;
                        $startTime = date("YmdHis", strtotime($thisday." ".$hour));
                        preg_match('/<td height="25" valign="top">?(.*<a.*?">)?(.*?)\s*(&lt;(.*)&gt;)?\s*(\(재\))?\s*(\(([\d,]+)회\))?(<img.*?)?(<\/a>)?\s*<\/td>/', trim($dom->saveHTML($program)), $matches);
                        if ($matches != NULL) :
                            $image = $matches[8] ? $matches[8] : "";
                            preg_match('/.*schedule_([\d,]+)?.*/', $image, $grade);
                            if($grade != NULL) : 
                                $rating = $grade[1];
                            else :
                                $rating = 0;
                            endif;
                        endif;
                            #programName, startTime, rating, subprogramName, rebroadcast, episode
                            $epginfo[] = array(trim($matches[2]), $startTime, $rating, trim($matches[4]), $matches[5], $matches[7]);
                    endforeach;
                endfor;
                $zipped = array_slice(array_map(NULL, $epginfo, array_slice($epginfo,1)),0,-1);
                foreach($zipped as $epg) :
                    $programName = $epg[0][0] ?: "";
                    $subprogramName = $epg[0][3] ?: "";
                    $startTime = $epg[0][1] ?: "";
                    $endTime = $epg[1][1] ?: "";
                    $desc = "";
                    $actors = "";
                    $producers = "";
                    $category = "";
                    $rebroadcast = $epg[0][4] ? True : False;
                    $episode = $epg[0][5] ?: "";
                    $rating = $epg[0][2] ?: 0;
                    $programdata = array(
                        'channelId'=> $ChannelId,
                        'startTime' => $startTime,
                        'endTime' => $endTime,
                        'programName' => $programName,
                        'subprogramName'=> $subprogramName,
                        'desc' => $desc,
                        'actors' => $actors,
                        'producers' => $producers,
                        'category' => $category,
                        'episode' => $episode,
                        'rebroadcast' => $rebroadcast,
                        'rating' => $rating
                    );
                    writeProgram($programdata);
                endforeach;
            endif;
        } catch (Exception $e) {
            printError($e->getMessage());
        }
    endforeach;
}
function GetEPGFromKT($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'user-agent' => $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://tv.olleh.com/renewal_sub/liveTv/pop_schedule_week.asp";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $params = array(
            'ch_name' => '',
            'ch_no' => $ServiceId,
            'nowdate'=> $day,
            'seldatie' => $day,
            'tab_no' => '1'
        );
        $params = http_build_query($params);
        $url = $url."?".$params;
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                throw new Exception ($ChannelName.HTTP_ERROR);
            else :
                $response = str_replace("charset=euc-kr", "charset=utf-8", $response);
                $dom = new DomDocument;
                libxml_use_internal_errors(true);
                $dom->loadHTML(mb_convert_encoding($response, "UTF-8", "EUC-KR"));
                $xpath = new DomXPath($dom);
                $query = "//table[@id='pop_day']/tbody/tr";
                $rows = $xpath->query($query);
                foreach($rows as $row) :
                    $cells = $row->getElementsByTagName('td');
                    #programName, startTime, rating, category
                    $startTime = date("YmdHis", strtotime($day." ".trim($cells[0]->nodeValue)));
                    $rating = str_replace("all", 0, str_replace("세 이상", "", trim($cells[2]->nodeValue)));
                    $epginfo[]= array(trim($cells[1]->nodeValue), $startTime, $rating, trim($cells[4]->nodeValue));
                endforeach;
                $zipped = array_slice(array_map(NULL, $epginfo, array_slice($epginfo,1)),0,-1);
                foreach($zipped as $epg) :
                    $programName = $epg[0][0] ?: "";
                    $subprogramName = "";
                    $startTime = $epg[0][1] ?: "";
                    $endTime = $epg[1][1] ?: "";
                    $desc = "";
                    $actors = "";
                    $producers = "";
                    $category = $epg[0][3] ?: "";
                    $rebroadcast = False;
                    $episode = "";
                    $rating = $epg[0][2] ?: 0;
                    $programdata = array(
                        'channelId'=> $ChannelId,
                        'startTime' => $startTime,
                        'endTime' => $endTime,
                        'programName' => $programName,
                        'subprogramName'=> $subprogramName,
                        'desc' => $desc,
                        'actors' => $actors,
                        'producers' => $producers,
                        'category' => $category,
                        'episode' => $episode,
                        'rebroadcast' => $rebroadcast,
                        'rating' => $rating
                    );
                    writeProgram($programdata);
                endforeach;
            endif;
        } catch (Exception $e) {
            printError($e->getMessage());
        }
    endforeach;
}
function GetEPGFromLG($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'user-agent' => $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://www.uplus.co.kr/css/chgi/chgi/RetrieveTvSchedule.hpi";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $params = array(
            'chnlCd' => $ServiceId,
            'evntCmpYmd' =>  $day
        );
        $params = http_build_query($params);
        $url = $url."?".$params;

        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                throw new Exception ($ChannelName.HTTP_ERROR);
            else :
                $response = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'.$response;
                $dom = new DomDocument;
                libxml_use_internal_errors(true);
                $response = mb_convert_encoding($response, "UTF-8", "EUC-KR");
                $response = str_replace(array('<재>', ' [..', ' (..'), array('&lt;재&gt;', '', ''), $response);
                $dom->loadHTML($response);
                $xpath = new DomXPath($dom);
                $query = "//table[@class='datatable06 datatable06_type01']/tbody/tr";
                $rows = $xpath->query($query);

                foreach($rows as $row) :
                    $cells = $row->getElementsByTagName('td');
                    $startTime = date("YmdHis", strtotime($day." ".trim($cells[0]->nodeValue)));
                    $images = $cells[1]->getElementsByTagName('img');
                    $rating = 0;
                    foreach($images as $image) :
                        if(preg_match('/(\d+)세이상 관람가/', $image->attributes->getNamedItem('alt')->nodeValue, $ratings)) $rating = $ratings[1];
                    endforeach;
                    #programName, startTime, rating, category
                    $epginfo[]= array(trim($cells[1]->nodeValue), $startTime, $rating, trim($cells[2]->nodeValue));
                endforeach;
                $zipped = array_slice(array_map(NULL, $epginfo, array_slice($epginfo,1)),0,-1);
                foreach($zipped as $epg) :
                    preg_match('/(<재>?)?(.*?)(\[(.*)\])?\s?(\(([\d,]+)회\))?$/', $epg[0][0], $matches);
                    $programName = trim($matches[2]) ?: "";
                    $subprogramName = trim($matches[4]) ?: "";
                    $startTime = $epg[0][1] ?: "";
                    $endTime = $epg[1][1] ?: "";
                    $desc = "";
                    $actors = "";
                    $producers = "";
                    $category = $epg[0][3] ?: "";
                    $rebroadcast = trim($matches[1]) ? True: False;
                    $episode = trim($matches[6]) ?: "";
                    $rating = $epg[0][2] ?: 0;
                    $programdata = array(
                        'channelId'=> $ChannelId,
                        'startTime' => $startTime,
                        'endTime' => $endTime,
                        'programName' => $programName,
                        'subprogramName'=> $subprogramName,
                        'desc' => $desc,
                        'actors' => $actors,
                        'producers' => $producers,
                        'category' => $category,
                        'episode' => $episode,
                        'rebroadcast' => $rebroadcast,
                        'rating' => $rating
                    );
                    writeProgram($programdata);
                endforeach;
            endif;
        } catch (Exception $e) {
            printError($e->getMessage());
        }
    endforeach;
}

function GetEPGFromSK($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $today = date("Ymd");
    $lastday = date("Ymd", strtotime("+".($GLOBALS['period'] - 1)." days"));
    $options = array(
        'http' => array(
            'method' => 'GET',
            'user-agent' => $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    $url = "http://m.btvplus.co.kr/Common/Inc/IFGetData.asp";
    $params = array(
        'variable' => 'IF_LIVECHART_DETAIL',
        'pcode' => '|^|start_time='.$today.'00|^|end_time='.$lastday.'24|^|svc_id='.$ServiceId
    );
    $params = http_build_query($params);
    $url = $url."?".$params;
    try {
        $response = @file_get_contents($url, False, $context);
        if ($response === False) :
            throw new Exception ($ChannelName.HTTP_ERROR);
        else :
            try {
                $data = json_decode($response, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                if($data['channel'] == NULL) :
                    printError($ChannelName.CHANNEL_ERROR);
                else :
                    $programs = $data['channel']['programs'];
                    foreach ($programs as $program) :
                        $programName = "";
                        $subprogramName = "";
                        $episode = "";
                        $rebroadcast = False;
                        preg_match('/^(.*?)(?:\s*[\(<]([\d,회]+)[\)>])?(?:\s*<([^<]*?)>)?(\((재)\))?$/', str_replace('...', '>', $program['programName']), $matches);
                        if ($matches != NULL) :
                            $programName = trim($matches[1]) ?: "";
                            $subprogramName = trim($matches[3]) ?: "";
                            $episode = str_replace("회", "", $matches[2]) ?: "";
                            $rebroadcast = $matches[5] ? True : False;
                        endif;
                        $startTime = date("YmdHis",$program['startTime']/1000);
                        $endTime = date("YmdHis",$program['endTime']/1000);
                        if ($GLOBALS['verbose'] == 'y') :
                            $desc = $program['synopsis'] ?: "";
                            $actors =trim(str_replace('...','',$program['actorName']), ', ') ?: "";//.replace('...','').strip(', ') if program['actorName'] else ''
                            $producers = trim(str_replace('...','',$program['directorName']), ', ') ?: "";//  if program['directorName'] else ''
                        else :
                            $desc = "";
                            $actors = "";
                            $producers = "";
                        endif;
                        if ($program['mainGenreName'] != NULL) :
                            $category = $program['mainGenreName'];
                        else:
                            $category = '';
                        endif;
                        $rating = $program['ratingCd'] ?: 0;
                        $programdata = array(
                            'channelId'=> $ChannelId,
                            'startTime' => $startTime,
                            'endTime' => $endTime,
                            'programName' => $programName,
                            'subprogramName'=> $subprogramName,
                            'desc' => $desc,
                            'actors' => $actors,
                            'producers' => $producers,
                            'category' => $category,
                            'episode' => $episode,
                            'rebroadcast' => $rebroadcast,
                            'rating' => $rating
                        );
                        writeProgram($programdata);
                    endforeach;
                endif;
            }
            catch(Exception $e) {
                printError($e->getMessage());
            }
        endif;
    } catch (Exception $e) {
       printError($e->getMessage());
    }
}
function GetEPGFromSKY($ChannelInfo) {
}
function GetEPGFromNaver($ChannelInfo) {
}
function writeProgram($programdata) {
    $ChannelId = $programdata['channelId'];
    $startTime = $programdata['startTime'];
    $endTime = $programdata['endTime'];
    $programName = $programdata['programName'];
    $subprogramName = $programdata['subprogramName'];
    $actors = $programdata['actors'];
    $producers = $programdata['producers'];
    $category = $programdata['category'];
    $episode = $programdata['episode'];
    $rebroadcast = $programdata['rebroadcast'];
    if($episode && $GLOBALS['addepisode'] == 'y') $programName = $programName." (".$episode."회)";
    if($rebroadcast == True && $GLOBALS['addrebroadcast'] == 'y') $programName = $programName." (재)";
    if($programdata['rating'] == 0) :
        $rating = "전체 관람가";
    else :
        $rating = sprintf("%s세 이상 관람가", $programdata['rating']);
    endif;
    if($GLOBALS['verbose'] == 'y') :
        $desc = $programdata['programName'];
        if($subprogramName)  $desc = $desc."\n부제 : ".$subprogramName;
        if($episode) $desc = $desc."\n회차 : (".$episode."회)";
        if($category) $desc = $desc."\n장르 : ".$category;
        if($actors) $desc = $desc."\n출연 : ".$actors;
        if($producers) $desc = $desc."\n제작 : ".$producers;
        $desc = $desc."\n등급 : ".$rating;
    else:
        $desc = "";
    endif;
    if($programdata['desc']) $desc = $desc."\n".$programdata['desc'];
    $contentTypeDict = array(
        '교양' => 'Arts / Culture (without music)',
        '만화' => 'Cartoons / Puppets',
        '교육' => 'Education / Science / Factual topics',
        '취미' => 'Leisure hobbies',
        '드라마' => 'Movie / Drama',
        '영화' => 'Movie / Drama',
        '음악' => 'Music / Ballet / Dance',
        '뉴스' => 'News / Current affairs',
        '다큐' => 'Documentary',
        '라이프' => 'Documentary',
        '시사/다큐' => 'Documentary',
        '연예' => 'Show / Game show',
        '스포츠' => 'Sports',
        '홈쇼핑' => 'Advertisement / Shopping'
       );
    $contentType = "";
    foreach($contentTypeDict as $key => $value) :
        if(!(strpos($category, $key) === False)) :
            $contentType = $value;
        endif;
    endforeach;
    printf("  <programme start=\"%s +0900\" stop=\"%s +0900\" channel=\"%s\">\n", $startTime, $endTime, $ChannelId);
    printf("    <title lang=\"kr\">%s</title>\n", $programName);
    if($subprogramName) :
        printf("    <sub-title lang=\"kr\">%s</sub-title>\n", $subprogramName);
    endif;
    if($GLOBALS['verbose']=='y') :
        printf("    <desc lang=\"kr\">%s</desc>\n", $desc);
        if($actors || $producers):
            printf("    <credits>\n");
            if($actors) :
                foreach(split(',', $actors) as $actor):
                    if($actor) printf("      <actor>%s</actor>\n", $actor);
                endforeach;
            endif;
            if($producers) :
                foreach(split(',', $producers) as $producer):
                    if($producer) printf("      <producer>%s</producer>\n", $producer);
                endforeach;
            endif;
            printf("    </credits>\n");
        endif;
    endif;
    if($category) printf("    <category lang=\"kr\">%s</category>\n", $category);
    if($contentType) printf("    <category lang=\"en\">%s</category>\n", $contentType);
    if($episode) printf("    <episode-num system=\"onscreen\">%s</episode-num>\n", $episode);
    if($rebroadcast) printf("    <previously-shown />\n");
    if($rating) :
        printf("    <rating system=\"KMRB\">\n");
        printf("      <value>%s</value>\n", $rating);
        printf("    </rating>\n");
    endif;
    printf("  </programme>\n");
}
function printLog($args) {
    fwrite(STDERR, $args."\n");
}
function printError($args) {
    fwrite(STDERR, "Error : ".$args."\n");
}
set_error_handler (function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException ($errstr, 0, $errno, $errfile, $errline);
});
?>
