FROM alpine:3 AS ffmpeg-downloader
RUN apk add --no-cache curl xz \
    && curl -L https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-linux64-nonfree.tar.xz \
       -o /tmp/ffmpeg.tar.xz \
    && tar -xJf /tmp/ffmpeg.tar.xz -C /tmp \
    && mv /tmp/ffmpeg-master-latest-linux64-nonfree/bin/ffmpeg /usr/local/bin/ffmpeg \
    && mv /tmp/ffmpeg-master-latest-linux64-nonfree/bin/ffprobe /usr/local/bin/ffprobe

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

# Fetch presets submodule in case it wasn't initialized in the build context
RUN if [ ! -f presets/480p-generic.json ]; then \
        rm -rf presets && \
        git clone https://github.com/sportarchive/CloudTranscode-FFMpeg-presets.git presets; \
    fi

# ---- runtime ----
FROM public.ecr.aws/docker/library/php:8.2-cli

RUN echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/timezone.ini \
    && DEBIAN_FRONTEND=noninteractive \
       apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip5 \
       imagemagick \
    && rm -rf /var/lib/apt/lists/* \
    && useradd -r -u 1001 -g root worker

COPY --from=ffmpeg-downloader /usr/local/bin/ffmpeg /usr/local/bin/ffmpeg
COPY --from=ffmpeg-downloader /usr/local/bin/ffprobe /usr/local/bin/ffprobe

COPY --from=builder /usr/local/lib/php/extensions/no-debug-non-zts-20220829/zip.so /usr/local/lib/php/extensions/no-debug-non-zts-20220829/
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-zip.ini /usr/local/etc/php/conf.d/

COPY --from=builder --chown=worker:root /usr/src/cloudtranscode /usr/src/cloudtranscode
WORKDIR /usr/src/cloudtranscode

USER worker
ENTRYPOINT ["/usr/src/cloudtranscode/bootstrap.sh"]
