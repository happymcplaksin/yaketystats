%define _prefix /usr/local
%define mydir %{_builddir}/%{name}-%{version}

Summary: Collect stats and report them to a yaketystats server
Name: yaketystats
Group: Applications/System
License: GPL
Provides: %{name}
Version: 1.3.4
Release: 1
Buildroot: %{_tmppath}/%{name}-%{version}-root
Source: %{name}-%{version}.tar.gz
URL: http://yaketystats.org/

%description
Yaketystats collects your stats.
And reports them to the server.
Watch the stats go up and down.
To keep your servers from going splats.

http://yaketystats.org/

# We'll probably need this or these
#%define _unpackaged_files_terminate_build 0
#%define _missing_doc_files_terminate_build 0

%package server
Group: Applications/System
Summary: Stuff stats into rrd files
Requires: %{name} = %{version}-%{release}, %{name} = %{version}-%{release}

%description server
Your stats need a place to go;
You are tired of your script called "go".
May this server serve you well.
Before your boxes go to hell.

%prep
%setup -c

%clean
rm -rf %{buildroot}

%install
rm -rf %{buildroot}
# Stole this idea from the Puppet spec files
mkdir -p %{buildroot}/%{_prefix}/%{name}/etc
touch %{buildroot}/%{_prefix}/%{name}/etc/stats.conf
mkdir -p %{buildroot}/var/lib/%{name}
touch %{buildroot}/var/lib/%{name}/fqdn
mkdir -p %{buildroot}/usr/share/doc/%{name}-%{version}-%{release}
touch %{buildroot}/usr/share/doc/%{name}-%{version}-%{release}/README
mkdir -p %{buildroot}/var/%{name}/incoming
mkdir -p %{buildroot}/var/%{name}/locks
mkdir -p %{buildroot}/var/%{name}/outgoing
mkdir -p %{buildroot}/var/%{name}/tmp
mkdir -p %{buildroot}/var/lib/%{name}

install -d -m 0755 %{buildroot}/%{_prefix}/%{name}
# install -m 0755 %{buildroot}/%{_prefix}/%{name}/bin/bivalve
# install -m 0755 %{buildroot}/%{_prefix}/%{name}/bin/collect
# install -m 0755 %{buildroot}/%{_prefix}/%{name}/bin/comproll
# install -m 0755 %{buildroot}/%{_prefix}/%{name}/bin/get_fqdn
# install -m 0755 %{buildroot}/%{_prefix}/%{name}/bin/roll_messages
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/bin
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/cgi-bin
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/etc
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/etc/banner
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/etc/vista
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/lib
install -d -m 0755 %{buildroot}/%{_prefix}/%{name}/libexec
install -Dp -m 0755 %{mydir}/bin/* %{buildroot}%{_prefix}/%{name}/bin
install -Dp -m 0755 %{mydir}/cgi-bin/* %{buildroot}%{_prefix}/%{name}/cgi-bin
install -Dp -m 0755 %{mydir}/etc/[c-uw-z]* %{buildroot}%{_prefix}/%{name}/etc
install -Dp -m 0755 %{mydir}/etc/banner/* %{buildroot}%{_prefix}/%{name}/etc/banner
install -Dp -m 0755 %{mydir}/etc/vista/* %{buildroot}%{_prefix}/%{name}/etc/vista
install -Dp -m 0755 %{mydir}/lib/* %{buildroot}%{_prefix}/%{name}/lib
install -Dp -m 0755 %{mydir}/libexec/* %{buildroot}%{_prefix}/%{name}/libexec
#install -d -m 0755 %{buildroot}/var/lib/%{name}
install -m 0755 %{mydir}/.forward %{buildroot}/%{_prefix}/%{name}

%files
%defattr(-, root, root, 0755)
/usr/share/doc/%{name}-%{version}-%{release}/README
#%dir /usr/local/%{name}
/usr/local/%{name}/.forward
/usr/local/%{name}/bin/[abd-r]*
/usr/local/%{name}/bin/collect
%dir /usr/local/%{name}/etc
/usr/local/%{name}/etc/banner
/usr/local/%{name}/etc/client.conf
/usr/local/%{name}/etc/config
/usr/local/%{name}/etc/crontab
/usr/local/%{name}/etc/ignores
%config(noreplace) /usr/local/%{name}/etc/snmp.config
/usr/local/%{name}/etc/stats.conf.default
/usr/local/%{name}/etc/vista
/usr/local/%{name}/etc/webct_monitor.config
/usr/local/%{name}/lib
/usr/local/%{name}/libexec

%config(noreplace) %{_prefix}/%{name}/etc/stats.conf
%attr(0755, stats, stats)/var/%{name}
#%attr(0755, stats, stats)/var/%{name}/incoming
#%attr(0755, stats, stats)/var/%{name}/locks
#%attr(0755, stats, stats)/var/%{name}/outgoing
#%attr(0755, stats, stats)/var/%{name}/tmp
%attr(0755, stats, stats)/var/lib/%{name}
%config(noreplace) /var/lib/%{name}/fqdn

%files server
%defattr(-, root, root, 0755)
/usr/local/%{name}/bin/check_rrds
/usr/local/%{name}/bin/comproll
/usr/local/%{name}/bin/stuffer
%config(noreplace) /usr/local/%{name}/etc/server.conf
/usr/local/%{name}/cgi-bin/store.php

#%doc(missingok) /usr/doc/%{name}-%{version}/you.wish
#%doc /usr/man/man1/HAHAHAHA

# NOTE NOTE NOTE:  This is just the preinst file included here.  Should automate.
%pre
#!/bin/sh

# pre-install script for stats
#
# Create stats user and group 

CHROOT=""
ROOTDIR=/
if [ "`uname`" = SunOS ]
  then
  if [ ! -z "${PKG_INSTALL_ROOT}" ]
    then
    ROOTDIR="${PKG_INSTALL_ROOT}/"
    CHROOT="/usr/sbin/chroot ${ROOTDIR}"
  fi
fi

OUR_GROUP=stats
OUR_GID=11994
OUR_USER=stats
OUR_UID=11994
DIR=/usr/local/%{name}

grep "^$OUR_GROUP:" $ROOTDIR/etc/group > /dev/null
if [ $? = 0 ]
  then
  echo "Group $OUR_GROUP exists so I refuse to add it."
else
  $CHROOT /usr/sbin/groupadd -g $OUR_GID $OUR_GROUP
  if [ $? != 0 ]
  then
    echo "Ugh!  I'm dying an ugly death!  groupadd had an error :("
    exit 41
  fi
fi

grep "^$OUR_USER:" $ROOTDIR/etc/passwd > /dev/null
if [ $? = 0 ]
  then
  echo "User $OUR_USER exists so I refuse to add it."
  status=$?
else
  $CHROOT /usr/sbin/useradd -m -d $DIR -u $OUR_UID -g $OUR_GID $OUR_USER
  status=$?
fi
if [ $status != 0 ]
then
  echo "Ugh!  I'm dying an ugly death!  useradd had an error ($status).  Perhaps $DIR already exists?"
  exit 42
fi

chmod 755 $DIR
if [ $? != 0 ]
then
  echo "chmod 755 failed"
  exit 43
fi

# NOTE NOTE NOTE:  This is just the postinst file included here.  Should automate.
%post
#!/bin/sh

# post-install for stats
#
# - figure out FQDN
# - add logcheck entries
# - install default crontab
# - install default config file


NAME=yaketystats
status=0
OUR_USER=stats
OUR_GROUP=stats
OUR_GID=11994
OUR_UID=11994

CHROOT=""
ROOTDIR=/
uname=`uname`

if [ "$uname" = SunOS ]
  then
  if [ ! -z "${PKG_INSTALL_ROOT}"  ]
    then
    ROOTDIR="${PKG_INSTALL_ROOT}/"
    CHROOT="/usr/sbin/chroot ${ROOTDIR}"
  fi
fi

if [ "$uname" = Linux ]; then
  CP=$ROOTDIR/bin/cp
  SU=$ROOTDIR/bin/su
  GREP=$ROOTDIR/bin/grep
  CAT=$ROOTDIR/bin/cat
  CHMOD=$ROOTDIR/bin/chmod
  CHOWN=$ROOTDIR/bin/chown
  MKDIR=$ROOTDIR/bin/mkdir
else
  CP=$ROOTDIR/usr/bin/cp
  SU=$ROOTDIR/usr/bin/su
  GREP=$ROOTDIR/usr/bin/grep
  CAT=$ROOTDIR/usr/bin/cat
  CHMOD=$ROOTDIR/usr/bin/chmod
  CHOWN=$ROOTDIR/usr/bin/chown
  MKDIR=$ROOTDIR/usr/bin/mkdir
fi

# Maybe append to logcheck.ignore and logcheck.violation.ignore
#
# First try to find it
ignore_file=$ROOTDIR/etc/logcheck/ignore.d.server/local
if [ ! -f $ignore_file ]
then
  ignore_file=$ROOTDIR/etc/logcheck.ignore
  if [ ! -f $ignore_file ]
  then
    ignore_file=$ROOTDIR/usr/local/etc/logcheck.ignore
    if [ ! -f $ignore_file ]
    then
      echo "I can't find logcheck.ignore.  Update it yourself."
      ignore=_file""
    fi
  fi
fi
if [ "$ignore_file" != "" ]
then
  ignore_line="JJStats"
  grep "$ignore_line" $ignore_file > /dev/null
  if [ $? != 0 ]
  then
    echo "Appending this to $ignore_file:"
    echo "$ignore_line"
    echo "$ignore_line" >> $ignore_file
  fi
fi

ignore_file=$ROOTDIR/etc/logcheck/violations.ignore.d/local
if [ ! -f $ignore_file ]
then
  ignore_file=$ROOTDIR/etc/logcheck.violations.ignore
  if [ ! -f $ignore_file ]
  then
    ignore_file=$ROOTDIR/usr/local/etc/logcheck.violations.ignore
    if [ ! -f $ignore_file ]
    then
      echo "I can't find logcheck.violations.ignore.  Update it yourself."
      ignore=_file""
    fi
  fi
fi
if [ "$ignore_file" != "" ]
then
  ignore_line="JJStats"
  grep "$ignore_line" $ignore_file > /dev/null
  if [ $? != 0 ]
  then
    echo "Appending this to $ignore_file:"
    echo "$ignore_line"
    echo "$ignore_line" >> $ignore_file
  fi
fi

# The only place the poor stats user can write
$MKDIR -p $ROOTDIR/var/$NAME
$CHOWN $OUR_UID:$OUR_GID $ROOTDIR/var/$NAME
for dir in incoming outgoing outgoing/uploads locks tmp
do
  if [ ! -d $ROOTDIR/var/$NAME/$dir ]
    then
    $MKDIR -p $ROOTDIR/var/$NAME/$dir
  fi
done
$CHOWN -R $OUR_UID:$OUR_GID $ROOTDIR/var/$NAME

ROOTDIR=/
CHROOT=""

if [ "`uname`" = SunOS ]
  then
  if [ ! -z "${PKG_INSTALL_ROOT}"  ]
    then
    ROOTDIR="${PKG_INSTALL_ROOT}/"
    CHROOT="/usr/sbin/chroot ${ROOTDIR}"
  fi
fi

MYHOME=$ROOTDIR/usr/local/$NAME

CRONTAB_FILE="$ROOTDIR/var/spool/cron/crontabs/$OUR_USER"
DEFAULT_CONF="$MYHOME/etc/stats.conf.default"
# These are used after chroot
CRONTAB="/usr/bin/crontab"
DEFAULT_CRONTAB="/usr/local/$NAME/etc/crontab"

status=0
uname=`uname`
DEBIAN=""
DEBIAN_VERSION="/etc/debian_version"

if [ "$uname" = Linux ]; then
  CP=$ROOTDIR/bin/cp
  # This is used after chroot
  SU=/bin/su
  GREP=$ROOTDIR/bin/grep
  CAT=$ROOTDIR/bin/cat
  CHMOD=$ROOTDIR/bin/chmod
  CHOWN=$ROOTDIR/bin/chown
  MKDIR=$ROOTDIR/bin/mkdir
  CRONTAB_ALLOW=$ROOTDIR/etc/cron.d/cron.allow
  CRONTAB_DENY=$ROOTDIR/etc/cron.d/cron.deny
  if [ -f $DEBIAN_VERSION ]; then
    DEBIAN=1
  fi
else
  CP=$ROOTDIR/usr/bin/cp
  # This is used after chroot
  SU=/usr/bin/su
  GREP=$ROOTDIR/usr/bin/grep
  CAT=$ROOTDIR/usr/bin/cat
  CHMOD=$ROOTDIR/usr/bin/chmod
  CHOWN=$ROOTDIR/usr/bin/chown
  MKDIR=$ROOTDIR/usr/bin/mkdir
  if [ "$uname" = "SunOS" ]; then
    CRONTAB_ALLOW=$ROOTDIR/etc/cron.d/cron.allow
    CRONTAB_DENY=$ROOTDIR/etc/cron.d/cron.deny
  else
  # Assume HP-UX
    CRONTAB_ALLOW=$ROOTDIR/var/adm/cron/cron.allow
    CRONTAB_DENY=$ROOTDIR/var/adm/cron/cron.deny
  fi
fi

# If the crontab looks like it has our stuff in it, do nothing
$GREP collect $CRONTAB_FILE > /dev/null 2>&1
status=$?

if [ $status != 0 ]; then
  # - If cron.allow exists, make sure stats is in it. That's all.
  if [ -f $CRONTAB_ALLOW ]; then
    $GREP $OUR_USER $CRONTAB_ALLOW > /dev/null
    if [ $? != 0 ]; then
      echo $OUR_USER >> $CRONTAB_ALLOW
    fi
  else
    # - If cron.deny exists and cron.allow does not exist, make sure
    #   stats is not in cron.deny. That's all.
    if [ -f $CRONTAB_DENY ]; then
      $GREP $OUR_USER $CRONTAB_DENY > /dev/null
      if [ $? = 0 ]; then
	echo "Ack!  $OUR_USER is explicitly denied access to cron.  Bailing."
	exit 7
      fi
    else
      # - If neither exists, then if you're not on Debian, create
      #   cron.allow and put stats in it.
      if [ "$DEBIAN" = "" ]; then
	echo $OUR_USER >> $CRONTAB_ALLOW
      fi
    fi
  fi
  $CHROOT $SU $OUR_USER -c "$CRONTAB $DEFAULT_CRONTAB"
fi

# For Solaris, put 'NP' into stats' password field in /etc/shadow
if [ "$uname" = SunOS ]
then
  $GREP "^$OUR_USER:" $ROOTDIR/etc/shadow > /dev/null
  if [ $? != 0 ]
  then
    echo "Gack!  $OUR_USER is not in $ROOTDIR/etc/shadow.  I am helpless!"
    exit 4
  fi
  $GREP "^$OUR_USER:NP:" $ROOTDIR/etc/shadow > /dev/null
  if [ $? != 0 ]
  then
    OUMASK=`umask`
    umask 077
    TMP="$ROOTDIR/var/$NAME/tmp/shadow"
    SHADOW="$ROOTDIR/etc/shadow"
    echo "Hmm, bad-looking password field for $OUR_USER in $SHADOW.  Making it be 'NP'"
    cp $SHADOW $TMP.bk
    if [ $? != 0 ]
    then
      echo "Ack!  Backup of $SHADOW failed.  Bailing."
      rm -f $TMP*
      exit 6
    fi
    sed "s/^$OUR_USER:[^:]*:/$OUR_USER:NP:/" $SHADOW > $TMP
    if [ $? != 0 ]
    then
      echo "Ack!  sed failed.  Bailing."
      rm -f $TMP*
      exit 5
    fi
    cp $TMP $SHADOW
    if [ $? != 0 ]
    then
      echo "Ack ack ack!  Copying $TMP to $SHADOW failed!!  HELP!!  Orig shadow file is $TMP.bk.  Bailing."
      rm -f $TMP*
      exit 7
    fi
    rm -f $TMP*
    umask $OUMASK
  fi
fi

#CONF="$MYHOME/etc/`$CAT $FQDN`.conf"
CONF="$MYHOME/etc/stats.conf"
if [ ! -s $CONF ]; then
  echo "Creating conf file based on $DEFAULT_CONF"
  $CP $DEFAULT_CONF $CONF
else
  echo "$CONF exists.  Not touching it."
fi

exit 0

# NOTE NOTE NOTE:  This is just the postrm file included here.  Should automate.
%postun
# - Delete crontab
# - Delete user and group
# - Delete logcheck entry
# - Delete syslog entry
OUR_USER=stats
ROOTDIR=/
uname=`uname`
USERDEL=$ROOTDIR/usr/sbin/userdel
GROUPDEL=$ROOTDIR/usr/sbin/groupdel
EXPR=$ROOTDIR/usr/bin/expr

# Maybe I should just use PATH instead of all these variables.
if [ "$uname" = Linux ]; then
  RM=$ROOTDIR/bin/rm
  SU=$ROOTDIR/bin/su
  CRONTAB_R="/usr/bin/crontab -u $OUR_USER -r"
  PS=$ROOTDIR/bin/ps
  GREP=$ROOTDIR/bin/grep
  SLEEP=$ROOTDIR/bin/sleep
else
  RM=$ROOTDIR/usr/bin/rm
  SU=$ROOTDIR/usr/bin/su
  CRONTAB_R="/usr/bin/crontab -r $OUR_USER"
  PS=$ROOTDIR/usr/bin/ps
  GREP=$ROOTDIR/usr/bin/grep
  SLEEP=$ROOTDIR/usr/bin/sleep
fi

# Only wipe this when purging under Debian.  Do other OSes have something
# similar to purge?
if [ "$uname" = "Linux" ]; then
  $RM -rf /usr/local/%{name}
fi

# Wipe out crontab so the running cron stops trying to run jobs as stats
$CRONTAB_R

# There has got to be a standard way of doing this, but maybe not a way
# that's standard across OSes.
#
# OK, I'm too lazy to do locking right now.  So try waiting a bit.
# (Locking idea:  This script could create a file which means "no more stats
# processes may start" and then wait for all stats processes to end, and then
# kill stats processes that haven't ended after a time interval.  Which would
# require the loop below anyhow :)

tries=5
echo "Trying to delete $OUR_USER user.  May take up to "
echo "`$EXPR $tries \* $tries` seconds..."
otries=$tries
while [ $otries -gt 0 ]; do
  itries=$tries
  # Look for processes owned by $OUR_USER.
  # NOTE:  There is a space and a tab character in the []'s!
  while [ $itries -gt 0 ]; do
    $PS -ef | $GREP "^[ 	]*${OUR_USER}" > /dev/null
    # Looks safe, let's try it! 
    if [ $? = 1 ]; then
      $USERDEL $OUR_USER
      u_stat=$?
      if [ $u_stat = 0 ]; then
        u_del_success=1
        # OK, some OSes (Linux!) delete the group when you run userdel so
        # check whether the group exists first
        $GREP '^stats:' /etc/group > /dev/null
        if [ $? = 0 ]; then
          # Sheesh!
          if [ "$uname" = "SunOS" ]; then
            TMPDIR=/etc $GROUPDEL $OUR_USER
            g_stat=$?
          else
            $GROUPDEL $OUR_USER      
            g_stat=$?
          fi
          if [ $g_stat = 0 ]; then
            g_del_success=1
          fi
        else
          # It group never existed, success!
          g_del_success=1
        fi
      fi
      # If both succeeded, we're done!
      if [ "$u_del_success" = 1 -a "$g_del_success" = 1 ]; then
        echo "Success!"
        itries=-10
        otries=-10
      fi
    fi
    itries=`$EXPR $itries - 1`
    $SLEEP 1
  done
  otries=`$EXPR $otries - 1`
  $SLEEP 1
done

if [ $itries -gt -10 ]; then
  echo "I tried really hard but could not delete the user and/or group named $OUR_USER"
  echo "You should check it out."
fi

echo
echo "You should remove the stats user from cron.allow.  You might also want to remove the stats"
echo "lines from syslog.conf and logcheck.ignore."
echo "That is, this script does none of that (yet).  "
echo
echo "If you never want to use stats again you should 'rm -rf /usr/local/%{name}'"
echo "and 'rm -rf /var/%{name}'"

%changelog
* Wed Oct 31 2007 Mark Plaksin <happy@yaketystats.org>
- First try
