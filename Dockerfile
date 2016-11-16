FROM php:5.6-cli
MAINTAINER SportArchive, Inc.

RUN echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/timezone.ini
RUN apt-get update \
    && apt-get install -y zlib1g-dev \
    && docker-php-ext-install zip

COPY . /usr/src/cloudprocessingengine
WORKDIR /usr/src/cloudprocessingengine

RUN echo "deb http://ftp.uk.debian.org/debian jessie main" >> "/etc/apt/sources.list"
RUN echo "deb http://ftp.uk.debian.org/debian jessie-backports main" >> "/etc/apt/sources.list"
RUN apt-get update \
    && apt-get install -y git \
    && make

RUN apt-get install -y pkg-config autoconf automake libtool
RUN git clone https://github.com/mstorsjo/fdk-aac.git

RUN apt-get purge -y git \
    && apt-get autoremove -y

RUN apt-get install -y wget autoconf automake build-essential libass-dev libfreetype6-dev \
  libsdl1.2-dev libtheora-dev libtool libva-dev libvdpau-dev libvorbis-dev libxcb1-dev libxcb-shm0-dev \
  libxcb-xfixes0-dev pkg-config texinfo zlib1g-dev
RUN mkdir ~/ffmpeg_sources

RUN apt-get install -y yasm
RUN cd ~/ffmpeg_sources \
    && wget http://www.tortall.net/projects/yasm/releases/yasm-1.3.0.tar.gz \
    && tar xzvf yasm-1.3.0.tar.gz \
    && cd yasm-1.3.0 \
    && ./configure --prefix="$HOME/ffmpeg_build" --bindir="$HOME/bin" \
    && make \
    && make install \
    && make distclean

RUN apt-get install -y libx264-dev
RUN cd ~/ffmpeg_sources \
    && wget http://download.videolan.org/pub/x264/snapshots/last_x264.tar.bz2 \
    && tar xjvf last_x264.tar.bz2 \
    && cd x264-snapshot* \
    && PATH="$HOME/bin:$PATH" ./configure --prefix="$HOME/ffmpeg_build" --bindir="$HOME/bin" --enable-static --disable-opencl \
    && PATH="$HOME/bin:$PATH" make \
    && make install \
    && make distclean

#RUN apt-get install -y cmake mercurial \
#    && cd ~/ffmpeg_sources \
#    && hg clone https://bitbucket.org/multicoreware/x265 \
#    && cd ~/ffmpeg_sources/x265/build/linux \
#    && PATH="$HOME/bin:$PATH" cmake -G "Unix Makefiles" -DCMAKE_INSTALL_PREFIX="$HOME/ffmpeg_build" -DENABLE_SHARED:bool=off ../../source \
#    && make \
#    && make install

RUN cd fdk-aac \
    && ./autogen.sh \
    && ./configure --enable-shared --enable-static \
    && make \
    && make install \
    && ldconfig

RUN cd ~/ffmpeg_sources \
    && wget -O fdk-aac.tar.gz https://github.com/mstorsjo/fdk-aac/tarball/master \
    && tar xzvf fdk-aac.tar.gz \
    && cd mstorsjo-fdk-aac* \
    && autoreconf -fiv \
    && ./configure --prefix="$HOME/ffmpeg_build" --disable-shared \
    && make \
    && make install \
    && make distclean 

#RUN apt-get install -y libmp3lame-dev
#RUN apt-get install -y nasm \
#    && cd ~/ffmpeg_sources \
#    && wget http://downloads.sourceforge.net/project/lame/lame/3.99/lame-3.99.5.tar.gz \
#    && tar xzvf lame-3.99.5.tar.gz \
#    && cd lame-3.99.5 \
#    && ./configure --prefix="$HOME/ffmpeg_build" --enable-nasm --disable-shared \
#    && make \
#    && make install \
#    && make distclean

#RUN apt-get install -y libopus-dev
#RUN cd ~/ffmpeg_sources \
#    && wget http://downloads.xiph.org/releases/opus/opus-1.1.2.tar.gz \
#    && tar xzvf opus-1.1.2.tar.gz \
#    && cd opus-1.1.2 \
#    && ./configure --prefix="$HOME/ffmpeg_build" --disable-shared \
#    && make \
#    && make install \
#    && make clean

#RUN cd ~/ffmpeg_sources \
#    && wget http://storage.googleapis.com/downloads.webmproject.org/releases/webm/libvpx-1.5.0.tar.bz2 \
#    && tar xjvf libvpx-1.5.0.tar.bz2 \
#    && cd libvpx-1.5.0 \
#    && PATH="$HOME/bin:$PATH" ./configure --prefix="$HOME/ffmpeg_build" --disable-examples --disable-unit-tests \
#    && PATH="$HOME/bin:$PATH" make \
#    && make install \
#    && make clean

RUN cd ~/ffmpeg_sources \
    && wget http://ffmpeg.org/releases/ffmpeg-snapshot.tar.bz2 \
    && tar xjvf ffmpeg-snapshot.tar.bz2 \
    && cd ffmpeg \
    && PATH="$HOME/bin:$PATH" PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig" ./configure \
	  --prefix="$HOME/ffmpeg_build" \
	  --pkg-config-flags="--static" \
	  --extra-cflags="-I$HOME/ffmpeg_build/include" \
	  --extra-ldflags="-L$HOME/ffmpeg_build/lib" \
	  --bindir="$HOME/bin" \
	  --enable-gpl \
	  --enable-libass \
	  --enable-libfdk-aac \
	  --enable-libfreetype \
#	  --enable-libmp3lame \
#	  --enable-libopus \
	  --enable-libtheora \
	  --enable-libvorbis \
#	  --enable-libvpx \
	  --enable-libx264 \
#	  --enable-libx265 \
	  --enable-nonfree \
    && PATH="$HOME/bin:$PATH" make \
    && make install \
    && make distclean \
    && hash -r

RUN touch /etc/profile.d/alias.sh \
    && echo "alias ffmpeg='~/bin/ffmpeg'" >> /etc/profile.d/alias.sh \
    && echo "alias ffprobe='~/bin/ffprobe'" >> /etc/profile.d/alias.sh \
    && echo "alias ffserver='~/bin/ffserver'" >> /etc/profile.d/alias.sh \
    && echo "alias vsyasm='~/bin/vsyasm'" >> /etc/profile.d/alias.sh \
    && echo "alias x264='~/bin/x264'" >> /etc/profile.d/alias.sh \
    && echo "alias yasm='~/bin/yasm'" >> /etc/profile.d/alias.sh \
    && echo "alias ytasm='~/bin/ytasm'" >> /etc/profile.d/alias.sh \
    && sh /etc/profile.d/alias.sh
RUN mv ~/bin/* /bin

ENV INPUT_QUEUE <url of input queue>
ENV OUTPUT_QUEUE <url of output queue>
ENV AWS_DEFAULT_REGION <region>
ENV AWS_ACCESS_KEY_ID <access key>
ENV AWS_SECRET_ACCESS_KEY <secret>

ENTRYPOINT ["/usr/src/cloudprocessingengine/bootstrap.sh"]
