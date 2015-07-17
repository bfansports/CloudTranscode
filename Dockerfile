FROM sportarc/cloudtranscode-base:latest
MAINTAINER SportArchive, Inc.

ENV DEBIAN_FRONTEND noninteractive
ENV TERM screen

RUN echo "deb http://httpredir.debian.org/debian stable non-free" >> /etc/apt/sources.list

# Run build script in one step to avoid bloating the image size
# See: https://www.dajobe.org/blog/2015/04/18/making-debian-docker-images-smaller/
COPY build.sh /usr/local/bin/build-cloudtranscode.sh
RUN /usr/local/bin/build-cloudtranscode.sh

COPY src /usr/src/cloudprocessingengine/src/
