#!/bin/bash

echo 'server
{
    server_name '$1';

    listen      80;
    listen [::]:80;
}
' > ssl.nginx.conf
ln --symbolic --force $(realpath ssl.nginx.conf) /etc/nginx/sites-enabled/ssl.nginx.conf
nginx -t
service nginx restart
certbot --nginx certonly -d $1
rm /etc/nginx/sites-enabled/ssl.nginx.conf
rm ssl.nginx.conf
service nginx restart
