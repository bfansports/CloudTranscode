FROM sportarc/cloudtranscode-base:latest
MAINTAINER Sport Archive, Inc.

RUN echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/timezone.ini
RUN apt-get update \
    && docker-php-ext-install zip

COPY CloudTranscode /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode
RUN DEBIAN_FRONTEND=noninteractive TERM=screen \
    && apt-get install -y git \
    && make \
    && apt-get purge -y git \
    && apt-get autoremove -y

COPY clientInterfaces/* /etc/cloudtranscode/

ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
