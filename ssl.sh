#!/bin/bash

echo 'server
{
    server_name $@;

    listen      80;
    listen [::]:80;
}
' > ssl.nginx.conf
ln --symbolic --force $(realpath ssl.nginx.conf) /etc/nginx/sites-enabled/ssl.nginx.conf
nginx -t
service nginx restart

for domain in "$@"; do domains="$domains -d $domain"; done
certbot --nginx certonly $domains

rm /etc/nginx/sites-enabled/ssl.nginx.conf
rm ssl.nginx.conf
service nginx restart
