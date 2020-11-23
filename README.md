# VISY Agent

Note: Don't get mixed up with VIS Agent that is totally different agent.

## Build
docker build -t agent-visy .

## Run
docker run -p 8888:80 agent-visy:latest

## Posting file with Basic Auth
```
curl -u visy:yourpasswd -X POST -F xml=@file.xml http://0.0.0.0:8888/post/
```

## See posted files

```
curl -u visy:yourpasswd http://0.0.0.0:8888/uploads/
```
There should be only last succesfully processed file and all failed files.
TODO: add removal of failed files.

