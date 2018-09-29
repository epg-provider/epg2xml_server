# how-to-build
```bash
docker build -f ./compose/Dockerfile -t johnpark_pj/epg2xml:`git rev-parse --short HEAD` .
docker tag johnpark_pj/epg2xml:`git rev-parse --short HEAD` johnpark_pj/epg2xml:latest
docker push johnpark_pj/epg2xml:latest
```