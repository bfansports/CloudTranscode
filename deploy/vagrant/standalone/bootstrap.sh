#!/usr/bin/env bash

export HOME=/home/vagrant
# Path to SRC files
export CT_SRC=$HOME/CloudTranscodeSrc
# Path to your cloudTranscodeConfig.json and env_vars files
export CT_CFG=$HOME/CloudTranscodeConfig
# Configuration filename
export CT_CFGFILE=$CT_CFG/cloudTranscodeConfig.json
# Environment file. Put some bash code in there if you want!
export CT_ENVFILE=$CT_CFG/env_vars

# Check if config file is present
if [ ! -f $CT_CFGFILE ]; then
    echo "Configuration file '$CT_CFGFILE' cannot be found in your '$CT_CFG' folder! Abording..."
    exit 2;
fi

# Load $CT_ENVFILE file and prepare env
if [ -f $CT_ENVFILE ]; then
    # Add to this env, for exec env
    eval `<$CT_ENVFILE`
    # Add to .ctrc for interactive shell
    cat $CT_ENVFILE >> $HOME/.ctrc
    
    # We save the original .profile
    if [ ! -f $HOME/.profile_bak ]; then
	cp $HOME/.profile $HOME/.profile_bak
    fi

    # We add .ctrc to .profile
    cp $HOME/.profile_bak $HOME/.profile
    cat $HOME/.ctrc >> $HOME/.profile
else
    echo "No '$CT_ENVFILE' file found in your '$CT_CFG' folder!"
    echo "Will look for your AWS Key and Secret in your cloudTranscodeConfig.json."
    echo "If not found the scripts will fail."
fi

# Get the roles for this VM
if [ ! -z "$4" ]; then
    export CT_ROLES="$4"
    echo "################################"
    echo "Roles provided to the instance:"
    echo "$CT_ROLES"
    echo "################################"
else
    echo "################################"
    echo "No Roles provided to this VM. Assuming default roles:"
    echo "decider inputPoller validateAsset transcodeAsset"
    echo "################################"
    export CT_ROLES="decider inputPoller validateAsset transcodeAsset"
fi

if [ "$1" == "install" ]; then
    # PPAs
    sudo apt-get install -y python-software-properties software-properties-common
    sudo add-apt-repository -y ppa:ondrej/php5

    # Update / Upgrade
    sudo apt-get -y update
    if [ "$2" == "upgrade" ]; then
	sudo apt-get -y upgrade
    fi
    
    # Tools
    sudo apt-get -y install unzip
    sudo apt-get -y install git
    sudo apt-get -y install php5-cli
    sudo apt-get -y install php5-curl

    
    #### Converters ####

    ## ImageMagick (IMAGE) ##
    sudo apt-get -y install imagemagick

    ## Unoconv (DOC) ##
    sudo apt-get -y install unoconv

    ## FFMPEG (VIDEO) ## 
    # See: http://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu
    # Dependencies:
    sudo apt-get -y install autoconf automake build-essential libass-dev libfreetype6-dev libgpac-dev libtheora-dev libtool libvorbis-dev pkg-config texi2html zlib1g-dev
    
    # Yasm 
    sudo apt-get -y install yasm
    
    # libx264
    mkdir -p $HOME/ffmpeg_sources
    cd $HOME/ffmpeg_sources
    wget http://download.videolan.org/pub/x264/snapshots/last_x264.tar.bz2
    tar xjvf last_x264.tar.bz2
    cd x264-snapshot*
    ./configure --prefix="$HOME/ffmpeg_build" --bindir="$HOME/bin" --enable-static
    make && make install && make distclean

    # libfdk-aac
    cd $HOME/ffmpeg_sources
    wget -O fdk-aac.zip https://github.com/mstorsjo/fdk-aac/zipball/master
    unzip fdk-aac.zip
    cd mstorsjo-fdk-aac*
    autoreconf -fiv
    ./configure --prefix="$HOME/ffmpeg_build" --disable-shared
    make && make install && make distclean

    # libmp3lame
    sudo apt-get -y install libmp3lame-dev

    # libopus
    sudo apt-get -y install libopus-dev

    # libvpx
    cd $HOME/ffmpeg_sources
    wget http://webm.googlecode.com/files/libvpx-v1.3.0.tar.bz2
    tar xjvf libvpx-v1.3.0.tar.bz2
    cd libvpx-v1.3.0
    ./configure --prefix="$HOME/ffmpeg_build" --disable-examples
    make && make install && make clean

    # ffmpeg
    cd $HOME/ffmpeg_sources
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
    hash -r
    rm -rf $HOME/ffmpeg_sources

    # Add ffmpeg to Environment
    echo "MANPATH_MAP $HOME/bin $HOME/ffmpeg_build/share/man" >> $HOME/.manpath
    . $HOME/.profile
    
    # Clean build
    sudo apt-get -y autoremove
    
    # Bootstrap project using Makefile. 
    # Download PHP dependencies using Composer.
    cd $CT_SRC && make 
fi

if [ "$3" == "start" ]; then
    
    ## CLOUD TRANSCODE START ##
    LOGS=$HOME/logs/
    mkdir -p $LOGS
    
    if [ ! -z "$CT_ROLES" ]; then
	# Start PHP scripts based on roles
	echo "#### STARTING CLOUD TRANSCODE SCRIPTS ####"

	for role in $CT_ROLES; do
	    if   [ "$role" == "decider" ]; then
		echo "#-> STARTING DECIDER"
		php $CT_SRC/src/Decider.php -c $CT_CFG/cloudTranscodeConfig.json $DEBUG 2>&1 > $LOGS/Decider.log &
	    elif [ "$role" == "inputPoller" ]; then
		echo "#-> STARTING INPUT_POLLER"
		php $CT_SRC/src/InputPoller.php -c $CT_CFG/cloudTranscodeConfig.json $DEBUG 2>&1 > $LOGS/InputPoller.log &
	    elif [ "$role" == "validateAsset" ]; then
		echo "#-> STARTING VALIDATE_ASSET POLLER"
		php $CT_SRC/src/ActivityPoller.php -c $CT_CFG/cloudTranscodeConfig.json -a $CT_SRC/config/validateInputAndAsset-ActivityPoller.json $DEBUG 2>&1 > $LOGS/ValidateInputAndAsset.log &
	    elif [ "$role" == "transcodeAsset" ]; then
		echo "#-> STARTING TRANSCODE_ASSET POLLER"
		php $CT_SRC/src/ActivityPoller.php -c $CT_CFG/cloudTranscodeConfig.json -a $CT_SRC/config/transcodeAsset-ActivityPoller.json $DEBUG 2>&1 > $LOGS/TranscodeAsset.log &
	    else
		echo "Invalid role '$role'! Abording..."
		exit 2;
	    fi
	done
    else
	echo "No $CT_ROLES provided! Edit your '$CT_ENVFILE' file in your ~/.cloudtranscode/"
    fi
fi
