FROM php:5.6-cli
MAINTAINER SportArchive, Inc.

ENV DEBIAN_FRONTEND noninteractive
ENV TERM screen

# Add non-free and media repositories
ADD http://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/deb-multimedia-keyring_2014.2_all.deb /usr/share/deb-multimedia-keyring.deb
RUN dpkg -i /usr/share/deb-multimedia-keyring.deb
RUN echo "deb http://httpredir.debian.org/debian jessie non-free" >> /etc/apt/sources.list
RUN echo "deb http://www.deb-multimedia.org jessie main non-free" >> /etc/apt/sources.list

RUN apt-get update && apt-get install -y \
  libcurl4-nss-dev \
  gettext \
  imagemagick \
  ffmpeg \
  libvpx1 \
  libfdk-aac0 \
  nano

RUN docker-php-ext-install curl

COPY . /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode

ENV AWS_REGION xyz
ENV AWS_ACCESS_KEY_ID xyz
ENV AWS_SECRET_KEY xyz

ENV CLIENT_NAME xyz
ENV CLIENT_INPUT_QUEUE xyz
ENV CLIENT_OUTPUT_QUEUE xyz

ENV WORKFLOW_NAME xyz
ENV WORKFLOW_DESCRIPTION xyz
ENV WORKFLOW_DOMAIN xyz
ENV WORKFLOW_TASKLIST xyz

ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
