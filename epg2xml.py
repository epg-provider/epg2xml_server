#!/usr/bin/env python
# -*- coding: utf-8 -*-

from __future__ import print_function
import os
import sys
import requests
import json
import locale
import datetime
from bs4 import BeautifulSoup, SoupStrainer
import codecs
import socket
import re
from xml.sax.saxutils import escape, unescape
import argparse
import pprint

reload(sys)
sys.setdefaultencoding('utf-8')

__version__ = '1.1.7p'

# Set variable
debug = False
today = datetime.date.today()
ua = {'User-Agent': 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36', 'accept': '*/*'}
CHANNEL_ERROR = ' 존재하지 않는 채널입니다.'
CONTENT_ERROR = ' EPG 정보가 없습니다.'
HTTP_ERROR = ' EPG 정보를 가져오는데 문제가 있습니다.'
SOCKET_ERROR = 'xmltv.sock 파일을 찾을 수 없습니다.'
JSON_FILE_ERROR = 'json 파일을 읽을 수 없습니다.'
JSON_SYNTAX_ERROR = 'json 파일 형식이 잘못되었습니다.'

# Get epg data
def getEpg():
    Channelfile = os.path.dirname(os.path.abspath(__file__)) + '/Channel.json'
    ChannelInfos = []
    try:
        with open(Channelfile) as f: # Read Channel Information file
            Channeldatas = json.load(f)
    except EnvironmentError:
        printError("Channel." + JSON_FILE_ERROR)
        sys.exit()
    except ValueError:
        printError("Channel." + JSON_SYNTAX_ERROR)
        sys.exit()
    print('<?xml version="1.0" encoding="UTF-8"?>')
    print('<!DOCTYPE tv SYSTEM "xmltv.dtd">\n')
    print('<tv generator-info-name="epg2xml ' + __version__ + '">')

    for Channeldata in Channeldatas: #Get Channel & Print Channel info
        if Channeldata['Enabled'] == 1:
            ChannelId = Channeldata['Id']
            ChannelName = escape(Channeldata['Name'])
            ChannelSource = Channeldata['Source']
            ChannelServiceId = Channeldata['ServiceId']
            ChannelIconUrl = escape(Channeldata['Icon_url'])
            if MyISP != "ALL" and Channeldata[MyISP+'Ch'] is not None:
                ChannelInfos.append([ChannelId,  ChannelName, ChannelSource, ChannelServiceId])
                ChannelNumber = str(Channeldata[MyISP+'Ch']);
                ChannelISPName = escape(Channeldata[MyISP+' Name'])
                print('  <channel id="%s">' % (ChannelId))
                print('    <display-name>%s</display-name>' % (ChannelName))
                print('    <display-name>%s</display-name>' % (ChannelISPName))
                print('    <display-name>%s</display-name>' % (ChannelNumber))
                print('    <display-name>%s</display-name>' % (ChannelNumber+' '+ChannelISPName))
                if IconUrl:
                    print('    <icon src="%s/%s.png" />' % (IconUrl, ChannelId))
                else :
                    print('    <icon src="%s" />' % (ChannelIconUrl))
                print('  </channel>')
            elif MyISP == "ALL":
                ChannelInfos.append([ChannelId,  ChannelName, ChannelSource, ChannelServiceId])
                print('  <channel id="%s">' % (ChannelId))
                print('    <display-name>%s</display-name>' % (ChannelName))
                if IconUrl:
                    print('    <icon src="%s/%s.png" />' % (IconUrl, ChannelId))
                else :
                    print('    <icon src="%s" />' % (ChannelIconUrl))
                print('  </channel>')
    # Print Program Information
    for ChannelInfo in ChannelInfos:
        ChannelId = ChannelInfo[0]
        ChannelName =  ChannelInfo[1]
        ChannelSource =  ChannelInfo[2]
        ChannelServiceId =  ChannelInfo[3]
        if(debug) : printLog(ChannelName + ' 채널 EPG 데이터를 가져오고 있습니다')
        if ChannelSource == 'EPG':
            GetEPGFromEPG(ChannelInfo)
        elif ChannelSource == 'KT':
            GetEPGFromKT(ChannelInfo)
        elif ChannelSource == 'LG':
            GetEPGFromLG(ChannelInfo)
        elif ChannelSource == 'SK':
            GetEPGFromSK(ChannelInfo)
        elif ChannelSource == 'SKY':
            GetEPGFromSKY(ChannelInfo)
        elif ChannelSource == 'NAVER':
            GetEPGFromNaver(ChannelInfo)
        elif ChannelSource == 'TBROAD':
            GetEPGFromTbroad(ChannelInfo)
        elif ChannelSource == 'ISCS':
            GetEPGFromIscs(ChannelInfo)
        elif ChannelSource == 'MBC':
            GetEPGFromMbc(ChannelInfo)
        elif ChannelSource == 'MIL':
            GetEPGFromMil(ChannelInfo)
        elif ChannelSource == 'IFM':
            GetEPGFromIfm(ChannelInfo)
    print('</tv>')

# Get EPG data from epg.co.kr
def GetEPGFromEPG(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    url = 'http://www.epg.co.kr/epg-cgi/extern/cnm_guide_type_v070530.cgi'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        params = {'beforegroup':'100', 'checkchannel':ServiceId, 'select_group':'100', 'start_date':day.strftime('%Y%m%d')}
        epginfo = []
        try:
            response = requests.post(url, data=params, headers=ua)
            response.raise_for_status()
            html_data = response.content
            data = unicode(html_data, 'euc-kr', 'ignore').encode('utf-8', 'ignore')
            strainer = SoupStrainer('table', {'style':'margin-bottom:30'})
            soup = BeautifulSoup(data, 'lxml', parse_only=strainer, from_encoding='utf-8')
            tables = soup.find_all('table', {'style':'margin-bottom:30'})
            for i in range(1,4):
                thisday = day
                row = tables[i].find_all('td', {'colspan':'2'})
                for j, cell in enumerate(row):
                    hour = int(cell.text.strip().strip('시'))
                    if(i == 1) : hour = 'AM ' + str(hour)
                    elif(i == 2) : hour = 'PM ' + str(hour)
                    elif(i == 3 and hour > 5) : hour = 'PM ' + str(hour)
                    elif(i == 3 and hour < 5) :
                        hour = 'AM ' + str(hour)
                        thisday = day + datetime.timedelta(days=1)
                    for celldata in cell.parent.find_all('tr'):
                        pattern = "<tr>.*\[(.*)\]<\/td>\s.*\">(.*?)\s*(&lt;(.*)&gt;)?\s*(\(재\))?\s*(\(([\d,]+)회\))?(<img.*?)?(<\/a>)?\s*<\/td><\/tr>"
                        matches = re.match(pattern, str(celldata))
                        if not (matches is None):
                            minute = matches.group(1) if matches.group(1) else ''
                            startTime = str(thisday) + ' ' + hour + ':' + minute
                            startTime = datetime.datetime.strptime(startTime, '%Y-%m-%d %p %I:%M')
                            startTime = startTime.strftime('%Y%m%d%H%M%S')
                            image = matches.group(8) if matches.group(8) else ''
                            grade = re.match('.*schedule_([\d,]+)?.*',image)
                            if not (grade is None): rating = int(grade.group(1))
                            else : rating = 0
                            programName = matches.group(2).strip() if matches.group(2) else ''
                            subprogramName = matches.group(4).strip() if matches.group(4) else ''
                            #programName, startTime, rating, subprogramName, rebroadcast, episode
                            epginfo.append([programName, startTime, rating, subprogramName, matches.group(5), matches.group(7)])
            for epg1, epg2 in zip(epginfo, epginfo[1:]):
                programName = epg1[0] if epg1[0] else ''
                subprogramName = epg1[3] if epg1[3] else ''
                startTime = epg1[1] if epg1[1] else ''
                endTime = epg2[1] if epg2[1] else ''
                desc = ''
                actors = ''
                producers = ''
                category = ''
                rebroadcast = True if epg1[4] else False
                episode = epg1[5] if epg1[5] else ''
                rating = int(epg1[2]) if epg1[2] else 0
                programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                writeProgram(programdata)
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Get EPG data from KT
def GetEPGFromKT(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    url = 'http://tv.olleh.com/renewal_sub/liveTv/pop_schedule_week.asp'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        params = {'ch_name':'', 'ch_no':ServiceId, 'nowdate':day.strftime('%Y%m%d'), 'seldatie':day.strftime('%Y%m%d'), 'tab_no':'1'}
        epginfo = []
        try:
            response = requests.get(url, params=params, headers=ua)
            response.raise_for_status()
            html_data = response.content
            data = unicode(html_data, 'euc-kr', 'ignore').encode('utf-8', 'ignore')
            strainer = SoupStrainer('table', {'id':'pop_day'})
            soup = BeautifulSoup(data, 'lxml', parse_only=strainer, from_encoding='utf-8')
            html = soup.find('table', {'id':'pop_day'}).tbody.find_all('tr') if soup.find('table', {'id':'pop_day'}) else ''
            if(html):
                for row in html:
                    for cell in [row.find_all('td')]:
                        epginfo.append([cell[1].text, str(day) + ' ' + cell[0].text, cell[4].text, cell[2].text])
                for epg1, epg2 in zip(epginfo, epginfo[1:]):
                    programName = ''
                    subprogrmaName = ''
                    matches = re.match('^(.*?)( <(.*)>)?$', epg1[0].decode('string_escape'))
                    if not (matches is None):
                        programName = matches.group(1) if matches.group(1) else ''
                        subprogramName = matches.group(3) if matches.group(3) else ''
                    startTime = datetime.datetime.strptime(epg1[1], '%Y-%m-%d %H:%M')
                    startTime = startTime.strftime('%Y%m%d%H%M%S')
                    endTime = datetime.datetime.strptime(epg2[1], '%Y-%m-%d %H:%M')
                    endTime = endTime.strftime('%Y%m%d%H%M%S')
                    category = epg1[2]
                    desc = ''
                    actors = ''
                    producers = ''
                    episode = ''
                    rebroadcast = False
                    rating = 0
                    matches = re.match('(\d+)', epg1[3])
                    if not(matches is None): rating = int(matches.group())
                    programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                    writeProgram(programdata)
            else:
                if(debug): printError(ChannelName + CONTENT_ERROR)
                else: pass
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Get EPG data from LG
def GetEPGFromLG(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    url = 'http://www.uplus.co.kr/css/chgi/chgi/RetrieveTvSchedule.hpi'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        params = {'chnlCd': ServiceId, 'evntCmpYmd': day.strftime('%Y%m%d')}
        epginfo = []
        try:
            response = requests.get(url, params=params, headers=ua)
            response.raise_for_status()
            html_data = response.content
            data = unicode(html_data, 'euc-kr', 'ignore').encode('utf-8', 'ignore')
            data = data.replace('<재>', '&lt;재&gt;')
            strainer = SoupStrainer('table')
            soup = BeautifulSoup(data, 'lxml', parse_only=strainer, from_encoding='utf-8')
            html = soup.find('table').tbody.find_all('tr') if soup.find('table') else ''
            if(html):
                for row in html:
                    for cell in [row.find_all('td')]:
                        rating = 0 if cell[1].find('span', {'class': 'tag cte_all'}).text.strip()=="All" else int(cell[1].find('span', {'class': 'tag cte_all'}).text.strip())
                        cell[1].find('span', {'class': 'tagGroup'}).decompose()
                        epginfo.append([cell[1].text.strip(), str(day) + ' ' + cell[0].text, cell[2].text.strip(), rating])
                for epg1, epg2 in zip(epginfo, epginfo[1:]):
                    programName = ''
                    subprogramName = ''
                    episode = ''
                    matches = re.match('(<재>?)?(.*?)(\[(.*)\])?\s?(\(([\d,]+)회\))?$',  epg1[0].decode('string_escape'))
                    rebroadcast = False
                    if not (matches is None):
                        programName = matches.group(2) if matches.group(2) else ''
                        subprogramName = matches.group(4) if matches.group(4) else ''
                        rebroadcast = True if matches.group(1) else False
                        episode = matches.group(6) if matches.group(6) else ''
                    startTime = datetime.datetime.strptime(epg1[1], '%Y-%m-%d %H:%M')
                    startTime = startTime.strftime('%Y%m%d%H%M%S')
                    endTime = datetime.datetime.strptime(epg2[1], '%Y-%m-%d %H:%M')
                    endTime = endTime.strftime('%Y%m%d%H%M%S')
                    category = epg1[2]
                    desc = ''
                    actors = ''
                    producers = ''
                    programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                    writeProgram(programdata)
            else:
                if(debug): printError(ChannelName + CONTENT_ERROR)
                else: pass
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Get EPG data from SK
def GetEPGFromSK(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    lastday = today + datetime.timedelta(days=period-1)
    url = 'http://m.btvplus.co.kr/Common/Inc/IFGetData.asp'
    params = {'variable': 'IF_LIVECHART_DETAIL', 'pcode':'|^|start_time=' + today.strftime('%Y%m%d') + '00|^|end_time='+ lastday.strftime('%Y%m%d') + '24|^|svc_id=' + str(ServiceId)}
    try:
        response = requests.get(url, params=params, headers=ua)
        response.raise_for_status()
        json_data = response.text
        try:
            data = json.loads(json_data, encoding='utf-8')
            if (data['channel'] is None) :
                 if(debug): printError(ChannelName + CONTENT_ERROR)
                 else: pass
            else :
                programs = data['channel']['programs']
                for program in programs:
                    programName = ''
                    subprogramName = ''
                    episode = ''
                    rebroadcast = False
                    matches = re.match('^(.*?)(?:\s*[\(<]([\d,회]+)[\)>])?(?:\s*<([^<]*?)>)?(\((재)\))?$', program['programName'].replace('...', '>').encode('utf-8'))
                    if not (matches is None):
                        programName = matches.group(1).strip() if matches.group(1) else ''
                        subprogramName = matches.group(3).strip() if matches.group(3) else ''
                        episode = matches.group(2).replace('회', '') if matches.group(2) else ''
                        episode = '' if episode== '0' else episode
                        rebroadcast = True if matches.group(5) else False
                    startTime = datetime.datetime.fromtimestamp(int(program['startTime'])/1000)
                    startTime = startTime.strftime('%Y%m%d%H%M%S')
                    endTime = datetime.datetime.fromtimestamp(int(program['endTime'])/1000)
                    endTime = endTime.strftime('%Y%m%d%H%M%S')
                    desc = program['synopsis'] if program['synopsis'] else ''
                    actors = program['actorName'].replace('...','').strip(', ') if program['actorName'] else ''
                    producers = program['directorName'].replace('...','').strip(', ')  if program['directorName'] else ''
                    if not (program['mainGenreName'] is None) :
                        category = program['mainGenreName']
                    else:
                        category = ''
                    rating = int(program['ratingCd']) if program['programName'] else 0
                    programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                    writeProgram(programdata)
        except ValueError:
            if(debug): printError(ChannelName + CONTENT_ERROR)
            else: pass
    except requests.exceptions.HTTPError:
        if(debug): printError(ChannelName + HTTP_ERROR)
        else: pass

# Get EPG data from SKY
def GetEPGFromSKY(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    url = 'http://www.skylife.co.kr/channel/epg/channelScheduleList.do'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        params = {'area': 'in', 'inFd_channel_id': ServiceId, 'inairdate': day.strftime('%Y-%m-%d'), 'indate_type': 'now'}
        try:
            response = requests.get(url, params=params, headers=ua)
            response.raise_for_status()
            json_data = response.text
            try:
                data = json.loads(json_data, encoding='utf-8')
                if (len(data['scheduleListIn']) == 0) :
                    if(debug): printError(ChannelName + CONTENT_ERROR)
                    else: pass
                else :
                    programs = data['scheduleListIn']
                    for program in programs :
                        programName = unescape(program['program_name']).replace('lt;','<').replace('gt;','>').replace('amp;','&') if program['program_name'] else ''
                        subprogramName = unescape(program['program_subname']).replace('lt;','<').replace('gt;','>').replace('amp;','&') if program['program_subname'] else ''
                        startTime = program['starttime']
                        endTime = program['endtime']
                        actors = program['cast'].replace('...','').strip(', ') if program['cast'] else ''
                        producers = program['dirt'].replace('...','').strip(', ') if program['dirt'] else ''
                        description = unescape(program['description']).replace('lt;','<').replace('gt;','>').replace('amp;','&') if program['description'] else ''
                        summary = unescape(program['summary']).replace('lt;','<').replace('gt;','>').replace('amp;','&') if program['summary'] else ''
                        desc = description if description else ''
                        if summary : desc = desc + '\n' + summary
                        category = program['program_category1']
                        episode = program['episode_id'] if program['episode_id'] else ''
                        if episode : episode = int(episode)
                        rebroadcast = True if program['rebroad']== 'Y' else False
                        rating = int(program['grade']) if program['grade'] else 0
                        programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                        writeProgram(programdata)
            except ValueError:
                if(debug): printError(ChannelName + CONTENT_ERROR)
                else: pass
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Get EPG data from Naver
def GetEPGFromNaver(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    epginfo = []
    totaldate = []
    url = 'https://search.naver.com/p/csearch/content/batchrender_ssl.nhn'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        totaldate.append(day.strftime('%Y%m%d'))
    params = {'_callback': 'epg', 'fileKey': 'single_schedule_channel_day', 'pkid': '66', 'u1': 'single_schedule_channel_day', 'u2': ','.join(totaldate), 'u3': today.strftime('%Y%m%d'), 'u4': period, 'u5': ServiceId, 'u6': '1', 'u7': ChannelName + '편성표', 'u8': ChannelName + '편성표', 'where': 'nexearch'}
    try:
        response = requests.get(url, params=params, headers=ua)
        response.raise_for_status()
        json_data = re.sub(re.compile("/\*.*?\*/",re.DOTALL ) ,"" ,response.text.split("epg(")[1].strip(");").strip())
        try:
            data = json.loads(json_data, encoding='utf-8')
            for i, date in enumerate(data['displayDates']):
                for j in range(0,24):
                    for program in data['schedules'][j][i]:
                        epginfo.append([program['title'], date['date'] + ' ' + program['startTime'], program['episode'].replace('회',''), program['isRerun'], program['grade']])
            for epg1, epg2 in zip(epginfo, epginfo[1:]):
                programName = unescape(epg1[0]) if epg1[0] else ''
                subprogramName = ''
                startTime = datetime.datetime.strptime(epg1[1], '%Y%m%d %H:%M')
                startTime = startTime.strftime('%Y%m%d%H%M%S')
                endTime = datetime.datetime.strptime(epg2[1], '%Y%m%d %H:%M')
                endTime = endTime.strftime('%Y%m%d%H%M%S')
                desc = ''
                actors = ''
                producers = ''
                category = ''
                episode = epg1[2] if epg1[2] else ''
                if episode : episode = int(episode)
                rebroadcast = epg1[3]
                rating = epg1[4]
                programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                writeProgram(programdata)
        except ValueError:
             if(debug): printError(ChannelName + CONTENT_ERROR)
             else: pass
    except requests.exceptions.HTTPError:
        if(debug): printError(ChannelName + HTTP_ERROR)
        else: pass

# Get EPG data from Tbroad
def GetEPGFromTbroad(ChannelInfo):
    url='https://www.tbroad.com/chplan/selectRealTimeListForNormal.tb'
    pass

# Get EPG data from Iscs
def GetEPGFromIscs(ChannelInfo):
    url='http://service.iscs.co.kr/sub/channel_view.asp'
    params = {'chan_idx':'242', 'source_id':'203', 'Chan_Date':'2017-04-18'}
    pass

# Get EPG data from MBC
def GetEPGFromMbc(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    dayofweek = ['월', '화', '수', '목', '금', '토', '일']
    url = 'http://miniunit.imbc.com/Schedule'
    params = {'rtype': 'json'}
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        try:
            response = requests.get(url, params=params, headers=ua)
            response.raise_for_status()
            json_data = response.text
            try:
                data = json.loads(json_data, encoding='utf-8')
                for program in data['Programs']:
                    if program['Channel'] == "CHAM" and program['LiveDays'] == dayofweek[day.weekday()]:
                        programName = ''
                        rebroadcast = True
                        matches = re.match('^(.*?)(\(재\))?$', unescape(program['ProgramTitle'].encode('utf-8', 'ignore')))
                        if not(matches is None):
                            programName = matches.group(1)
                            rebroadcast = True if matches.group(2) else False
                        subprogramName = ''
                        startTime = str(day) + ' ' + program['StartTime']
                        startTime = datetime.datetime.strptime(startTime, '%Y-%m-%d %H%M')
                        endTime = startTime  + datetime.timedelta(minutes=int(program['RunningTime']))
                        startTime = startTime.strftime('%Y%m%d%H%M%S')
                        endTime = endTime.strftime('%Y%m%d%H%M%S')
                        desc = ''
                        actors = ''
                        producers = ''
                        category = '음악'
                        episode = ''
                        rating = 0
                        programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                        writeProgram(programdata)
            except ValueError:
                 if(debug): printError(ChannelName + CONTENT_ERROR)
                 else: pass
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Get EPG data from MIL
def GetEPGFromMil(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    url = 'http://radio.dema.mil.kr/web/fm/quick/ajaxTimetableList.do'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        params = {'program_date': day.strftime('%Y%m%d')}
        try:
            response = requests.get(url, params=params, headers=ua)
            response.raise_for_status()
            json_data = response.text
            try:
                data = json.loads(json_data, encoding='utf-8')
                for program in data['resultList']:
                    programName = ''
                    rebroadcast = False
                    matches = re.match('^(.*?)(\(재\))?$', unescape(program['program_title'].encode('utf-8', 'ignore')))
                    if not(matches is None):
                        programName = matches.group(1)
                        rebroadcast = True if matches.group(2) else False
                    subprogramName =  unescape(program['program_subtitle'])
                    startTime = str(day) + ' ' + program['program_time']
                    startTime = datetime.datetime.strptime(startTime, '%Y-%m-%d %H%M')
                    startTime = startTime.strftime('%Y%m%d%H%M%S')
                    endTime = str(day) + ' ' + program['program_end_time']
                    try:
                        endTime = datetime.datetime.strptime(endTime, '%Y-%m-%d %H%M')
                        endTime = endTime.strftime('%Y%m%d%H%M%S')
                    except ValueError:
                        endTime = endTime.replace(' 24', ' 23')
                        endTime = datetime.datetime.strptime(endTime, '%Y-%m-%d %H%M')
                        endTime = endTime + datetime.timedelta(hours=1)
                        endTime = endTime.strftime('%Y%m%d%H%M%S')
                    desc = ''
                    actors =  unescape(program['movie_actor'])
                    producers =  unescape(program['movie_director'])
                    category = ''
                    episode = ''
                    rating = 0
                    programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                    writeProgram(programdata)
            except ValueError:
                 if(debug): printError(ChannelName + CONTENT_ERROR)
                 else: pass
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Get EPG data from IFM
def GetEPGFromIfm(ChannelInfo):
    ChannelId = ChannelInfo[0]
    ChannelName = ChannelInfo[1]
    ServiceId =  ChannelInfo[3]
    dayofweek = ['1', '2', '3', '4', '5', '6', '7']
    url = 'http://mapp.itvfm.co.kr/hyb/front/selectHybPgmList.do'
    for k in range(period):
        day = today + datetime.timedelta(days=k)
        params = {'outDay':dayofweek[(day.weekday()+1)%7], 'viewDt':day}
        try:
            response = requests.get(url, params=params, headers=ua)
            response.raise_for_status()
            json_data = response.text
            try:
                data = json.loads(json_data, encoding='utf-8')
                for program in data['hybMusicInfoList']:
                    programName = unescape(program['pgmTitle'])
                    subprogramName = ''
                    startTime = str(day) + ' ' + program['pgmStime']
                    startTime = datetime.datetime.strptime(startTime, '%Y-%m-%d %H:%M')
                    startTime = startTime.strftime('%Y%m%d%H%M%S')
                    endTime = str(day) + ' ' + program['pgmEtime']
                    try:
                        endTime = datetime.datetime.strptime(endTime, '%Y-%m-%d %H:%M')
                        endTime = endTime.strftime('%Y%m%d%H%M%S')
                    except ValueError:
                        endTime = endTime.replace(' 24', ' 23')
                        endTime = datetime.datetime.strptime(endTime, '%Y-%m-%d %H:%M')
                        endTime = endTime + datetime.timedelta(hours=1)
                        endTime = endTime.strftime('%Y%m%d%H%M%S')
                    desc = ''
                    actors = program['pgmDj']
                    producers = program['pgmPd']
                    category = ''
                    episode = ''
                    rebroadcast = False
                    rating = 0
                    programdata = {'channelId':ChannelId, 'startTime':startTime, 'endTime':endTime, 'programName':programName, 'subprogramName':subprogramName, 'desc':desc, 'actors':actors, 'producers':producers, 'category':category, 'episode':episode, 'rebroadcast':rebroadcast, 'rating':rating}
                    writeProgram(programdata)
            except ValueError:
                 if(debug): printError(ChannelName + CONTENT_ERROR)
                 else: pass
        except requests.exceptions.HTTPError:
            if(debug): printError(ChannelName + HTTP_ERROR)
            else: pass

# Write Program
def writeProgram(programdata):
    ChannelId = programdata['channelId']
    startTime = programdata['startTime']
    endTime = programdata['endTime']
    programName = escape(programdata['programName'])
    subprogramName = escape(programdata['subprogramName'])
    actors = escape(programdata['actors'])
    producers = escape(programdata['producers'])
    category = escape(programdata['category'])
    episode = programdata['episode']
    rebroadcast = programdata['rebroadcast']
    if episode and addepisode  == 'y': programName = programName + ' ('+ str(episode) + '회)'
    if rebroadcast  == True and addrebroadcast == 'y' : programName = programName + ' (재)'
    if programdata['rating'] == 0 :
        rating = '전체 관람가'
    else :
        rating = '%s세 이상 관람가' % (programdata['rating'])
    if addverbose == 'y':
        desc = escape(programdata['programName'])
        if subprogramName : desc = desc + '\n부제 : ' + subprogramName
        if episode : desc = desc + '\n회차 : ' + str(episode) + '회'
        if category : desc = desc + '\n장르 : ' + category
        if actors : desc = desc + '\n출연 : ' + actors
        if producers : desc = desc + '\n제작 : ' + producers
        desc = desc + '\n등급 : ' + rating
    else:
        desc =''
    if programdata['desc'] : desc = desc + '\n' + escape(programdata['desc'])
    contentTypeDict={'교양':'Arts / Culture (without music)', '만화':'Cartoons / Puppets', '교육':'Education / Science / Factual topics', '취미':'Leisure hobbies', '드라마':'Movie / Drama', '영화':'Movie / Drama', '음악':'Music / Ballet / Dance', '뉴스':'News / Current affairs', '다큐':'Documentary', '라이프':'Documentary', '시사/다큐':'Documentary', '연예':'Show / Game show', '스포츠':'Sports', '홈쇼핑':'Advertisement / Shopping'}
    contentType = ''
    for key, value in contentTypeDict.iteritems():
        if category.startswith(key):
            contentType = value
    print('  <programme start="%s +0900" stop="%s +0900" channel="%s">' % (startTime, endTime, ChannelId))
    print('    <title lang="kr">%s</title>' % (programName))
    if subprogramName :
        print('    <sub-title lang="kr">%s</sub-title>' % (subprogramName))
    if addverbose=='y' :
        print('    <desc lang="kr">%s</desc>' % (desc))
        if actors or producers:
            print('    <credits>')
            if actors:
                for actor in actors.split(','):
                    if actor.strip(): print('      <actor>%s</actor>' % (actor.strip()))
            if producers:
                for producer in producers.split(','):
                    if producer.strip(): print('      <producer>%s</producer>' % (producer).strip())
            print('    </credits>')

    if category: print('    <category lang="kr">%s</category>' % (category))
    if contentType: print('    <category lang="en">%s</category>' % (contentType))
    if episode: print('    <episode-num system="onscreen">%s</episode-num>' % (episode))
    if rebroadcast: print('    <previously-shown />')

    if rating:
        print('    <rating system="KMRB">')
        print('      <value>%s</value>' % (rating))
        print('    </rating>')
    print('  </programme>')

def printLog(*args):
    print(*args, file=sys.stderr)

def printError(*args):
    print("Error : ", *args, file=sys.stderr)

Settingfile = os.path.dirname(os.path.abspath(__file__)) + '/epg2xml.json'
ChannelInfos = []
try:
    with open(Settingfile) as f: # Read Channel Information file
        Settings = json.load(f)
        MyISP = Settings['MyISP'] if 'MyISP' in Settings else ''
        default_output = Settings['output'] if 'output' in Settings else ''
        default_xml_file = Settings['default_xml_file'] if 'default_xml_file' in Settings else 'xmltv.xml'
        default_xml_socket = Settings['default_xml_socket'] if 'default_xml_socket' in Settings else 'xmltv.sock'
        default_icon_url = Settings['default_icon_url'] if 'default_icon_url' in Settings else None
        default_fetch_limit = Settings['default_fetch_limit'] if 'default_fetch_limit' in Settings else ''
        default_rebroadcast = Settings['default_rebroadcast'] if 'default_rebroadcast' in Settings else ''
        default_episode = Settings['default_episode'] if 'default_episode' in Settings else ''
        default_verbose = Settings['default_verbose'] if 'default_verbose' in Settings else ''

except EnvironmentError:
    printError("epg2xml." + JSON_FILE_ERROR)
    sys.exit()
except ValueError:
    printError("epg2xml." + JSON_SYNTAX_ERROR)
    sys.exit()

parser = argparse.ArgumentParser(description = 'EPG 정보를 출력하는 방법을 선택한다')
argu1 = parser.add_argument_group(description = 'IPTV 선택')
argu1.add_argument('-i', dest = 'MyISP', choices = ['ALL', 'KT', 'LG', 'SK'], help = '사용하는 IPTV : ALL, KT, LG, SK', default = MyISP)
argu2 = parser.add_mutually_exclusive_group()
argu2.add_argument('-v', '--version', action = 'version', version = '%(prog)s version : ' + __version__)
argu2.add_argument('-d', '--display', action = 'store_true', help = 'EPG 정보 화면출력')
argu2.add_argument('-o', '--outfile', metavar = default_xml_file, nargs = '?', const = default_xml_file, help = 'EPG 정보 저장')
argu2.add_argument('-s', '--socket', metavar = default_xml_socket, nargs = '?', const = default_xml_socket, help = 'xmltv.sock(External: XMLTV)로 EPG정보 전송')
argu3 = parser.add_argument_group('추가옵션')
argu3.add_argument('--icon', dest = 'icon', metavar = "http://www.example.com/icon", help = '채널 아이콘 URL, 기본값: '+ default_icon_url, default = default_icon_url)
argu3.add_argument('-l', '--limit', dest = 'limit', type=int, metavar = "1-7", choices = range(1,8), help = 'EPG 정보를 가져올 기간, 기본값: '+ str(default_fetch_limit), default = default_fetch_limit)
argu3.add_argument('--rebroadcast', dest = 'rebroadcast', metavar = 'y, n', choices = 'yn', help = '제목에 재방송 정보 출력', default = default_rebroadcast)
argu3.add_argument('--episode', dest = 'episode', metavar = 'y, n', choices = 'yn', help = '제목에 회차 정보 출력', default = default_episode)
argu3.add_argument('--verbose', dest = 'verbose', metavar = 'y, n', choices = 'yn', help = 'EPG 정보 추가 출력', default = default_verbose)

args = parser.parse_args()
if args.MyISP : MyISP = args.MyISP
if args.display :
    default_output = "d"
elif args.outfile :
    default_output = "o"
    default_xml_file = args.outfile
elif args.socket :
    default_output = "s"
    default_xml_socket = args.socket
if args.icon : default_icon_url = args.icon
if args.limit : default_fetch_limit = args.limit
if args.rebroadcast : default_rebroadcast = args.rebroadcast
if args.episode : default_episode = args.episode
if args.verbose : default_verbose = args.verbose

if MyISP:
    if not any(MyISP in s for s in ['ALL', 'KT', 'LG', 'SK']):
        printError("MyISP는 ALL, KT, LG, SK만 가능합니다.")
        sys.exit()
else :
    printError("epg2xml.json 파일의 MyISP항목이 없습니다.")
    sys.exit()

if default_output :
    if any(default_output in s for s in ['d', 'o', 's']):
        if default_output == "d" :
            output = "display";
        elif default_output == "o" :
            output = "file";
        elif default_output == 's' :
            output = "socket";
    else :
        printError("default_output는 d, o, s만 가능합니다.")
        sys.exit()
else :
    printError("epg2xml.json 파일의 output항목이 없습니다.");
    sys.exit()

IconUrl = default_icon_url

if default_rebroadcast :
    if not any(default_rebroadcast in s for s in ['y', 'n']):
        printError("default_rebroadcast는 y, n만 가능합니다.")
        sys.exit()
    else :
        addrebroadcast = default_rebroadcast
else :
    printError("epg2xml.json 파일의 default_rebroadcast항목이 없습니다.");
    sys.exit()

if default_episode :
    if not any(default_episode in s for s in ['y', 'n']):
        printError("default_episode는 y, n만 가능합니다.")
        sys.exit()
    else :
        addepisode = default_episode
else :
    printError("epg2xml.json 파일의 default_episode항목이 없습니다.");
    sys.exit()

if default_verbose :
    if not any(default_verbose in s for s in ['y', 'n']):
        printError("default_verbose는 y, n만 가능합니다.")
        sys.exit()
    else :
        addverbose = default_verbose
else :
    printError("epg2xml.json 파일의 default_verbose항목이 없습니다.");
    sys.exit()

if default_fetch_limit :
    if not any(str(default_fetch_limit) in s for s in ['1', '2', '3', '4', '5', '6', '7']):
        printError("default_fetch_limit 는 1, 2, 3, 4, 5, 6, 7만 가능합니다.")
        sys.exit()
    else :
        period = int(default_fetch_limit)
else :
    printError("epg2xml.json 파일의 default_fetch_limit항목이 없습니다.");
    sys.exit()

if output == "file" :
    if default_xml_file :
        sys.stdout = codecs.open(default_xml_file, 'w+', encoding='utf-8')
    else :
        printError("epg2xml.json 파일의 default_xml_file항목이 없습니다.");
        sys.exit()
elif output == "socket" :
    if default_xml_socket :
        try:
            sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
            sock.connect(default_xml_socket)
            sockfile = sock.makefile('w+')
            sys.stdout = sockfile
        except socket.error:
            printError(SOCKET_ERROR)
            sys.exit()
    else :
        printError("epg2xml.json 파일의 default_xml_socket항목이 없습니다.");
        sys.exit()
getEpg()
