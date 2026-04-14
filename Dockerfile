FROM public.ecr.aws/docker/library/php:8.2-cli AS builder

RUN DEBIAN_FRONTEND=noninteractive \
    apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       git \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /usr/src/cloudtranscode

# Copy only dependency files first for better layer caching
COPY composer.json composer.lock Makefile ./
RUN make

# Copy the rest of the source
COPY . .
RUN rm -f composer.phar

# ---- runtime ----
FROM public.ecr.aws/docker/library/php:8.2-cli

RUN echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/timezone.ini \
    && DEBIAN_FRONTEND=noninteractive \
       apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       imagemagick \
       ffmpeg \
    && docker-php-ext-install zip \
    && apt-get purge -y libzip-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* \
    && useradd -r -u 1001 -g root worker

COPY --from=builder --chown=worker:root /usr/src/cloudtranscode /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode

USER worker
ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
