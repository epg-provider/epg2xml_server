# EPG2XML

이 프로그램은 EPG(Electronic Program Guide)를 웹상의 여러 소스에서 가져와서 XML로 출력하는 프로그램으로 python2 및 php5 Cli에서 사용 가능하도록 제작되었다.
기본적으로 외부의 소스를 분석하여 출력하므로 외부 소스 사이트가 변경되거나 삭제되면 문제가 발생할 수 있다.

## 필요 모듈

### EPG2XML.PY
BeautifulSoup(bs4), lxml, requests 모듈이 추가로 필요하다.
설치 OS별로 모듈을 설치하기 위한 사전 설치 방법이 다를 수도 있으므로 검색해서 설치하도록 한다.
synology의 경우 파이썬 모듈을 설치하면 easy_install beautifulsoup, easy_install lxml, easy_install requests 로 추가할 수 있다.

### EPG2XML.php
PHP의 내장함수를 이용하므로 특별한 모듈은 필요없다. 

## 설정방법
epg2xml.json 안의 항목이 설정 가능한 항목이다. 
<pre>
MyISP : 사용하는 ISP를 넣는다 .(KT, LG, SK가 사용가능하다)
default_icon_url : 채널별 아이콘이 있는 url을 설정할 수 있다. 아이콘의 이름은 json 파일에 있는 Id.png로 기본설정되어 있다.
default_rebroadcast : 제목에 재방송 정보 출력
default_episode : 제목에 회차정보 출력
default_verbose : EPG 정보 상세 출력
default_fetch_limit : EPG 데이터 가져오는 기간이다.
default_xml_filename : EPG 저장시 기본 저장 이름으로 tvheadend 서버가 쓰기가 가능한 경로로 설정해야 한다.
default_xml_socket   : External XMLTV 사용시 xmltv.sock가 있는 경로로 설정해준다.
</pre>

Channel.json 파일을 텍스트 편집기로 열어보면 각채널별 정보가 들어 있다.
이중 Enabled:1로 되어 있는 부분을 Enabled:0으로 바꾸면 EPG정보를 가져오지 않는다.
필요없는 채널정보를 가져오지 않게 하는 것으로 EPG 정보 수집시 시간을 단축할 수 있다.
삭제된 채널등으로 인해서 오류 발생시에도 Enabled:0으로 변경하면 오류 발생을 차단할 수 있다.

## 사용방법

### tv_grab_file 사용시 (https://github.com/neo365/tvheadend-for-Korea)
epg2xml
tv_grab_file 안의 cat xmltv.xml 또는 wget 부분을

#### PYTHON의 경우
<pre>
/usr/bin/python /home/hts/epg2xml.py 또는
/home/hts/epg2xml.py
</pre>

#### PHP의 경우
<pre>
/usr/bin/php /home/hts/epg2xml.php 또는
/home/hts/epg2xml.php
</pre>

### XMLTV 사용시
#### PYTHON의 경우
<pre>
/usr/bin/python /home/hts/epg2xml.py 또는
/home/hts/epg2xml.py
</pre>

#### PHP의 경우
<pre>
/usr/bin/php /home/hts/epg2xml.php 또는
/home/hts/epg2xml.php
</pre>

## 옵션 소개

실행시 사용가능한 인수는 --help 명령어로 확인이 가능하다
<pre>
-h --help : 도움말 출력
--version : 버전을 보여준다.
</pre>

## 라이센스
BSD
