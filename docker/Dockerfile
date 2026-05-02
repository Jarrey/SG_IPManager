FROM php:8.2-fpm-alpine3.20

ARG TZ=Asia/Shanghai
ENV TZ=${TZ}

RUN apk add --no-cache nginx iputils tzdata \
 && mkdir -p /var/www/html/data /run/nginx

COPY docker/start.sh /usr/local/bin/start.sh
COPY docker/.env.sample /var/www/html/.env.sample
COPY index.php app.php api.php logout.php /var/www/html/
COPY includes/ /var/www/html/includes/
COPY assets/ /var/www/html/assets/
COPY logo.svg /var/www/html/

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/start.sh \
 && chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
