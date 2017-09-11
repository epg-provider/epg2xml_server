<?php
function getEPG() {
    $fp = $GLOBALS['fp'];
    $MyISP = $GLOBALS['MyISP'];
    $MyChannels = $GLOBALS['MyChannels'];
    $Channelfile = __DIR__."/Channel.json";
    $IconUrl = "";
    $ChannelInfos = array();
    try {
        $f = @file_get_contents($Channelfile);
        if($f === False) :
            printError("Channel.json.".JSON_FILE_ERROR);
            exit;
        else :
            try {
                $Channeldatajson = json_decode($f, TRUE);
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
//My Channel 정의
    $MyChannelInfo = array();
    if($MyChannels) :
        $MyChannelInfo = array_map('trim',explode(',', $MyChannels));
    endif;

    fprintf($fp, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    fprintf($fp, "<!DOCTYPE tv SYSTEM \"xmltv.dtd\">\n\n");
    fprintf($fp, "<tv generator-info-name=\"epg2xml %s\">\n", VERSION);
 
    foreach ($Channeldatajson as $Channeldata) : //Get Channel & Print Channel info
        if($Channeldata['Enabled'] == 1 ||  in_array($Channeldata['Id'], $MyChannelInfo)) :
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
    // Print Program Information
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
        elseif($ChannelSource == 'SKB') :
            GetEPGFromSKB($ChannelInfo);
        elseif($ChannelSource == 'SKY') :
            GetEPGFromSKY($ChannelInfo);
        elseif($ChannelSource == 'NAVER') :
            GetEPGFromNaver($ChannelInfo);
        elseif($ChannelSource == 'ISCS') :
            GetEPGFromIscs($ChannelInfo);
        elseif($ChannelSource == 'HCN') :
            GetEPGFromHcn($ChannelInfo);
        elseif($ChannelSource == 'POOQ') :
            GetEPGFromPooq($ChannelInfo);
        elseif($ChannelSource == 'MBC') :
            GetEPGFromMbc($ChannelInfo);
        elseif($ChannelSource == 'MIL'):
            GetEPGFromMil($ChannelInfo);
        elseif($ChannelSource == 'IFM'):
            GetEPGFromIfm($ChannelInfo);
        elseif($ChannelSource == 'KBS'):
            GetEPGFromKbs($ChannelInfo);
        elseif($ChannelSource == 'ARIRANG'):
            GetEPGFromArirang($ChannelInfo);
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
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://211.43.210.10:88/epg-cgi/extern/cnm_guide_type_v070530.php";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $params = array(
            'beforegroup' => '100',
            'checkchannel[]' => $ServiceId,
            'select_group' => '100',
            'start_date' => $day
        );
        $params = http_build_query($params);
        $method = "POST";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $response = str_replace("charset=euc-kr", "charset=utf-8", $response);
                $response = mb_convert_encoding($response, "UTF-8", "EUC-KR");
                $pattern = '/<td height="25" valign=top >(.*)<\/td>/';
                $response = preg_replace_callback($pattern, function($matches) { return '<td class="title">'.htmlspecialchars($matches[1], ENT_NOQUOTES).'</td>';}, $response);
                $response = str_replace(array('&lt;/b&gt;', '&lt;/a&gt;', '&lt;img', 'valign=top&gt;','align=absmiddle&gt;'), array('', '</a>', '<img', '>','>'), $response);
                $dom = new DomDocument;
                libxml_use_internal_errors(True);
                if($dom->loadHTML($response)):
                    $xpath = new DomXPath($dom);
                    for($i = 2; $i < 5; $i++) :
                        $thisday = $day;
                        $query = "//table[contains(@style,'margin-bottom:30')][".$i."]//td[contains(@colspan,'2')]/following::td[1]/table[1]//td[2]";
                        $programs = $xpath->query($query);
                        foreach($programs as $program) :
                            $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
                            $hour = $xpath->query("parent::*/parent::*/parent::*/parent::*/td[1]", $program)->item(0);
                            $hour = str_replace("시", "", trim($hour->nodeValue));
                            $minute = $xpath->query("preceding-sibling::td[1]", $program)->item(0);
                            $minute = str_replace(array("[", "]"), array("",""), trim($minute->nodeValue));
                            $minute = substr($minute, -2);
                            $hour = $hour.":".$minute;
                            switch ($i) :
                                case 2 :
                                    $hour = $hour." AM";
                                    break;
                                case 3 :
                                    $hour = $hour." PM";
                                    break;
                                case 4 :
                                    if($hour > 5 && $hour < 12) :
                                        $hour = $hour." PM";
                                    elseif($hour <5 || $hour == 12) :
                                        $hour = $hour." AM";
                                        $thisday = date("Ymd", strtotime($day." +1 days"));
                                    endif;
                                    break;
                            endswitch;
                            $startTime = date("YmdHis", strtotime($thisday." ".$hour));
                            $pattern = '/^(.*?)\s*(?:<(.*)>)?\s*(?:\((재)\))?\s*(?:\(([\d,]+)회\)?)?$/';
                            $programName = trim($program->nodeValue);
                             preg_match($pattern, $programName, $matches);
                            if ($matches != NULL) :
                                if(isset($matches[1])) $programName = trim($matches[1]) ?: "";
                                if(isset($matches[2])) $subprogramName = trim($matches[2]) ?: "";
                                if(isset($matches[3])) $rebroadcast = $matches[3] ? True : False;
                                if(isset($matches[4])) $episode = $matches[4] ?: "";
                            endif;
                            $images = $program->getElementsByTagName('img');
                            foreach($images as $image):
                                preg_match('/.*schedule_([\d,]+)?.*/', $image->getAttribute('src'), $grade);
                                if($grade != NULL) $rating = $grade[1];
                            endforeach;
                            //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                            $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                            usleep(1000);
                        endforeach;
                    endfor;
                 else:
                    if($GLOBALS['debug']) printError($ChannelName.CONTENT_ERROR);
                endif;
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
    epgzip($epginfo);
}

// Get EPG data from KT
function GetEPGFromKT($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
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
        $method = "GET";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $response = str_replace("charset=euc-kr", "charset=utf-8", $response);
                $response = mb_convert_encoding($response, "UTF-8", "EUC-KR");
                $dom = new DomDocument;
                libxml_use_internal_errors(True);
                if($dom->loadHTML($response)):
                    $xpath = new DomXPath($dom);
                    $query = "//table[@id='pop_day']/tbody/tr";
                    $rows = $xpath->query($query);
                    foreach($rows as $row) :
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        $cells = $row->getElementsByTagName('td');
                        //programName, startTime, rating, category
                        $startTime = date("YmdHis", strtotime($day." ".trim($cells->item(0)->nodeValue)));
                        $pattern = '/^(.*?)( <(.*)>)?$/';
                        $programName = trim($cells->item(1)->nodeValue);
                        preg_match($pattern, $programName, $matches);
                        if ($matches != NULL) :
                           if(isset($matches[1])) $programName = $matches[1] ?: "";
                           if(isset($matches[3])) $subprogramName = $matches[3] ?: "";
                        endif;
                        $category = trim($cells->item(4)->nodeValue);
                        $rating = str_replace("all", 0, str_replace("세 이상", "", trim($cells->item(2)->nodeValue)));
                        //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                        $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                        usleep(1000);
                    endforeach;
                else :
                    if($GLOBALS['debug']) printError($ChannelName.CONTENT_ERROR);
                endif;
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
    epgzip($epginfo);
}

// Get EPG data from LG
function GetEPGFromLG($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://www.uplus.co.kr/css/chgi/chgi/RetrieveTvSchedule.hpi";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $params = array(
            'chnlCd' => $ServiceId,
            'evntCmpYmd' =>  $day
        );
        $params = http_build_query($params);
        $method = "POST";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $response = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'.$response;
                $response = mb_convert_encoding($response, "UTF-8", "EUC-KR");
                $response = str_replace(array('<재>', ' [..', ' (..'), array('&lt;재&gt;', '', ''), $response);
                $dom = new DomDocument;
                libxml_use_internal_errors(True);
                if($dom->loadHTML($response)):
                    $xpath = new DomXPath($dom);
                    $query = "//div[@class='tblType list']/table/tbody/tr";
                    $rows = $xpath->query($query);
                    foreach($rows as $row) :
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        $cells = $row->getElementsByTagName('td');
                        $startTime = date("YmdHis", strtotime($day." ".trim($cells->item(0)->nodeValue)));
                        $programName = trim($cells->item(1)->childNodes->item(0)->nodeValue);
                        $pattern = '/(<재>)?\s?(?:\[.*?\])?(.*?)(?:\[(.*)\])?\s?(?:\(([\d,]+)회\))?$/';
                        preg_match($pattern, $programName, $matches);
                        if ($matches != NULL) :
                            if(isset($matches[2])) $programName = trim($matches[2]) ?: "";
                            if(isset($matches[3])) $subprogramName = trim($matches[3]) ?: "";
                            if(isset($matches[4])) $episode = trim($matches[4]) ?: "";
                            if(isset($matches[1])) $rebroadcast = trim($matches[1]) ? True: False;
                        endif;
                        $category = trim($cells->item(2)->nodeValue);
                        $spans = $cells->item(1)->getElementsByTagName('span');
                        $rating = trim($spans->item(1)->nodeValue)=="All" ? 0 : trim($spans->item(1)->nodeValue);
                        //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                        $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                        usleep(1000);
                    endforeach;
                else :
                    if($GLOBALS['debug']) printError($ChannelName.CONTENT_ERROR);
                endif;
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
    epgzip($epginfo);
}

// Get EPG data from SK
function GetEPGFromSK($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $today = date("Ymd");
    $lastday = date("Ymd", strtotime("+".($GLOBALS['period'] - 1)." days"));
    $url = "http://m.btvplus.co.kr/Common/Inc/IFGetData.asp";
    $params = array(
        'variable' => 'IF_LIVECHART_DETAIL',
        'pcode' => '|^|start_time='.$today.'00|^|end_time='.$lastday.'24|^|svc_id='.$ServiceId
    );
    $params = http_build_query($params);
    $method = "POST";
    try {
        $response = getWeb($url, $params, $method);
        if ($response === False && $GLOBALS['debug']) :
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
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        $pattern = '/^(.*?)(?:\s*[\(<]([\d,회]+)[\)>])?(?:\s*<([^<]*?)>)?(\((재)\))?$/';
                        preg_match($pattern, str_replace('...', '>', $program['programName']), $matches);
                        if ($matches != NULL) :
                            if(isset($matches[1])) $programName = trim($matches[1]) ?: "";
                            if(isset($matches[3])) $subprogramName = trim($matches[3]) ?: "";
                            if(isset($matches[2])) $episode = str_replace("회", "", $matches[2]) ?: "";
                            if(isset($matches[5])) $rebroadcast = $matches[5] ? True : False;
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
                        usleep(1000);
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

// Get EPG data from SKB
function GetEPGFromSKB($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://m.skbroadband.com/content/realtime/Channel_List.do";
        $day = date("Ymd", strtotime("+".($k - 1)." days"));
        $params = array(
            'key_depth2' => $ServiceId,
            'key_depth3' => $day
        );
        $params = http_build_query($params);
        $method = "POST";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $response = str_replace('charset="euc-kr"', 'charset="utf-8"', $response);
                $response = mb_convert_encoding($response, "UTF-8", "EUC-KR");
                $response = preg_replace('/<!--(.*?)-->/is', '', $response);
                $response = preg_replace('/<span><\/span>/is', '', $response);
                $pattern = '/<span>(.*)<\/span>/';
                $response = preg_replace_callback($pattern, function($matches) { return '<span class="title">'.htmlspecialchars($matches[1], ENT_NOQUOTES).'</span>';}, $response);
                $dom = new DomDocument;
                libxml_use_internal_errors(True);
                if($dom->loadHTML($response)):
                    $xpath = new DomXPath($dom);
                    $query = "//span[@class='caption' or @class='explan' or @class='fullHD' or @class='UHD' or @class='nowon']";
                    $spans = $xpath->query($query);
                    foreach($spans as $span) :
                        $span->parentNode->removeChild( $span);
                    endforeach;
                    $query = "//div[@id='dawn']/ul/li";
                    $rows = $xpath->query($query);
                    foreach($rows as $row) :
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        $cells = $row->getElementsByTagName('span');
                        $startTime = $cells->item(0)->nodeValue ?: "";
                        $startTime = date("YmdHis", strtotime($day." ".$startTime));
                        $programName = trim($cells->item(2)->nodeValue) ?: "";
                        $pattern = '/^(.*?)(\(([\d,]+)회\))?(<(.*)>)?(\((재)\))?$/';
                        preg_match($pattern, $programName, $matches);
                        if ($matches != NULL) :
                            if(isset($matches[1])) $programName = trim($matches[1]) ?: "";
                            if(isset($matches[5])) $subprogramName = trim($matches[5]) ?: "";
                            if(isset($matches[3])) $episode = $matches[3] ?: "";
                            if(isset($matches[7])) $rebroadcast = $matches[7] ? True : False;
                        endif;
                        if($cells->length > 3) $rating = str_replace('세', '', $cells->item(3)->nodeValue)  ?: 0;
                        //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                        $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                        usleep(1000);
                    endforeach;
                else :
                    if($GLOBALS['debug']) printError($ChannelName.CONTENT_ERROR);
                endif;
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
    epgzip($epginfo);
}

// Get EPG data from SKY
function GetEPGFromSKY($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://www.skylife.co.kr/channel/epg/channelScheduleListJson.do";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'area' => 'in',
            'inFd_channel_id' => $ServiceId,
            'inairdate' => $day,
            'indate_type' => 'now'
        );
        $params = http_build_query($params);
        $method = "POST";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
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
                            $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
                            $programName = htmlspecialchars_decode($program['program_name']) ?: "";
                            $subprogramName = str_replace(array('lt;', 'gt;', 'amp;'), array('<', '>', '&'),$program['program_subname']) ?: "";
                            $startTime = $program['starttime'];
                            $endTime = $program['endtime'];
                            $actors = trim(str_replace('...', '',$program['cast']), ', ') ?: "";
                            $producers = trim(str_replace('...', '',$program['dirt']), ', ') ?: "";
                            $description = str_replace(array('lt;', 'gt;', 'amp;'), array('<', '>', '&'),$program['description']) ?: "";
                            $summary = str_replace(array('lt;', 'gt;', 'amp;'), array('<', '>', '&'),$program['summary']) ?: "";
                            $desc = $description ?: "";
                            if($desc) :
                                if($summary):
                                    $desc = $desc."\n".$summary;
                                endif;
                            else :
                                $desc = $summary;
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
                            usleep(1000);
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
    $epginfo = array();
    $totaldate = array();
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
    $method = "GET";
    try {
        $response = getWeb($url, $params, $method);
        if ($response === False && $GLOBALS['debug']) :
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
                                $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                                $rebroadcast = False;
                                $rating = 0;
                                $startTime = date("YmdHis", strtotime($data['displayDates'][$i]['date']." ".$program['startTime']));
                                $programName = htmlspecialchars_decode(trim($program['title']), ENT_XML1);
                                $episode = str_replace("회","", $program['episode']);
                                $rebroadcast = $program['isRerun'] ? True : False;
                                $rating = $program['grade'];
                                //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                                $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                                usleep(1000);
                            endforeach;
                        endfor;
                    endfor;
                endif;
             } catch(Exception $e) {
                if($GLOBALS['debug']) printError($e->getMessage());
            }
        endif;
    } catch (Exception $e) {
        if($GLOBALS['debug']) printError($e->getMessage());
    }
    epgzip($epginfo);
}

// Get EPG data from Iscs
function GetEPGFromIscs($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    $epginfo2 = array();
    foreach(range(1, $GLOBALS['period']) as $k) :
        $istomorrow = False;
        $url = "http://m.iscs.co.kr/sub/02/data.asp";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'Exec_Mode' => 'view',
            'Source_Id' => $ServiceId,
            'Ch_Day' => $day
        );
        $params = http_build_query($params);
        $method = "POST";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                try {
                    $data = json_decode($response, TRUE);
                    if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                    if(count($data['total']) == 0) :
                        if($GLOBALS['debug']) :
                            printError($ChannelName.CHANNEL_ERROR);
                        endif;
                    else :
                        $programs = $data['list'];
                        foreach($programs as $program) :
                            $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
                            if(startsWith($program['Time'], '1') || startsWith($program['Time'], '2')) $istomorrow = True;
                            if(startsWith($program['Time'], '0') && $istomorrow == True) :
//                                $thisday = date("Ymd", strtotime($day." +1 days"));
                                $startTime = date("YmdHis", strtotime($day." +1 days"." ".$program['Time']));
                            else :
                                $startTime = date("YmdHis", strtotime($day." ".$program['Time']));
                            endif;
                            $pattern = '/^(.*?)(?:\(([\d,]+)회\))?(?:\((재)\))?$/';
                            preg_match($pattern, trim($program['Pg_Name']), $matches);
                            if ($matches != NULL) :
                                if(isset($matches[1])) $programName = trim($matches[1]) ?: "";
                                if(isset($matches[2])) $episode = $matches[2] ?: "";
                                if(isset($matches[3])) $rebroadcast = $matches[3] ? True : False;
                            endif;
                            if($program['Rating'] == '모든연령'):
                                $rating = 0;
                            else:
                                $rating = str_replace("세이상","", $program['Rating']);
                            endif;
                            //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                            $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                            usleep(1000);
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
    $epginfo2 =  array_map("unserialize", array_unique(array_map("serialize", $epginfo)));
    epgzip($epginfo2);

}

// Get EPG data from Hcn
function GetEPGFromHcn($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://m.hcn.co.kr/sch_ScheduleList.action";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'ch_id' => $ServiceId,
            'onairdate' => $day,
            '_' => _microtime()
        );
        $params = http_build_query($params);
        $method = "GET";
       try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $response = mb_convert_encoding($response, "HTML-ENTITIES", "UTF-8");
                $dom = new DomDocument;
                libxml_use_internal_errors(True);
                if($dom->loadHTML($response)):
                    $xpath = new DomXPath($dom);
                    $query = "//li";
                    $rows = $xpath->query($query);
                    foreach($rows as $row) :
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        $startTime = trim($xpath->query("span[@class='progTime']", $row)->item(0)->nodeValue) ?: "";
                        $startTime = date("YmdHis", strtotime($day." ".$startTime));
                        $programName = trim($xpath->query("span[@class='progTitle']", $row)->item(0)->nodeValue) ?: "";
                        //$category = trim($cells->item(2)->nodeValue) ?: "";
                        //$category = preg_replace('/\(.*\)/', '', $category);
                        $images = $row->getElementsByTagName('img');
                        foreach($images as $image):
                            preg_match('/re\.png/', $image->getAttribute('src'), $rebroad);
                            if($rebroad != NULL) $rebroadcast = True;
                            preg_match('/.*plus([\d,]+)\.png/', $image->getAttribute('src'), $grade);
                            if($grade != NULL) $rating = $grade[1];
                        endforeach;
                        //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                        $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                        usleep(1000);
                    endforeach;
                else :
                    if($GLOBALS['debug']) printError($ChannelName.CONTENT_ERROR);
                endif;
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
    epgzip($epginfo);
}

// Get EPG data from POOQ
function GetEPGFromPooq($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $today = date("Ymd");
    $lastday = date("Ymd", strtotime("+".($GLOBALS['period'])." days"));
    $url = "https://wapie.pooq.co.kr/v1/epgs30/".$ServiceId."/";
    $params = array(
        'deviceTypeId'=> 'pc',
        'marketTypeId'=> 'generic',
        'apiAccessCredential'=> 'EEBE901F80B3A4C4E5322D58110BE95C',
        'offset'=> '0',
        'limit'=> '1000',
        'startTime'=>  date("Y/m/d", strtotime($today)).' 00:00',
        'endTime'=>  date("Y/m/d", strtotime($lastday)).' 00:00'
    );
    foreach(range(1, $GLOBALS['period']) as $k) :
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $date_list[] = $day;
    endforeach;
    $params = http_build_query($params);
    $method = "GET";
    try {
        $response = getWeb($url, $params, $method);
        if ($response === False && $GLOBALS['debug']) :
            printError($ChannelName.HTTP_ERROR);
        else :
            try {
                $data = json_decode($response, TRUE);
                if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                if($data['result']['count'] == 0) :
                    if($GLOBALS['debug']) : 
                        printError($ChannelName.CHANNEL_ERROR);
                    endif;
                else :
                    $programs = $data['result']['list'];
                    foreach ($programs as $program) :
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        if(in_array($program['startDate'] , $date_list)) :
                            $startTime = $program['startDate']." ".$program['startTime'];
                            $startTime = date("YmdHis", strtotime($startTime));
                            $pattern = '/^(.*?)(?:([\d,]+)회)?(?:\((재)\))?$/';
                            $programName = str_replace("\r\n", "", $program['programTitle']);
                            preg_match($pattern, $programName, $matches);
                            if($matches !== NULL) :
                                if(isset($matches[1])) $programName = trim($matches[1]) ?: "";
                                if(isset($matches[2])) $episode = trim($matches[2]) ?: "";
                                if(isset($matches[3])) $rebroadcast = $matches[3] ? True : False;
                            endif;
                            if($program['programStaring']) $actors = trim($program['programStaring'], ',');
                            if($program['programSummary']) $desc = trim($program['programSummary']);
                            $rating = $program['age'];
                            //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                            $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                            usleep(1000);
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
    epgzip($epginfo);
}

// Get EPG data from MBC
function GetEPGFromMbc($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $dayofweek = array('일', '월', '화', '수', '목', '금', '토');
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://miniunit.imbc.com/Schedule";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'rtype' => 'json'
        );
        $params = http_build_query($params);
        $method = "GET";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
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
                                $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                                $rebroadcast = False;
                                $rating = 0;
                                $pattern = '/^(.*?)(\(재\))?$/';
                                preg_match($pattern, htmlspecialchars_decode($program['ProgramTitle']), $matches);
                                if ($matches != NULL) :
                                    $programName = $matches[1];
                                    if(isset($matches[2])) $rebroadcast = $matches[2] ? True : False;
                                endif;
                                $startTime = $day." ".$program['StartTime'];
                                $startTime = date("YmdHis", strtotime($startTime));
                                $endTime = date("YmdHis", strtotime("+".$program['RunningTime']." minutes", strtotime($startTime)));
                                $category = "음악";
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
                                usleep(1000);
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
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://radio.dema.mil.kr/web/fm/quick/ajaxTimetableList.do";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'program_date' => date("Ymd", strtotime($day))
        );
        $params = http_build_query($params);
        $method = "GET";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
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
                            $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
                            $pattern = '/^(.*?)(\(재\))?$/';
                            preg_match($pattern, htmlspecialchars_decode($program['program_title']), $matches);
                            if ($matches != NULL) :
                                $programName = $matches[1];
                                if(isset($matches[2])) $rebroadcast = $matches[2] ? True : False;
                            endif;
                            $subprogramName =  htmlspecialchars_decode($program['program_subtitle']);
                            $startTime = $day." ".$program['program_time'];
                            $startTime = date("YmdHis", strtotime($startTime));
                            $endTime = $day." ".$program['program_end_time'];
                            $endTime = date("YmdHis", strtotime($endTime));
                            $actors =  htmlspecialchars_decode($program['movie_actor']);
                            $producers =  htmlspecialchars_decode($program['movie_director']);
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
                            usleep(1000);
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
    $dayofweek = array('1', '2', '3', '4', '5', '6', '7');
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://mapp.itvfm.co.kr/hyb/front/selectHybPgmList.do";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'outDay' => $dayofweek[(date("w", strtotime($day)+1))%7],
            'viewDt' => $day
        );
        $params = http_build_query($params);
        $method = "GET";
        try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
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
                            $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
                            $programName = htmlspecialchars_decode($program['pgmTitle']) ?: "";
                            $startTime = $day." ".$program['pgmStime'];
                            $startTime = date("YmdHis", strtotime($startTime));
                            $endTime = $day." ".$program['pgmEtime'];
                            $endTime = date("YmdHis", strtotime($endTime));
                            $actors =  htmlspecialchars_decode($program['pgmDj']);
                            $producers =  htmlspecialchars_decode($program['pgmPd']);
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
                            usleep(1000);
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

// Get EPG data from KBS
function GetEPGFromKbs($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://world.kbs.co.kr/include/wink/_ajax_schedule.php";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array(
            'channel'=>'wink_11'
        );
        $params = http_build_query($params);
        $method = "GET";
        try {
             $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                try {
                    $data = json_decode($response, TRUE);
                    if(json_last_error() != JSON_ERROR_NONE) throw new Exception(JSON_SYNTAX_ERROR);
                    if(count($data['schedule']) == 0) :
                        if($GLOBALS['debug']) : 
                            printError($ChannelName.CHANNEL_ERROR);
                        endif;
                    else :
                        $dom = new DomDocument;
                        libxml_use_internal_errors(True);
                        $dom->loadHTML($data['schedule']);
                        $xpath = new DomXPath($dom);
                        $query = "//li";
                        $rows = $xpath->query($query);
                        foreach($rows as $row) :
                            $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                            $rebroadcast = False;
                            $rating = 0;
                            $cells = $row->getElementsByTagName('span');
                            $startTime = $day." ".trim($cells->item(0)->childNodes->item(0)->nodeValue);
                            $startTime = date("YmdHis", strtotime($startTime));
                            $programName = trim($cells->item(2)->childNodes->item(0)->nodeValue);
                            $programName = str_replace(array("[","]", " Broadcast"), array("", "", ""), $programName);
                            //ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
                             $epginfo[] = array($ChannelId, $startTime, $programName, $subprogramName, $desc, $actors, $producers, $category, $episode, $rebroadcast, $rating);
                             usleep(1000);
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
    epgzip($epginfo);
}

function GetEPGFromArirang($ChannelInfo) {
    $ChannelId = $ChannelInfo[0];
    $ChannelName = $ChannelInfo[1];
    $ServiceId =  $ChannelInfo[3];
    $epginfo = array();
    foreach(range(1, $GLOBALS['period']) as $k) :
        $url = "http://www.arirang.com/Radio/Radio_Index.asp";
        $day = date("Y-m-d", strtotime("+".($k - 1)." days"));
        $params = array();
        $params = http_build_query($params);
        $method = "GET";
       try {
            $response = getWeb($url, $params, $method);
            if ($response === False && $GLOBALS['debug']) :
                printError($ChannelName.HTTP_ERROR);
            else :
                $dom = new DomDocument;
                libxml_use_internal_errors(True);
                $response = mb_convert_encoding($response, "HTML-ENTITIES", "EUC-KR");
                if($dom->loadHTML($response)):
                    $xpath = new DomXPath($dom);
                    $dayofweek = date("w", strtotime($day));
                    if($dayofweek == 0):
                        $query = "//table[@id='aIRSW_sun']/tr";
                    elseif($dayofweek == 6):
                        $query = "//table[@id='aIRSW_sat']/tr";
                    else :
                        $query = "//table[@id='aIRSW_week']/tr";
                    endif;
                    $rows = $xpath->query($query);
                    foreach($rows as $row) :
                        $startTime = $endTime = $programName = $subprogramName = $desc = $actors = $producers = $category = $episode = "";
                        $rebroadcast = False;
                        $rating = 0;
                        $time = $row->getElementsByTagName('th');
                        $times = explode('~', trim($time->item(0)->nodeValue));
                        $startTime = date("YmdHis", strtotime($day." ".$times[0]));
                        $endTime = date("YmdHis", strtotime($day." ".$times[1]));
                        $program = $row->getElementsByTagName('td');
                        $pattern = '/^(.*?)(?:\((Re)\))?$/';
                        preg_match($pattern, trim($program->item(0)->nodeValue), $matches);
                        if ($matches != NULL) :
                            $programName = $matches[1];
                            if(isset($matches[2])) $rebroadcast = $matches[2] ? True : False;
                        endif;
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
                        usleep(1000);
                    endforeach;
                else :
                    if($GLOBALS['debug']) printError($ChannelName.CONTENT_ERROR);
                endif;
            endif;
        } catch (Exception $e) {
            if($GLOBALS['debug']) printError($e->getMessage());
        }
    endforeach;
}

# Zip epginfo
function epgzip($epginfo) {
    if($epginfo == NULL) $epginfo = array();
    #ChannelId, startTime, programName, subprogramName, desc, actors, producers, category, episode, rebroadcast, rating
    $zipped = array_slice(array_map(NULL, $epginfo, array_slice($epginfo,1)),0,-1);
    foreach($zipped as $epg) :
        $ChannelId = $epg[0][0] ?: "";
        $startTime = $epg[0][1] ?: "";
        $endTime = $epg[1][1] ?: "";
        $programName = $epg[0][2] ?: "";
        $subprogramName = $epg[0][3] ?: "";
        $desc = $epg[0][4] ?: "";
        $actors = $epg[0][5] ?: "";
        $producers = $epg[0][6] ?: "";
        $category = $epg[0][7] ?: "";
        $episode = $epg[0][8] ?: "";
        $rebroadcast = $rebroadcast = $epg[0][9] ? True: False;
        $rating = $epg[0][10] ?: 0;
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
}

function writeProgram($programdata) {
    $fp = $GLOBALS['fp'];
    $ChannelId = $programdata['channelId'];
    $startTime = $programdata['startTime'];
    $endTime = $programdata['endTime'];
    $programName = trim(htmlspecialchars($programdata['programName'], ENT_XML1));
    $subprogramName = trim(htmlspecialchars($programdata['subprogramName'], ENT_XML1));
    preg_match('/(.*) \(?(\d+부)\)?/', $programName, $matches);
    if ($matches != NULL) :
        if(isset($matches[1])) $programName = trim($matches[1]) ?: "";
        if(isset($matches[2])) $subprogramName = trim($matches[2]." ".$subprogramName) ?: "";
    endif;
    if($programName == NULL):
        $programName = $subprogramName;
    endif;
    $actors = htmlspecialchars($programdata['actors'], ENT_XML1);
    $producers = htmlspecialchars($programdata['producers'], ENT_XML1);
    $category = htmlspecialchars($programdata['category'], ENT_XML1);
    $episode = $programdata['episode'];
    if($episode) :
        $episode_ns = (int)$episode - 1;
        $episode_ns = '0' . '.' . $episode_ns . '.' . '0' . '/' . '0';
        $episode_on = $episode;
    endif;
    $rebroadcast = $programdata['rebroadcast'];
    if($episode && $GLOBALS['addepisode'] == 'y') $programName = $programName." (".$episode."회)";
    if($rebroadcast == True && $GLOBALS['addrebroadcast'] == 'y') $programName = $programName." (재)";
    if($programdata['rating'] == 0) :
        $rating = "전체 관람가";
    else :
        $rating = sprintf("%s세 이상 관람가", $programdata['rating']);
    endif;
    if($GLOBALS['addverbose'] == 'y') :
        $desc = trim(htmlspecialchars($programdata['programName'], ENT_XML1));
        if($subprogramName)  $desc = $desc."\n부제 : ".$subprogramName;
        if($rebroadcast == True && $GLOBALS['addrebroadcast']  == 'y') $desc = $desc."\n방송 : 재방송";
        if($episode) $desc = $desc."\n회차 : ".$episode."회";
        if($category) $desc = $desc."\n장르 : ".$category;
        if($actors) $desc = $desc."\n출연 : ".trim($actors);
        if($producers) $desc = $desc."\n제작 : ".trim($producers);
        $desc = $desc."\n등급 : ".$rating;
    else:
        $desc = "";
    endif;
    if($programdata['desc']) $desc = $desc."\n".htmlspecialchars($programdata['desc'], ENT_XML1);
    $desc = preg_replace('/ +/', ' ', $desc);
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
    if($episode) fprintf($fp, "    <episode-num system=\"xmltv_ns\">%s</episode-num>\n", $episode_ns);
    if($episode) fprintf($fp, "    <episode-num system=\"onscreen\">%s</episode-num>\n", $episode_on);
    if($rebroadcast) fprintf($fp, "    <previously-shown />\n");
    if($rating) :
        fprintf($fp, "    <rating system=\"KMRB\">\n");
        fprintf($fp, "      <value>%s</value>\n", $rating);
        fprintf($fp, "    </rating>\n");
    endif;
    fprintf($fp, "  </programme>\n");
}

function getWeb($url, $params, $method) {
    $ch = curl_init();
    if($method == "GET"):
        $url = $url."?".$params;
    elseif($method == "POST"):
        curl_setopt ($ch, CURLOPT_POST, True);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $params);
    endif;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['timeout']);
    curl_setopt($ch, CURLOPT_HEADER, False);
    curl_setopt($ch, CURLOPT_FAILONERROR, True);
    curl_setopt($ch, CURLOPT_USERAGENT, $GLOBALS['ua']);
    $response = curl_exec($ch);
    if(curl_error($ch) && $GLOBALS['debug']) printError($url." ".curl_error($ch));
    curl_close($ch);
    return $response;
}

function printLog($string) {
    if(php_sapi_name() == "cli"):
        fwrite(STDERR, $string."\n");
    else:
        header("Content-Type: text/plain; charset=utf-8");
        print($string."\n");
    endif;
}

function printError($string) {
    if(php_sapi_name() == "cli"):
        fwrite(STDERR, "Error : ".$string."\n");
    else:
        header("Content-Type: text/plain; charset=utf-8");
        print("Error : ".$string."\n");
    endif;
}

function _microtime() {
    list($usec, $sec) = explode(" ", microtime());
    return ($sec.(int)($usec*1000));
}

function startsWith($haystack, $needle) {
    return !strncmp($haystack, $needle, strlen($needle));
}
?>
