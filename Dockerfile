FROM 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode-base:4.2
MAINTAINER bFAN Sports

COPY . /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode

RUN DEBIAN_FRONTEND=noninteractive TERM=screen \
    apt-get update \
    && apt-get install -y git \
    && make \
    && apt-get purge -y git \
    && apt-get autoremove -y

ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
