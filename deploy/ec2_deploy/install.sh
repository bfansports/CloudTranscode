#!/usr/bin/env bash

# This script install all the requiements on an Ubuntu machine
# It also clone the CloudTranscode project and install the PHP dependencies

# PPAs
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php5

# Update / Upgrade
apt-get -y update
apt-get -y upgrade

# Tools
apt-get -y install unzip
apt-get -y install git
apt-get -y install php5-cli
apt-get -y install php5-curl

#### Converters ####

## ImageMagick (IMAGE) ##
apt-get -y install imagemagick

## Unoconv (DOC) ##
apt-get -y install unoconv

if -z hash ffmpeg 2>/dev/null; then
    ## FFMPEG (VIDEO) ## 
    # See: http://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu
    # Dependencies:
    apt-get -y install autoconf automake build-essential libass-dev libfreetype6-dev libgpac-dev libsdl1.2-dev libtheora-dev libtool libva-dev libvdpau-dev libvorbis-dev libx11-dev libxext-dev libxfixes-dev pkg-config texi2html zlib1g-dev

    # Project install dir
    mkdir ~/ffmpeg_sources

    # Yasm 
    apt-get -y install yasm

    # libx264
    cd ~/ffmpeg_sources
    wget http://download.videolan.org/pub/x264/snapshots/last_x264.tar.bz2
    tar xjvf last_x264.tar.bz2
    cd x264-snapshot*
    ./configure --prefix="$HOME/ffmpeg_build" --bindir="$HOME/bin" --enable-static
    make && make install && make distclean

    # libfdk-aac
    cd ~/ffmpeg_sources
    wget -O fdk-aac.zip https://github.com/mstorsjo/fdk-aac/zipball/master
    unzip fdk-aac.zip
    cd mstorsjo-fdk-aac*
    autoreconf -fiv
    ./configure --prefix="$HOME/ffmpeg_build" --disable-shared
    make && make install && make distclean

    # libmp3lame
    apt-get -y install libmp3lame-dev

    # libopus
    apt-get -y install libopus-dev

    # libvpx
    cd ~/ffmpeg_sources
    wget http://webm.googlecode.com/files/libvpx-v1.3.0.tar.bz2
    tar xjvf libvpx-v1.3.0.tar.bz2
    cd libvpx-v1.3.0
    ./configure --prefix="$HOME/ffmpeg_build" --disable-examples
    make && make install && make clean

    # ffmpeg
    cd ~/ffmpeg_sources
    git clone https://github.com/FFmpeg/FFmpeg.git ffmpeg
    cd ffmpeg
    # ffmpeg version change - Change the version of ffmpeg here. See GitHub to get the right branch or tag
    # Git Tag 2.2.3 (Stable)
    git checkout tags/n2.2.3
    PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig"
    export PKG_CONFIG_PATH
    ./configure --prefix="$HOME/ffmpeg_build" --extra-cflags="-I$HOME/ffmpeg_build/include" \
	--extra-ldflags="-L$HOME/ffmpeg_build/lib" --bindir="$HOME/bin" --extra-libs="-ldl" --enable-gpl \
	--enable-libass --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus \
	--enable-libtheora --enable-libvorbis --enable-libvpx --enable-libx264 --enable-nonfree --enable-x11grab
    make && make install && make distclean
    hash -r && cd ~ && rm -rf ~/ffmpeg_sources

fi;

# Environment
echo "MANPATH_MAP $HOME/bin $HOME/ffmpeg_build/share/man" >> ~/.manpath
. ~/.profile

# Clean
apt-get -y autoremove

## CLOUD TRANSCODE ##
LOGS=$HOME/logs/
mkdir -p $LOGS
INSTALLDIR=$HOME/CloudTranscode

git clone https://github.com/sportarchive/CloudTranscode $INSTALLDIR

# Bootstrap using Makefile
cd $INSTALLDIR && make
