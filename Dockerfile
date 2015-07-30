FROM sportarc/cloudprocessingengine-pollers
MAINTAINER SportArchive, Inc.

ENV DEBIAN_FRONTEND noninteractive
ENV TERM screen

RUN echo "deb http://httpredir.debian.org/debian stable non-free" >> /etc/apt/sources.list

# Run build script in one step to avoid bloating the image size
# See: https://www.dajobe.org/blog/2015/04/18/making-debian-docker-images-smaller/
COPY . /usr/src/cloudtranscode
RUN /usr/src/cloudtranscode/build.sh
WORKDIR /usr/src/cloudtranscode
RUN apt-get update \
    && apt-get install -y git \
    && make \
    && apt-get purge -y git \
    && apt-get autoremove -y

ENTRYPOINT ["/usr/src/cloudprocessingengine/bootstrap.sh"]
