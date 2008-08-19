#!/bin/bash

# HACK HACK HACK!
FQDNS=/usr/local/nagios/etc/fqdns

# This is the "statshandler" for the Nagios plugin called check_ora_table_space.pl
# It takes the output of the plugin and turns it into stats.
bivalve="/usr/local/yaketystats/bin/bivalve"
rrd_type="GAUGE";
rrd_interval=900;
time=`date +%s`;
# This is the Unit Separator (ASCII 31; type it as C-_).
sep=''
ip=$2
sid=$3
ts=$7

# Simple test of input
echo $* | grep 'size:.*free:' > /dev/null
if [ $? != 0 ]
then
    logger -p local5.info -t JJStats "statshandler exiting because input looks wrong.  Input: $*"
    exit
fi

echo $* | sed -e "s/.*--outputwas size: //" -e "s/MB free://" -e "s/MB.*//" | 
while read size free ; do

  let size=$size*1024*1024
  let free=$free*1024*1024
  
  hostname=`grep -l $ip /usr/local/nagios/etc/hosts/*.cfg`
  hostname=`basename $hostname`
  hostname=${hostname%.cfg}
  # FURTHER HACK!
  hostname=`grep "^$hostname[.]" $FQDNS | awk '{print \$1}'`
  if [ "$hostname" = "" ]
  then
    logger -p local5.info -t JJStats "statshandler can't find FQDN for $ip"
    exit
  fi
  if [ -z "$size" -o -z "$free" ]
  then
    logger -p local5.info -t JJStats "statshandler got bad size ($size) and/or free ($free) for ip=$ip, sid=$sid, ts=$ts"
    exit
  fi

  $bivalve "$sid.$ts" "$hostname" <<EOF
p=/${hostname}/oracle/${sid}/size/${ts}${sep}t=$rrd_type${sep}i=$rrd_interval${sep}ts=$time${sep}v=$size
p=/${hostname}/oracle/${sid}/free/${ts}${sep}t=$rrd_type${sep}i=$rrd_interval${sep}ts=$time${sep}v=$free
EOF
  
done
