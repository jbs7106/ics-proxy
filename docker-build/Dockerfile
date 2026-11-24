# syntax=docker/dockerfile:1
# docker buildx build \ --platform linux/amd64,linux/arm64 \ --tag ics-proxy.php:latest \ --push .

FROM php:8.5-apache

COPY web/ /var/www/html/

EXPOSE 80
