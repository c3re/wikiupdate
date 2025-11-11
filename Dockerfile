FROM debian:13-slim
RUN apt-get update && apt-get upgrade -y && apt-get install -y \
    libapache2-mod-php \
    php-mbstring \
    php-curl

RUN rm -rf /var/www/html/*
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
COPY ./www /var/www

RUN chown -R www-data:www-data /var/www
ADD starter / /usr/local/bin/

CMD ["starter"]
EXPOSE 80