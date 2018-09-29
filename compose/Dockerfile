FROM python:2.7.15-alpine

COPY ./requirements.txt /app/requirements.txt
RUN apk add --no-cached vim procps supervisor busybox busybox-extras libxslt tzdata
RUN apk add --no-cache --virtual .build-deps musl-dev g++ gcc libxslt-dev \
    && pip install --no-cache-dir -r /app/requirements.txt \
    && apk del .build-deps

ENV IPTV_SYSTEM KT
ENV TZ Asia/Seoul

# epg2xml
COPY . /app
WORKDIR /app

# httpd root
RUN mkdir -p /httpd
COPY ./LICENSE /httpd/index.html

# conf
RUN mkdir -p /conf && ln -sf /conf/epg2xml.json /app/epg2xml.json
COPY ./epg2xml.json /conf/epg2xml.json

# cron 
# https://gist.github.com/andyshinn/3ae01fa13cb64c9d36e7
# It is important to know that your script cannot be suffixed with any extension name.
RUN chmod +x /app/compose/cron/update-epg2xml.sh
RUN cp -pr /app/compose/cron/update-epg2xml.sh /etc/periodic/hourly/update-epg2xml

# timezone KST
RUN echo "Asia/Seoul" >  /etc/timezone

# supervisord config file
COPY ./compose/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

STOPSIGNAL SIGTERM

# CMD ["/bin/bash"]
CMD /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
