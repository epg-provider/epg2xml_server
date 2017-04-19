#!/usr/bin/env php
<?php
@date_default_timezone_set('Asia/Seoul');
define("VERSION", "1.1.7p1");

$debug = False;
$ua = "User-Agent: 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36', accept: '*/*'";
define("CHANNEL_ERROR", " 존재하지 않는 채널입니다.");
define("CONTENT_ERROR ", " EPG 정보가 없습니다.");
define("HTTP_ERROR", " EPG 정보를 가져오는데 문제가 있습니다.");
define("DISPLAY_ERROR", "EPG를 출력할 수 없습니다.");
define("FILE_ERROR", "XML 파일을 만들수 없습니다.");
define("SOCKET_ERROR", "소켓 파일을 찾을 수 없습니다.");
define("JSON_FILE_ERROR", "json 파일이 없습니다.");
define("JSON_SYNTAX_ERROR",  "json 파일 형식이 잘못되었습니다.");

//사용방법
$usage = <<<USAGE
usage: epg2xml.php [-h] -i {ALL, KT,LG,SK}
                  (-v | -d | -o [xmltv.xml] | -s [xmltv.sock]) [-l 1-7]
                  [--icon http://www.example.com/icon] [--verbose y, n]
USAGE;

//도움말
$help = <<<HELP
usage: epg2xml.py [-h] -i {ALL, KT,LG,SK}
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
  -i {ALL, KT,LG,SK}         사용하는 IPTV : ALL, KT, LG, SK
추가옵션:
  -l 1-7, --limit 1-7   EPG 정보를 가져올 기간, 기본값: 2
  --icon http://www.example.com/icon
                        채널 아이콘 URL, 기본값:
  --rebroadcast y, n    재방송정보 제목에 추가 출력
  --episode y, n        회차정보 제목에 추가 출력
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
    printf($help);
    exit;
elseif($args['v'] === False || $args['version'] === False)://버전 정보 출력
    printf("epg2xml.php version : %s\n", VERSION);
    exit;
else :
    $Settingfile = __DIR__."/epg2xml.json";
    try {
        $f = @file_get_contents($Settingfile);
        if($f === False) :
            printError("epg2xml.".JSON_FILE_ERROR);
            exit;
        else :
            try {
                $Settings = json_decode($f, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception("epg2xml.".JSON_SYNTAX_ERROR);
                $MyISP = $Settings['MyISP'];
                $default_output = $Settings['output'];
                $default_xml_file = $Settings['default_xml_file'];
                $default_xml_socket = $Settings['default_xml_socket'];
                $default_icon_url = $Settings['default_icon_url'];
                $default_fetch_limit = $Settings['default_fetch_limit'];
                $default_rebroadcast = $Settings['default_rebroadcast'];
                $default_episode = $Settings['default_episode'];
                $default_verbose = $Settings['default_verbose'];

                if(!empty($args['i'])) $MyISP = $args['i'];
                if($args['d'] === False || $args['display'] === False ) :
                    if($args['o'] || $args['outfile'] || $args['s'] || $args['socket']) :
                        printf($usage);
                        printf("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                        exit;
                    endif;
                    $default_output = "d";
                elseif(empty($args['o']) === False || empty($args['outfile']) === False) :
                    if($args['d'] === False || $args['display'] === False || $args['s'] || $args['socket']) :
                        print($usage);
                        print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                        exit;
                    endif;
                    $default_output = "o";
                    $default_xml_file = $args['o'] ?: $args['outfile'];
                elseif(empty($args['s']) === False || empty($args['socket']) === False) :
                    if($args['d'] === False || $args['display'] === False || $args['o'] || $args['outfile']) :
                        print($usage);
                        print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                        exit;
                    endif;
                    $default_output = "s";
                    $default_xml_socket = $args['s'] ?: $args['socket'];
                endif;
                if(empty($args['l']) === False || empty($args['limit']) === False) $default_fetch_limit = $args['l'] ?: $args['limit'];
                if(empty($args['icon']) === False) $default_icon_url = $args['icon'];
                if(empty($args['rebroadcast']) === False) $default_rebroadcast = $args['rebroadcast'];
                if(empty($args['episode']) === False) $default_episode = $args['episode'];
                if(empty($args['verbose']) === False) $default_verbose = $args['verbose'];
                if(empty($MyISP)) : //ISP 선택없을 시 사용법 출력
                    printError("epg2xml.json 파일의 MyISP항목이 없습니다.");
                    exit;
                else :
                    if(!in_array($MyISP, array("ALL", "KT", "LG", "SK"))) : //ISP 선택
                        printError("MyISP는 ALL, KT, LG, SK만 가능합니다.");
                        exit;
                    endif;
                endif;
                if(empty($default_output)) :
                    printError("epg2xml.json 파일의 output항목이 없습니다.");
                    exit;
                else :
                    if(in_array($default_output, array("d", "o", "s"))) :
                        switch ($default_output) :
                            case "d" :
                                $output = "display";
                                break;
                            case "o" :
                                $output = "file";
                                break;
                            case "s" :
                                $output = "socket";
                                break;
                        endswitch;
                    else :
                        printError("output는 d, o, s만 가능합니다.");
                        exit;
                    endif;
                endif;
                if(empty($default_fetch_limit)) :
                    printError("epg2xml.json 파일의 default_fetch_limit항목이 없습니다.");
                    exit;
                else :
                    if(in_array($default_fetch_limit, array(1, 2, 3, 4, 5, 6, 7))) :
                        $period = $default_fetch_limit;
                    else :
                        printError("default_fetch_limit는 1, 2, 3, 4, 5, 6, 7만 가능합니다.");
                        exit;
                    endif;
                endif;
                if(is_null($default_icon_url) == True) :
                    printError("epg2xml.json 파일의 default_icon_url항목이 없습니다.");
                    exit;
                else :
                    $IconUrl = $default_icon_url;
                endif;
                if(empty($default_rebroadcast)) :
                    printError("epg2xml.json 파일의 default_rebroadcast항목이 없습니다.");
                    exit;
                else :
                    if(in_array($default_rebroadcast, array("y", "n"))) :
                        $addrebroadcast = $default_rebroadcast;
                    else :
                        printError("default_rebroadcast는 y, n만 가능합니다.");
                        exit;
                    endif;
                endif;
               if(empty($default_episode)) :
                    printError("epg2xml.json 파일의 default_episode항목이 없습니다.");
                    exit;
                else :
                    if(in_array($default_episode, array("y", "n"))) :
                        $addepisode = $default_episode;
                    else :
                        printError("default_episode는 y, n만 가능합니다.");
                        exit;
                    endif;
                endif;
                if(empty($default_verbose)) :
                    printError("epg2xml.json 파일의 default_verbose항목이 없습니다.");
                    exit;
                else :
                    if(in_array($default_verbose, array("y", "n"))) :
                        $addverbose = $default_verbose;
                    else :
                        printError("default_verbose는 y, n만 가능합니다.");
                        exit;
                    endif;
                endif;
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
 endif;

if($output == "display") :
    $fp = fopen('php://output', 'w+');
    if ($fp === False) :
        printError(DISPLAY_ERROR);
        exit;
    else :
        try {
            getEpg();
            fclose($fp);
        } catch(Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endif;
elseif($output == "file") :
    if($default_xml_file) :
        $fp = fopen($default_xml_file, 'w+');
        if ($fp === False) :
            printError(FIEL_ERROR);
            exit;
        else :
            try {
                getEpg();
                fclose($fp);
            } catch(Exception $e) {
                if($GLOBALS['debug']) printError($e->getMessage());
            }
        endif;
    else :
        printError("epg2xml.json 파일의 default_xml_file항목이 없습니다.");
        exit;
    endif;
elseif($output == "socket") :
    if($default_xml_socket) :
        $default_xml_socket = "unix://".$default_xml_socket;
        $fp = @fsockopen($default_xml_socket, -1, $errno, $errstr, 30);
        if ($fp === False) :
            printError(SOCKET_ERROR);
            exit;
        else :
            try {
                getEpg();
                fclose($fp);
            } catch(Exception $e) {
                if($GLOBALS['debug']) printError($e->getMessage());
            }
        endif;
    else :
        printError("epg2xml.json 파일의 default_xml_socket항목이 없습니다.");
        exit;
    endif;
endif;

function getEPG() {
    $fp = $GLOBALS['fp'];
    $MyISP = $GLOBALS['MyISP'];
    $Channelfile = __DIR__."/Channel.json";
    try {
        $f = @file_get_contents($Channelfile);
        if($f === False) :
            printError("Channel.json.".JSON_FILE_ERROR);
            exit;
        else :
            try {
                $Channeldatas = json_decode($f, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception("Channel.".JSON_SYNTAX_ERROR);
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
    fprintf($fp, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    fprintf($fp, "<!DOCTYPE tv SYSTEM \"xmltv.dtd\">\n\n");
    fprintf($fp, "<tv generator-info-name=\"epg2xml %s\">\n", VERSION);
 
    foreach ($Channeldatas as $Channeldata) : #Get Channel & Print Channel info
        if($Channeldata['Enabled'] == 1) :
            $ChannelId = $Channeldata['Id'];
            $ChannelName = htmlspecialchars($Channeldata['Name'], ENT_XML1);
            $ChannelSource = $Channeldata['Source'];
            $ChannelServiceId = $Channeldata['ServiceId'];
            $ChannelIconUrl = htmlspecialchars($Channeldata['Icon_url'], ENT_XML1);            
            if($MyISP != "ALL" && $Channeldata[$MyISP.'Ch'] != Null):
                $ChannelInfos[] = array($ChannelId,  $ChannelName, $ChannelSource, $ChannelServiceId);
                $Channelnumber = $Channeldata[$MyISP.'Ch'];
                $ChannelISPName = htmlspecialchars($Channeldata[$MyISP." Name"], ENT_XML1);
                fprintf($fp, "  <channel id=\"%s\">\n", $ChannelId);
                fprintf($fp, "    <display-name>%s</display-name>\n", $ChannelName);
                fprintf($fp, "    <display-name>%s</display-name>\n", $ChannelISPName);
                fprintf($fp, "    <display-name>%s</display-name>\n", $Channelnumber);
                fprintf($fp, "    <display-name>%s</display-name>\n", $Channelnumber." ".$ChannelISPName);
                if($IconUrl) :
                    fprintf($fp, "    <icon src=\"%s/%s.png\" />\n", $IconUrl, $ChannelId);
                else :
                    fprintf($fp, "    <icon src=\"%s\" />\n", $ChannelIconUrl);
                endif;
                fprintf($fp, "  </channel>\n");
            elseif($MyISP == "ALL"):
                $ChannelInfos[] = array($ChannelId,  $ChannelName, $ChannelSource, $ChannelServiceId);
                fprintf($fp, "  <channel id=\"%s\">\n", $ChannelId);
                fprintf($fp, "    <display-name>%s</display-name>\n", $ChannelName);
                if($IconUrl) :
                    fprintf($fp, "    <icon src=\"%s/%s.png\" />\n", $IconUrl, $ChannelId);
                else :
                    fprintf($fp, "    <icon src=\"%s\" />\n", $ChannelIconUrl);
                endif;
                fprintf($fp, "  </channel>\n");
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
            GetEPGFromEPG($ChannelInfo);
        elseif($ChannelSource == 'KT') :
            GetEPGFromKT($ChannelInfo);
        elseif($ChannelSource == 'LG') :
            GetEPGFromLG($ChannelInfo);
        elseif($ChannelSource == 'SK') :
            GetEPGFromSK($ChannelInfo);
        elseif($ChannelSource == 'SKY') :
            GetEPGFromSKY($ChannelInfo);
        elseif($ChannelSource == 'NAVER') :
            GetEPGFromNaver($ChannelInfo);
        elseif($ChannelSource == 'TBROAD') :
            GetEPGFromTbroad($ChannelInfo);
        elseif($ChannelSource == 'ISCS') :
            GetEPGFromIscs($ChannelInfo);
        elseif($ChannelSource == 'MBC') :
            GetEPGFromMbc($ChannelInfo);
        elseif($ChannelSource == 'MIL'):
            GetEPGFromMil($ChannelInfo);
        elseif($ChannelSource == 'IFM'):
            GetEPGFromIfm($ChannelInfo);
        endif;
    endforeach;
    fprintf($fp, "</tv>\n");
}

// Get EPG data from epg.co.kr
function GetEPGFromEPG($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
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
        $epginfo = array();
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
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
                                    $thisday = date("Ymd", strtotime($day." +1 days"));
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
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

// Get EPG data from KT
function GetEPGFromKT($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
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
        $epginfo = array();
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
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
                    $startTime = date("YmdHis", strtotime($day." ".trim($cells->item(0)->nodeValue)));
                    $rating = str_replace("all", 0, str_replace("세 이상", "", trim($cells->item(2)->nodeValue)));
                    $epginfo[]= array(trim($cells->item(1)->nodeValue), $startTime, $rating, trim($cells->item(4)->nodeValue));
                endforeach;
                $zipped = array_slice(array_map(NULL, $epginfo, array_slice($epginfo,1)),0,-1);
                foreach($zipped as $epg) :
                    $programName = "";
                    $subprogramName = "";
                    preg_match('/^(.*?)( <(.*)>)?$/', $epg[0][0], $matches);
                    if($matches) :
                       $programName = $matches[1] ?: "";
                       $subprogramName = $matches[3] ?: "";
                    endif;
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
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

// Get EPG data from LG
function GetEPGFromLG($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
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
        $epginfo = array();
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $response = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'.$response;
                $dom = new DomDocument;
                libxml_use_internal_errors(true);
                $response = mb_convert_encoding($response, "UTF-8", "EUC-KR");
                $response = str_replace(array('<재>', ' [..', ' (..'), array('&lt;재&gt;', '', ''), $response);
                $dom->loadHTML($response);
                $xpath = new DomXPath($dom);
                $query = "//div[@class='tblType list']/table/tbody/tr";
                $rows = $xpath->query($query);
                foreach($rows as $row) :
                    $cells = $row->getElementsByTagName('td');
                    $programName = trim($cells->item(1)->childNodes->item(0)->nodeValue);
                    $startTime = date("YmdHis", strtotime($day." ".trim($cells->item(0)->nodeValue)));
                    $spans = $cells->item(1)->getElementsByTagName('span');
                    $rating = trim($spans->item(1)->nodeValue)=="All" ? 0 : trim($spans->item(1)->nodeValue);
                    #programName, startTime, rating, category
                    $epginfo[]= array($programName, $startTime, $rating, trim($cells->item(2)->nodeValue));
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
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

// Get EPG data from SK
function GetEPGFromSK($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $today = date("Ymd");
    $lastday = date("Ymd", strtotime("+".($GLOBALS['period'] - 1)." days"));
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
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
            printError($ChannelName.HTTP_ERROR);
        else :
            try {
                $data = json_decode($response, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                if($data['channel'] == NULL) :
                    if($GLOBALS['debug']) : 
                        printError($ChannelName.CHANNEL_ERROR);
                    endif;
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
                        $desc = $program['synopsis'] ?: "";
                        $actors =trim(str_replace('...','',$program['actorName']), ', ') ?: "";
                        $producers = trim(str_replace('...','',$program['directorName']), ', ') ?: "";
                        if ($program['mainGenreName'] != NULL) :
                            $category = $program['mainGenreName'];
                        else:
                            $category = "";
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
            } catch(Exception $e) {
                if($GLOBALS['debug']) printError($e->getMessage());
            }
        endif;
    } catch (Exception $e) {
        if($GLOBALS['debug']) printError($e->getMessage());
    }
}

// Get EPG data from SKY
function GetEPGFromSKY($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://www.skylife.co.kr/channel/epg/channelScheduleList.do";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'area' => 'in',
            'inFd_channel_id' => $ServiceId,
            'inairdate' => $day,
            'indate_type' => 'now'
        );
        $params = http_build_query($params);
        $url = $url."?".$params;
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
            else :
                try {
                    $data = json_decode($response, TRUE);
                    if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                    if(count($data['scheduleListIn']) == 0) :
                        if($GLOBALS['debug']) : 
                            printError($ChannelName.CHANNEL_ERROR);
                        endif;
                    else :
                        $programs = $data['scheduleListIn'];
                        foreach($programs as $program) :
                            $programName = htmlspecialchars_decode($program['program_name']) ?: "";
                            $subprogramName = str_replace(array('lt;', 'gt;', 'amp;'), array('<', '>', '&'),$program['program_subname']) ?: "";
                            $startTime = $program['starttime'];
                            $endTime = $program['endtime'];
                            $actors = trim(str_replace('...', '',$program['cast']), ', ') ?: "";
                            $producers = trim(str_replace('...', '',$program['dirt']), ', ') ?: "";
                            $description = str_replace(array('lt;', 'gt;', 'amp;'), array('<', '>', '&'),$program['description']) ?: "";
                            $summary = str_replace(array('lt;', 'gt;', 'amp;'), array('<', '>', '&'),$program['summary']) ?: "";
                            $desc = $description ?: "";
                            if($summary) :
                                $desc = $desc."\n".$summary;
                            endif;
                            $category = $program['program_category1'];
                            $episode = $program['episode_id'] ?: "";
                            $rebroadcast = $program['rebroad']== "Y" ? True : False;
                            $rating = $program['grade'] ?: 0;
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
                } catch(Exception $e) {
                    if($GLOBALS['debug']) printError($e->getMessage());
                }
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

// Get EPG data from Naver
function GetEPGFromNaver($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "https://search.naver.com/p/csearch/content/batchrender_ssl.nhn";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $totaldate[] = $day;
    endforeach;
    $params = array(
        '_callback' => 'epg',
        'fileKey' => 'single_schedule_channel_day',
        'pkid' => '66',
        'u1' => 'single_schedule_channel_day',
        'u2' => join(",", $totaldate),
        'u3' => $day,
        'u4' => $GLOBALS['period'],
        'u5' => $ServiceId,
        'u6' => 1,
        'u7' => $ChannelName."편성표", 
        'u8' => $ChannelName."편성표",
        'where' => 'nexearch'
    );
    $params = http_build_query($params);
    $url = $url."?".$params;
    try {
        $response = @file_get_contents($url, False, $context);
        if ($response === False) :
            printError($ChannelName.HTTP_ERROR);
        else :
            try {
                $response = str_replace('epg( ', '', $response );
                $response = substr($response, 0, strlen($response)-2);
                $response = preg_replace("/\/\*.*?\*\//","",$response);
                $data = json_decode($response, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                 if($data['displayDates'][0]['count'] == 0) :
                    if($GLOBALS['debug']) : 
                        printError($ChannelName.CHANNEL_ERROR);
                    endif;
                else :
                    for($i = 0; $i < count($data['displayDates']); $i++) :
                        for($j = 0; $j < 24; $j++) :
                            foreach($data['schedules'][$j][$i] as $program) :
                                #programName, startTime, episode, rebroadcast, rating
                                $startTime = date("YmdHis", strtotime($data['displayDates'][$i]['date']." ".$program['startTime']));
                                $epginfo[] = array($program['title'], $startTime, str_replace("회","", $program['episode']), $program['isRerun'], $program['grade']);
                            endforeach;
                        endfor;
                    endfor;
                    $zipped = array_slice(array_map(NULL, $epginfo, array_slice($epginfo,1)),0,-1);
                    foreach($zipped as $epg) :
                        $programName = htmlspecialchars_decode($epg[0][0], ENT_XML1) ?: "";
                        $subprogramName = "";
                        $startTime = $epg[0][1] ?: "";
                        $endTime = $epg[1][1] ?: "";
                        $desc = "";
                        $actors = "";
                        $producers = "";
                        $category = "";
                        $rebroadcast = $epg[0][3] ? True: False;
                        $episode = $epg[0][2] ?: "";
                        $rating = $epg[0][4] ?: 0;
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
             } catch(Exception $e) {
                if($GLOBALS['debug']) printError($e->getMessage());
            }
        endif;
    } catch (Exception $e) {
        if($GLOBALS['debug']) printError($e->getMessage());
    }
}

// Get EPG data from Tbroad
function GetEPGFromTbroad($ChannelInfo) {
    $url='https://www.tbroad.com/chplan/selectRealTimeListForNormal.tb';
}

// Get EPG data from Iscs
function GetEPGFromIscs($ChannelInfo) {
    $url='http://service.iscs.co.kr/sub/channel_view.asp';
    $params = array(
        'chan_idx'=>'242',
        'source_id'=>'203',
        'Chan_Date'=>'2017-04-18'
    );
}

// Get EPG data from MBC
function GetEPGFromMbc($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    $dayofweek = array('일', '월', '화', '수', '목', '금', '토');
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://miniunit.imbc.com/Schedule";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'rtype' => 'json'
        );
        $params = http_build_query($params);
        $url = $url."?".$params;
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
            else :
                try {
                    $data = json_decode($response, TRUE);
                    if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                    if(count($data['Programs']) == 0) :
                        if($GLOBALS['debug']) : 
                            printError($ChannelName.CHANNEL_ERROR);
                        endif;
                    else :
                        $programs = $data['Programs'];
                        foreach($programs as $program) :
                            if($program['Channel'] == "CHAM" && $program['LiveDays'] == $dayofweek[date("w", strtotime($day))]) :
                                $programName = "";
                                $rebroadcast = False;
                                preg_match('/^(.*?)(\(재\))?$/', htmlspecialchars_decode($program['ProgramTitle']), $matches);
                                if ($matches != NULL) :
                                    $programName = $matches[1];
                                    $rebroadcast = $matches[2] ? True : False;
                                endif;
                                $subprogramName =  "";
                                $startTime = $day." ".$program['StartTime'];
                                $startTime = date("YmdHis", strtotime($startTime));
                                $endTime = date("YmdHis", strtotime("+".$program['RunningTime']." minutes", strtotime($startTime)));
                                $desc = "";
                                $actors =  "";
                                $producers =  "";
                                $category = "음악";
                                $episode = "";
                                $rating = 0;
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
                            endif;

                        endforeach;
                    endif;
                } catch(Exception $e) {
                    if($GLOBALS['debug']) printError($e->getMessage());
                }
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

// Get EPG data from MIL
function GetEPGFromMil($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://radio.dema.mil.kr/web/fm/quick/ajaxTimetableList.do";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'program_date' => date("Ymd", strtotime($day))
        );
        $params = http_build_query($params);
        $url = $url."?".$params;
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
            else :
                try {
                    $data = json_decode($response, TRUE);
                    if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                    if(count($data['resultList']) == 0) :
                        if($GLOBALS['debug']) : 
                            printError($ChannelName.CHANNEL_ERROR);
                        endif;
                    else :
                        $programs = $data['resultList'];
                        foreach($programs as $program) :
                            $programName = "";
                            $rebroadcast = False;
                            preg_match('/^(.*?)(\(재\))?$/', htmlspecialchars_decode($program['program_title']), $matches);
                            if ($matches != NULL) :
                                $programName = $matches[1];
                                $rebroadcast = $matches[2] ? True : False;
                            endif;
                            $subprogramName =  htmlspecialchars_decode($program['program_subtitle']);
                            $startTime = $day." ".$program['program_time'];
                            $startTime = date("YmdHis", strtotime($startTime));
                            $endTime = $day." ".$program['program_end_time'];
                            $endTime = date("YmdHis", strtotime($endTime));
                            $desc = "";
                            $actors =  htmlspecialchars_decode($program['movie_actor']);
                            $producers =  htmlspecialchars_decode($program['movie_director']);
                            $category = "";
                            $episode = "";
                            $rating = 0;
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
                } catch(Exception $e) {
                    if($GLOBALS['debug']) printError($e->getMessage());
                }
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

// Get EPG data from IFM
function GetEPGFromIfm($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header'=> $GLOBALS['ua']
    ));
    $context  = stream_context_create($options);
    $dayofweek = array('1', '2', '3', '4', '5', '6', '7');
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://mapp.itvfm.co.kr/hyb/front/selectHybPgmList.do";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'outDay' => $dayofweek[(date("w", strtotime($day)+1))%7],
            'viewDt' => $day
        );
        $params = http_build_query($params);
        $url = $url."?".$params;
        try {
            $response = @file_get_contents($url, False, $context);
            if ($response === False) :
                printError($ChannelName.HTTP_ERROR);
            else :
                try {
                    $data = json_decode($response, TRUE);
                    if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                    if(count($data['hybMusicInfoList']) == 0) :
                        if($GLOBALS['debug']) : 
                            printError($ChannelName.CHANNEL_ERROR);
                        endif;
                    else :
                        $programs = $data['hybMusicInfoList'];
                        foreach($programs as $program) :
                            $programName = htmlspecialchars_decode($program['pgmTitle']) ?: "";
                            $subprogramName = "";
                            $startTime = $day." ".$program['pgmStime'];
                            $startTime = date("YmdHis", strtotime($startTime));
                            $endTime = $day." ".$program['pgmEtime'];
                            $endTime = date("YmdHis", strtotime($endTime));
                            $desc = "";
                            $actors =  htmlspecialchars_decode($program['pgmDj']);
                            $producers =  htmlspecialchars_decode($program['pgmPd']);
                            $category = "";
                            $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
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
                } catch(Exception $e) {
                    if($GLOBALS['debug']) printError($e->getMessage());
                }
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}
  
function writeProgram($programdata) {
    $fp = $GLOBALS['fp'];
    $ChannelId = $programdata['channelId'];
    $startTime = $programdata['startTime'];
    $endTime = $programdata['endTime'];
    $programName = htmlspecialchars($programdata['programName'], ENT_XML1);
    $subprogramName = htmlspecialchars($programdata['subprogramName'], ENT_XML1);
    $actors = htmlspecialchars($programdata['actors'], ENT_XML1);
    $producers = htmlspecialchars($programdata['producers'], ENT_XML1);
    $category = htmlspecialchars($programdata['category'], ENT_XML1);
    $episode = $programdata['episode'];
    $rebroadcast = $programdata['rebroadcast'];
    if($episode && $GLOBALS['addepisode'] == 'y') $programName = $programName." (".$episode."회)";
    if($rebroadcast == True && $GLOBALS['addrebroadcast'] == 'y') $programName = $programName." (재)";
    if($programdata['rating'] == 0) :
        $rating = "전체 관람가";
    else :
        $rating = sprintf("%s세 이상 관람가", $programdata['rating']);
    endif;
    if($GLOBALS['addverbose'] == 'y') :
        $desc = htmlspecialchars($programdata['programName'], ENT_XML1);
        if($subprogramName)  $desc = $desc."\n부제 : ".$subprogramName;
        if($episode) $desc = $desc."\n회차 : ".$episode."회";
        if($category) $desc = $desc."\n장르 : ".$category;
        if($actors) $desc = $desc."\n출연 : ".$actors;
        if($producers) $desc = $desc."\n제작 : ".$producers;
        $desc = $desc."\n등급 : ".$rating;
    else:
        $desc = "";
    endif;
    if($programdata['desc']) $desc = $desc."\n".htmlspecialchars($programdata['desc'], ENT_XML1);
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
    fprintf($fp, "  <programme start=\"%s +0900\" stop=\"%s +0900\" channel=\"%s\">\n", $startTime, $endTime, $ChannelId);
    fprintf($fp, "    <title lang=\"kr\">%s</title>\n", $programName);
    if($subprogramName) :
        fprintf($fp, "    <sub-title lang=\"kr\">%s</sub-title>\n", $subprogramName);
    endif;
    if($GLOBALS['addverbose']=='y') :
        fprintf($fp, "    <desc lang=\"kr\">%s</desc>\n", $desc);
        if($actors || $producers):
            fprintf($fp, "    <credits>\n");
            if($actors) :
                foreach(explode(',', $actors) as $actor):
                    if(trim($actor)) fprintf($fp, "      <actor>%s</actor>\n", trim($actor));
                endforeach;
            endif;
            if($producers) :
                foreach(explode(',', $producers) as $producer):
                    if(trim($producer)) fprintf($fp, "      <producer>%s</producer>\n", trim($producer));
                endforeach;
            endif;
            fprintf($fp, "    </credits>\n");
        endif;
    endif;
    if($category) fprintf($fp, "    <category lang=\"kr\">%s</category>\n", $category);
    if($contentType) fprintf($fp, "    <category lang=\"en\">%s</category>\n", $contentType);
    if($episode) fprintf($fp, "    <episode-num system=\"onscreen\">%s</episode-num>\n", $episode);
    if($rebroadcast) fprintf($fp, "    <previously-shown />\n");
    if($rating) :
        fprintf($fp, "    <rating system=\"KMRB\">\n");
        fprintf($fp, "      <value>%s</value>\n", $rating);
        fprintf($fp, "    </rating>\n");
    endif;
    fprintf($fp, "  </programme>\n");
}
function printLog($args) {
    fwrite(STDERR, $args."\n");
}
function printError($args) {
    fwrite(STDERR, "Error : ".$args."\n");
}
?>
