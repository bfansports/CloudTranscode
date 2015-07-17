#!/usr/bin/env bash

set -euo pipefail

apt-get update
apt-get install -y \
        gettext \
        imagemagick \
        autoconf \
        automake \
        cmake \
        mercurial \
        yasm \
        build-essential \
        pkg-config \
        libtool \
        libass5 libass-dev \
        libfreetype6 libfreetype6-dev \
        libsdl1.2debian libsdl1.2-dev \
        libtheora0 libtheora-dev \
        libvorbis0a libvorbisenc2 libvorbisfile3 libvorbis-dev \
        zlib1g zlib1g-dev \
        libvpx1 libvpx-dev \
        libfdk-aac0 libfdk-aac-dev \
        libx264-142 libx264-dev \
        libmp3lame0 libmp3lame-dev \
        libopus0 libopus-dev \
        libxvidcore4 libxvidcore-dev \
        libwebp5 libwebp-dev \
	libxcb-xfixes0 libxcb-shape0 \
        nano

# Build libx265
hg clone https://bitbucket.org/multicoreware/x265 /usr/src/libx265
cd /usr/src/libx265/build/linux
cmake \
    -G "Unix Makefiles" \
    -DCMAKE_INSTALL_PREFIX="/usr/local" \
    -DENABLE_SHARED:bool=off \
    ../../source
make
make install

# Build ffmpeg
curl -o /usr/src/ffmpeg.tar.bz2 https://ffmpeg.org/releases/ffmpeg-2.7.1.tar.bz2
tar -xjv -C /usr/src -f /usr/src/ffmpeg.tar.bz2
cd /usr/src/ffmpeg-2.7.1
export PKG_CONFIG_PATH="/usr/src/ffmpeg/lib/pkgconfig"
./configure \
    --prefix="/usr/local" \
    --pkg-config-flags="--static" \
    --disable-ffplay \
    --disable-ffserver \
    --disable-doc \
    --enable-gpl \
    --enable-version3 \
    --enable-nonfree \
    --enable-libass \
    --enable-libfreetype \
    --enable-libx264 \
    --enable-libx265 \
    --enable-libfdk-aac \
    --enable-libmp3lame \
    --enable-libopus \
    --enable-libtheora \
    --enable-libvorbis \
    --enable-libvpx \
    --enable-libxvid \
    --enable-libwebp
make
make install

# Cleanup build artifacts
cd
apt-get remove -y \
        autoconf \
        automake \
        cmake \
        mercurial \
        yasm \
        build-essential \
        pkg-config \
        libass-dev \
        libfreetype6-dev \
        libsdl1.2-dev \
        libtheora-dev \
        libvorbis-dev \
        zlib1g-dev \
        libvpx-dev \
        libfdk-aac-dev \
        libx264-dev \
        libmp3lame-dev \
        libopus-dev \
        libxvidcore-dev \
        libwebp-dev
apt-get autoremove -y
apt-get clean -y
rm -rf /usr/src/ffmpeg* /usr/src/libx265
