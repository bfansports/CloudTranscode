FROM php:5.6-cli
MAINTAINER SportArchive, Inc.

ENV DEBIAN_FRONTEND noninteractive
ENV TERM screen

# Add non-free and media repositories
ADD http://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/deb-multimedia-keyring_2015.6_all.deb /usr/share/deb-multimedia-keyring.deb
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

COPY . /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode

ENV AWS_REGION xyz
ENV AWS_ACCESS_KEY_ID xyz
ENV AWS_SECRET_ACCESS_KEY xyz

ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
