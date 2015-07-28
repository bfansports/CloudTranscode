FROM sportarc/cloudprocessingengine-pollers
MAINTAINER SportArchive, Inc.

ENV DEBIAN_FRONTEND noninteractive
ENV TERM screen

RUN echo "deb http://httpredir.debian.org/debian stable non-free" >> /etc/apt/sources.list

# Run build script in one step to avoid bloating the image size
# See: https://www.dajobe.org/blog/2015/04/18/making-debian-docker-images-smaller/
COPY build.sh /usr/local/bin/build-cloudtranscode.sh
RUN /usr/local/bin/build-cloudtranscode.sh

COPY src /usr/src/cloudprocessingengine/src/
WORKDIR /usr/src/cloudprocessingengine
RUN apt-get update \
    && apt-get install -y git \
    && make \
    && apt-get purge -y git \
    && apt-get autoremove -y

ENTRYPOINT ["/usr/src/cloudprocessingengine/bootstrap.sh"]
