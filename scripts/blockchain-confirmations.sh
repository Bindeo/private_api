#!/usr/bin/env bash

if [ -n "$1" ];
then
url='http://'$1'.'
else
url='http://'
fi

curl --request GET \
  --url ${url}private.bindeo.com/system/blockchain/confirmations \
  --header 'content-type: application/json' \
  --header 'Authorization: Bearer ce42585fd8ab9272455bca6db8ef84eeef8989ab70c21529125c944ee1c93bed' \
  --data '{"net":"bitcoin"}'