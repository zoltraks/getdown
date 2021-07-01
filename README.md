Get Down
========

PHP gateway for fetching HTML sites in stripped text format.

Allows to get just plain text from website.

## Requirements ##

This program requires ``PHP`` at minimum version of ``7.1``.

## Installation ##

Place files from this repository in some directory i.e. ``/var/www/getdown`` and make configuration for your web server.

This is an example for ``nginx`` using ``php-fpm`` with version ``7.2``.

Create file ``/etc/nginx/sites-available/getdown.example.org`` with the following content and make symbolic link to this file in ``/etc/nginx/sites-enabled`` directory.

```
# HTTP server
#
server {
        listen 80;

        server_name getdown.example.org www.getdown.example.org;

        root /var/www/getdown;
        index index.phar index.php index.html index.htm;

        location / {
                # First attempt to serve request as file, then
                # as directory, then fall back to displaying a 404.
                try_files $uri $uri/ =404;
                # Uncomment to enable naxsi on this location
                # include /etc/nginx/naxsi.rules
        }

        # pass the PHP scripts through FastCGI to php-fpm
        #
        location ~ \.(php|phar)$ {
                fastcgi_split_path_info ^(.+\.(?:php|phar))(/.+)$;
                fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;

                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                fastcgi_index index.php;
                include fastcgi_params;

                set $path_info $fastcgi_path_info;
                fastcgi_param PATH_INFO $path_info;
        }

        # deny access to .htaccess files, if Apache's document root
        # concurs with nginx's one
        #
        location ~ /\.ht {
                deny all;
        }

        # deny access to files ending with ~ because they may be backups
        #
        location ~ ~$ {
                deny all;
        }

        # deny access to file config.json as it is configuration file
        #
        location ~ config\.json {
                deny all;
        }
}
```

For https the configuration is slightly different.
In the following example it is assumed that private key and certificate are located at ``/etc/nginx/ssl/getdown.example.org/`` directory.

```
# HTTPS server
#
server {
        listen 443;
        server_name getdown.example.org www.getdown.example.org;

        root /var/www/atari;
        index index.phar index.php index.html index.htm;

        ssl on;
        ssl_certificate /etc/nginx/ssl/getdown.example.org/server.crt;
        ssl_certificate_key /etc/nginx/ssl/getdown.example.org/server.key;

        ssl_session_timeout 5m;

        ssl_protocols SSLv3 TLSv1 TLSv1.1 TLSv1.2;
        ssl_ciphers "HIGH:!aNULL:!MD5 or HIGH:!aNULL:!MD5:!3DES";
        ssl_prefer_server_ciphers on;

        location / {
                # First attempt to serve request as file, then
                # as directory, then fall back to displaying a 404.
                try_files $uri $uri/ =404;
                # Uncomment to enable naxsi on this location
                # include /etc/nginx/naxsi.rules
        }

        # pass the PHP scripts through FastCGI to php-fpm
        #
        location ~ \.(php|phar)$ {
                fastcgi_split_path_info ^(.+\.(?:php|phar))(/.+)$;
                fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;

                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                fastcgi_index index.php;
                include fastcgi_params;

                set $path_info $fastcgi_path_info;
                fastcgi_param PATH_INFO $path_info;
        }

        # deny access to .htaccess files, if Apache's document root
        # concurs with nginx's one
        #
        location ~ /\.ht {
                deny all;
        }

        # deny access to files ending with ~ because they may be backups
        #
        location ~ ~$ {
                deny all;
        }

        # deny access to file config.json as it is configuration file
        #
        location ~ config\.json {
                deny all;
        }
}
```

After that reload ``nginx`` server with this command.

```
sudo service nginx reload
```

## Configuration ##

You might create ``config.json`` file in main directory or ``src`` and place some defaults there.

```json
{
    "debug": true,
    "mode": "links"
}
```

## Usage ##

Browse to your site with additional parameters in URL:

```
http://getdown.example.org?u=google.com&m=links&c=ascii&f=html
```

- ``url``=&lt;``url_to_fetch``&gt;

- ``mode``=&lt;``fetch_mode``&gt;

Choose one of these modes how website should be retrieved: ``curl``, ``php``, ``links``.

- ``charset``=&lt;``output_charset``&gt;

Supported charsets are ``utf-8`` and ``ascii``.

- ``format``=&lt;``output_format``&gt;

Supported formats are ``html`` and ``text``.

Instead of using long parameter name you can use just first letter, like ``u`` instead of ``url``. Parameter names are case insensitive.
