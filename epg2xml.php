#!/usr/bin/env php
<?php
include __DIR__."/epg2xml-function.php";
@date_default_timezone_set('Asia/Seoul');
error_reporting(E_ALL ^ E_NOTICE);
define("VERSION", "1.2.2p2");

$debug = False;
$ua = "'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36'";
$timeout = 5;
define("CHANNEL_ERROR", " 존재하지 않는 채널입니다.");
define("CONTENT_ERROR ", " EPG 정보가 없습니다.");
define("HTTP_ERROR", " EPG 정보를 가져오는데 문제가 있습니다.");
define("DISPLAY_ERROR", "EPG를 출력할 수 없습니다.");
define("FILE_ERROR", "XML 파일을 만들수 없습니다.");
define("SOCKET_ERROR", "소켓 파일을 찾을 수 없습니다.");
define("JSON_FILE_ERROR", "json 파일이 없습니다.");
define("JSON_SYNTAX_ERROR",  "json 파일 형식이 잘못되었습니다.");

if(version_compare(PHP_VERSION, '5.4.45','<')) :
    printError("PHP 버전은 5.4.45 이상이어야 합니다.");
    printError("현재 PHP 버전은 ".PHP_VERSION." 입니다.");
    exit;
endif;
if (!extension_loaded('json')) :
    printError("json 모듈이 설치되지 않았습니다.");
    exit;
endif;
if (!extension_loaded('dom')) :
    printError("dom 모듈이 설치되지 않았습니다.");
    exit;
endif;
if (!extension_loaded('mbstring')) :
    printError("mbstring 모듈이 설치되지 않았습니다.");
    exit;
endif;
if (!extension_loaded('openssl')) :
    printError("openssl 모듈이 설치되지 않았습니다.");
    exit;
endif;

if (!extension_loaded('curl')) :
    printError("curl 모듈이 설치되지 않았습니다.");
    exit;
endif;
//사용방법
$usage = <<<USAGE
usage: epg2xml.php [-h] -i {ALL, KT,LG,SK}
                  (-v | -d | -o [xmltv.xml] | -s [xmltv.sock]) [-l 1-7]
                  [--icon http://www.example.com/icon] [--verbose y, n]
USAGE;

//도움말
$help = <<<HELP
usage: epg2xml.php [-h] -i {ALL, KT,LG,SK}
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

if((isset($args['h']) && $args['h'] === False) || (isset($args['help']) && $args['help'] === False))://도움말 출력
    printf($help);
    exit;
elseif((isset($args['v']) && $args['v'] === False) || (isset($args['version']) && $args['version'] === False))://버전 정보 출력
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
                $MyChannels = isset($Settings['MyChannels']) ? $Settings['MyChannels'] : "";
                $default_output = $Settings['output'];
                $default_xml_file = $Settings['default_xml_file'];
                $default_xml_socket = $Settings['default_xml_socket'];
                $default_icon_url = $Settings['default_icon_url'];
                $default_fetch_limit = $Settings['default_fetch_limit'];
                $default_rebroadcast = $Settings['default_rebroadcast'];
                $default_episode = $Settings['default_episode'];
                $default_verbose = $Settings['default_verbose'];
                if(!empty($args['i'])) $MyISP = $args['i'];
                if((isset($args['d']) && $args['d'] === False) || (isset($args['display']) && $args['display'] === False) ) :
                    if(isset($args['o']) || isset($args['outfile']) || isset($args['s']) || isset($args['socket'])) :
                        printf($usage);
                        printf("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                        exit;
                    endif;
                    $default_output = "d";
                elseif(empty($args['o']) === False || empty($args['outfile']) === False) :
                    if((isset($args['d']) && $args['d'] === False) || (isset($args['display']) && $args['display'] === False) || isset($args['s']) || isset($args['socket'])) :
                        print($usage);
                        print("epg2xml.php: error: one of the arguments -v/--version -d/--display -o/--outfile -s/--socket is required\n");
                        exit;
                    endif;
                    $default_output = "o";
                    $default_xml_file = $args['o'] ?: $args['outfile'];
                elseif(empty($args['s']) === False || empty($args['socket']) === False) :
                    if((isset($args['d']) && $args['d'] === False) || (isset($args['display']) && $args['display'] === False) || isset($args['o']) || isset($args['outfile'])) :
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
                        $period = $period > 2 ? 2 : $period;
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
?>
