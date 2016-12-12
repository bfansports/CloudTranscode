FROM sportarc/cloudtranscode-base:3.2.2
MAINTAINER Sport Archive, Inc.

RUN echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/timezone.ini
RUN apt-get update \
    && apt-get install -y autoconf zlib1g-dev \
    && docker-php-ext-install zip

COPY . /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode
RUN DEBIAN_FRONTEND=noninteractive TERM=screen \
    && apt-get install -y git \
    && make \
    && apt-get purge -y git \
    && apt-get autoremove -y

ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
