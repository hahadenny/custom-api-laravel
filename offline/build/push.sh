#!/bin/bash

##############################################################################################
#  Push on-prem images to docker hub repo
##############################################################################################

## !! BEFORE RUNNING THIS SCRIPT YOU WILL ALSO NEED TO chmod u+x push.sh

#docker push mlapko/porta-api-base && \
docker push portadisguise/porta-api-base:latest && \
docker push portadisguise/porta-api:latest && \
docker push portadisguise/porta-socket:latest
