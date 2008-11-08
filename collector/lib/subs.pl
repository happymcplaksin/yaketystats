# Copyright (C) 2008 Board of Regents of the University System of Georgia

# This file is part of YaketyStats (see http://yaketystats.org/).
#
# YaketyStats is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, version 2.
#
# YaketyStats is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with YaketyStats.  If not, see <http://www.gnu.org/licenses/>.

use IO::Socket;
use strict;
use warnings;
use Fcntl qw (:flock :mode); # get LOCK_* and S_IF*
use Data::Dumper;
$Data::Dumper::Purity = 1;
$Data::Dumper::Deepcopy = 1;

#require "$ENV{'HOME'}/lib/include";
our ($g_hostname_file, $g_get_fqdn, $g_debug, $g_debug_level, $g_exit, $g_os);
# TODO:  This is from sloppy obmon code.  Clean!
our ($cmd_debug, $spec, $tape, $protection, @tape_list, $free_now, $label, $full,
     $os, $cmd, $path, @labels, $location, $g_user_agent);
my %months =
          ( Jan => 0,
            Feb => 1,
            Mar => 2,
            Apr => 3,
            May => 4,
            Jun => 5,
            Jul => 6,
            Aug => 7,
            Sep => 8,
            Oct => 9,
            Nov => 10,
            Dec => 11 );

# Assumes only one occurrence of elt in array
sub array_delete {
  my ($elt, $aref) = @_;

  my ($size, $i);

  $size = scalar @$aref;
  for ($i = 0; $i < $size; $i++) {
    if ( $elt eq @$aref[$i] ) {
      splice (@$aref, $i, 1);
      return ();
    }
  }
  mylog ("Ack!  Didn't find $elt in $aref");
}

sub string_member {
  my ($item, @list) = @_;
  my ($elt);

  foreach $elt (@list) {
    if ( $elt eq $item ) {
      return (1);
    }
  }
  return (0);
}

sub string_match {
  my ($item, @list) = @_;
  my ($elt);

  foreach $elt (@list) {
    if ( $elt =~ /^$item/ ) {
      return (1);
    }
  }
  return (0);
}

sub return_fh {
    local *FH;
    return *FH;
}

sub unlock_fh {
  my ($fh) = @_;
  my ($file, $sub) = (caller(1))[1, 3];
  flock ($fh, LOCK_UN) || warn "$file, $sub, Can't unlock: $!";
  close($fh);
}

sub lock_fh {
  my ($fh,$limit) = @_;
  my ($sleep, $total);
  $sleep = 5;
  # If no limit supplied, default to 30 seconds
  if ( !defined ($limit) ) {
    $limit = 30;
  }
  # If flock exists, wait for it to go away
  while ( ! flock ($fh, LOCK_EX|LOCK_NB) ) {
    debug ("lock_fh is sleeping $sleep seconds waiting for lock.");
    sleep ($sleep);
    $total += $sleep;
    # But don't wait too long
    if ( $total > $limit ) {
      fileit ("timed out waiting for lock ($total seconds).  Exiting.");
      return (0);
    }
  }
  return (1);
}

sub my_unlock {
  my ($file, $sub) = (caller(1))[1, 3];
  flock (LOCK, LOCK_UN) || warn "$file, $sub, Can't unlock: $!";
}

sub my_lock {
  my ($limit) = @_;
  my ($sleep, $total);
  $sleep = 5;
  # If no limit supplied, default to 30 seconds
  if ( !defined ($limit) ) {
    $limit = 30;
  }
  # If flock exists, wait for it to go away
  while ( ! flock (LOCK, LOCK_EX|LOCK_NB) ) {
    debug ("my_lock is sleeping $sleep seconds waiting for lock.");
    sleep ($sleep);
    $total += $sleep;
    # But don't wait too long
    if ( $total > $limit ) {
      fileit ("timed out waiting for lock ($total seconds).  Exiting.");
      exit (43);
    }
  }
}

sub run_prg {
  my ($cmdline, $timeout, $ignore_stderr) = @_;
  my ($status, @data, $redirect);

  # Wrap the program run in alarm in case it never exits
  eval {
    local $SIG{ALRM} = sub { die "alarm\n" };
    alarm $timeout;

    if ( defined ($ignore_stderr) && $ignore_stderr == 1 ) {
      $redirect = "2> /dev/null";
    } else {
      $redirect = "2>&1";
    }
    # 11/3/5:  Backticks work but open does not.  I don't understand.
    @data = `$cmdline $redirect`;
    $status = $? >> 8;
    alarm 0;
  };

  # If alarm was triggered, add a message to @data and return -1
  if ($@) {
      # If we're here because the eval'd code got a signal *other* than ALRM,
      # something really weird is going on!
      if ( $@ ne "alarm\n" ) {
        push (@data, "Really weird non-alarm ($@) death in run_prg for $cmdline\n");
      }
      # Otherwise it's a normal timeout
      else {
        push (@data, "Timeout for $cmdline");
      }
      return (-1, @data);
  }
  return ($status, @data);
}

# Get FQDN and cache it in a file.  If the cache file already exists, just read it
# and return the first line.
sub get_hostname {
  my ($status, @data, $cmd);
  local *F;

  # If cache file exists, return first line of it
  # or bail if it's not useful
  if ( -f $g_hostname_file ) {
    open (F, $g_hostname_file) || die "can't open $g_hostname_file: $!";
    @data = <F>;
    close (F);
    if ( defined ($data[0]) ) {
      chomp ($data[0]);
      if ( $data[0] ne '' ) {
	return ($data[0]);
      } else {
	fileit ("No FQDN found.  I give up.", "err");
	exit (1);
      }
    } else {
      fileit ("No FQDN found.  I give up.", "err");
      exit (1);
    }
  }

  # Otherwise, run goofy get_fqdn script, save to cache file, and return FQDN.
  $cmd = $g_get_fqdn;
  ($status, @data) = run_prg ($g_get_fqdn, 10);
  if ( $status != 0 ) {
    fileit ("Bad status for $cmd: $status.  The output was:  @data\n");
    fileit ("No FQDN found.  I give up.", "err");
    exit (3);
  }

  open (F, ">$g_hostname_file") || die "can't write $g_hostname_file: $!";
  print F $data[0];
  close (F);
  chomp($data[0]);
  return $data[0];
}

sub debug {
  my ($message, $level) = @_;

  if ( $g_debug &&
       (! defined ($level) || $level <= $g_debug_level) ) {
    fileit ("$message", "debug");
  }
}

our $g_default_timeout;
# Do a GET.  If $truefalse is defined, return 0 for 200 OK and 1 for anything else
sub http_get {
  my ($host, $port, $uri, $truefalse) = @_;
  my ($sock, $resp, $nl);

  $nl = "\r\n";
  $sock = IO::Socket::INET->new(PeerAddr => "$host:$port",
                                Timeout => $g_default_timeout);
  if ( !defined ($sock) || !$sock ) {
    fileit ("Darn sock!  $@\n");
    return (8);
  }

  # Heh, use HTTP 1.1 so name-based virtual hosts work!
  print $sock "GET $uri HTTP/1.1${nl}" .
              "User-Agent: ${g_user_agent}${nl}" .
              "Host: ${host}${nl}" .
              "Connection: close${nl}${nl}";
  $resp = <$sock>;
  if ( ! defined ($sock) ) {
    fileit ("Ack!  No response!  Does this help:  $!\n");
    close ($sock);
    return (9);
  }
  if ( defined ($truefalse) ) {
    if ( $resp =~ /200 OK/ ) {
      close ($sock);
      return (0);
    } else {
      close ($sock);
      return (1);
    }
  } else {
    if ( $resp !~ /200 OK/ ) {
      fileit ("Ack!  Bad response:  ${resp}.  Does this help:  $!\n");
      close ($sock);
      return (10);
    }
    debug ($resp);
    close ($sock);
    return (0);
  }
}

sub check_vars {
  my ($exit, @list) = @_;
  my ($i, @bad);

  for ($i = 0; $i < @list; $i++) {
    if ( ! defined ($list[$i]) ) {
      push (@bad, $list[$i+1]);
    }
    $i++;
  }
  if ( defined ($bad[0]) ) {
    my (undef, undef, $sub) = (caller (1))[1,2,3];
    fileit ("Undefined variables from $sub:  @bad");
    if ( $exit ) {
      fileit ("exiting because of badness in check_vars.", "err");
      exit (22);
    }
    return (1);
  }
  return (0);
}

# syslog summaries and log details to /var/.../messages.  cron job rolls
# messages file once a day
# Optional second arg is syslog priority (debug, info, etc)
#
# alert
# crit
# debug
# emerg
# err
# info
# notice
# warning
our ($g_message_file, $g_summary_file, $g_fileit_mode, $g_syslog_facility,
     $g_syslog);
sub fileit {
  my ($text, $priority) = @_;
  my ($summary, $stack);
  my $date = localtime ();
  $date =~ s/\s+/|/g;

  local *F;
  # Generate a summary based on $where and the first line of $text
  $summary = $text;
  $summary =~ s/\n.*//g;
  $stack = callstack ();
  $summary = "$$: $stack: $summary";

  # Only print to screen when running scripts in test or debug mode
  if ( $g_debug ||
       (defined ($g_fileit_mode) && $g_fileit_mode eq "test") ) {
    print "$date: $$: $stack: $text\n";
    return ();
  }

  # In any case, log to syslog or messages files
  #
  # Print the summary to the summary file.
  if ( $g_syslog == 1 ) {
    my_syslog($g_syslog_facility, $priority, "$stack: $summary", 1);
  } else {
    open (F, ">>$g_summary_file") || die "Help!  Can't open $g_summary_file: $!";
    print F "$date: $summary\n";
    close (F);
  }
  # And the full message to syslog or the messages file.
  open (F, ">>$g_message_file") || die "Help!  Can't open $g_message_file: $!";
  print F "$date: $$: $stack: $text\n";
  close (F);
}

# Sadness.  If only Sys::Syslog actually worked across the board.
our $g_syslog_tag;
sub my_syslog {
  my ($facility, $priority, $message, $nofileit) = @_;
  my ($cmd, $status, @data);

  if ( ! defined ($priority) ) {
    $priority = "info";
  }

  # Only keep good chars
  $message =~ s/[^-0-9a-z<>:_\/.= ]//ig;
  $cmd = "logger -p $facility.$priority -t $g_syslog_tag '$message'";
  ($status, @data) = run_prg ($cmd, $g_default_timeout);
  if ( $status !=0 ) {
    if ( $nofileit == 1 ) {
      print "Bad status for $cmd:  $status.  Output was:  @data\n";
    } else {
      fileit ("Bad status for $cmd:  $status.  Output was:  @data\n");
    }
    return (undef);
  }
}

# parse_cmd:  Run a command and return some fields from one line of the output
#
# parse_cmd ($cmd, $timeout, $match, $wipe, $sep, $delete, @fields)
# cmd     = pathname of command
# match   = NUMBER or REGEXP.  If number (possibly negative), read that line number
#           of output otherwise, find matches for regexp return a list of items from
#           matching lines.
# wipe    = regexp to delete from line
# sep     = regexp used as separator between fields
# delete  = regexp of lines to delete from output
# ignore_retval = set to 1 if you want to ignore the return value.  Sometimes you need to.
# @fields = list of fields to return after splitting on sep
sub parse_cmd {
  my ($cmd, $timeout, $match, $wipe, $sep, $delete, $ignore_retval, @fields) = @_;
  my ($status, @data, $field, $found, $i, $line, @return, @lines);

  debug ("parse_cmd cmd is $cmd.\n");
  ($status, @data) = run_prg ($cmd, $timeout);
  if ( $status !=0 && $ignore_retval != 1 ) {
    fileit ("Bad status for $cmd:  $status.  Output was:  @data\n");
    return (undef);
  }

  # Delete lines matching $delete regexp.
  my @temp;
  if ( defined ($delete) ) {
    foreach $line (@data) {
      if ( $line !~ /$delete/ ) {
        push (@temp, $line);
      }
    }
    @data = @temp;
  }

  # If $match is a (possibly negative) number, it's a line number.
  if ( $match =~ /^[-]*[0-9]+/ ) {
    #$line = $data[$match];
    push (@lines, $data[$match]);
  } else {
    # Otherwise, it's a regexp to find in the output
    $found = 0;
  LOOP: foreach $i (@data) {
      if ( $i =~ /$match/ ) {
        $found = 1;
        debug ("Found line: $i");
        #$line = $i;
        #last LOOP;
        push (@lines, $i);
      }
    }
    if ( ! $found ) {
      fileit ("No match for $match from $cmd.  Exiting\n", "err");
      exit (13);
    }
  }
  foreach $line (@lines) {
    chomp ($line);
    debug ("line = $line");

    # Maybe wipe out part of the line
    if ( defined ($wipe) && $wipe ne "" ) {
      $line =~ s/$wipe//g;
    }
    debug ("after wipe: $line");

    # Split the line
    debug ("splitting on $sep.");
    @data = split (/$sep/, $line);

    # Get the requested fields
    foreach $field (@fields) {
      debug ("getting field $field which is $data[$field]");
      push (@return, $data[$field]);
    }
  }
  return (@return);
}

sub write2file {
  my ($file, $text) = @_;
  local *F;

  open (F, ">$file") || die "Can't open $file: $!";
  print F "$text\n";
  close (F);
}

sub read1line {
  my ($file) = @_;
  my ($line);
  local *F;

  if ( ! -f $file ) {
    return (0);
  }
  open (F, "$file") || die "Can't open $file: $!";
  $line = <F>;
  close (F);
  chomp ($line);
  return ($line);
}

# If line starts with whitespace, assume it's a stupid wrap-around like this:
# fubar% bdf /SD
# Filesystem          kbytes    used   avail %used Mounted on
# /dev/vgDepots/lvol1
#              56623104 45002248 11557016   80% /SD
# That is, join this line to the previous line
sub unwrap {
  my (@data) = @_;
  my ($line, $stack, @return);

  foreach $line (@data) {
    chomp ($line);
    # Store first line in $stack in case there is a wrap on the next line,
    if ( ! defined ($stack) ) {
      debug ("first line: $line");
      $stack = $line;
    } else {
      # If this line starts with whitespace, join it to the stack
      if ( $line =~ /^\s+/ ) {
        debug ("joining: $line");
        $stack = "$stack $line";
      } else {
        # If this line doesn't start with whitespace, push the stack into the array
        # to be returned, then set $stack to this line.
        debug ("pushing: $stack");
        push (@return, $stack);
        $stack = $line;
      }
    }
  }
  # Push the last line
  push (@return, $stack);

  return (@return);
}

sub renamefs {
  my ($fs) = @_;

  $fs =~ s/\//_/g;
  $fs =~ s/^_//g;
  if ( $fs eq "" ) {
    $fs = "slash";
  }
  return ($fs);
}

# Return a string showing the stack of callers.  Useful in log messages and for
# debugging.
sub callstack {
  my ($count, $file, $sub, $line, $stack);
  $count = 1;
  $stack = "";

  ($file, $line, $sub) = (caller($count))[1, 2, 3];
  while ( defined ($file) && defined ($sub) ) {
    if ( $stack eq "" ) {
      $stack = "$file:$line:$sub";
    } else {
      $stack = "$file:$line:$sub ->  $stack";
    }
    $count++;
    ($file, $line, $sub) = (caller($count))[1, 2, 3];
  }
  return ($stack);
}

# Run a sub with a timeout!
sub run_sub {
  my ($subname, $subref, $timeout, @args) = @_;
  my ($status, @data);

  if ( ! defined (&$subref) ) {
    return (-1, ("Your subref for $subname ($subref) points nowhere useful\n"));
  }

  # Wrap the sub in alarm in case it never returns
  eval {
    local $SIG{ALRM} = sub { die "alarm\n" };
    alarm $timeout;

    @data = &$subref (@args);

    alarm 0;
  };

  # If alarm was triggered, add a message to @data and return -1
  if ($@) {
      # If we're here because the eval'd code got a signal *other* than ALRM,
      # something really weird is going on!
      if ( $@ ne "alarm\n" ) {
	  push (@data, "Really weird non-alarm ($@) death in run_sub for $subname\n");
      }
      # Otherwise it's a normal timeout
      else {
	  push (@data, "Timeout for subref $subname");
      }
      return (-1, @data);
  }
  return (0, @data);
}

sub config_value {
  my ($line) = @_;

  return ((split (/\s+/, $line))[1]);
}

# TODO:  Yup, it doesn't do ports.
sub parse_server_url {
  my ($url) = @_;
  my ($protocol, $fqdn, $uri);

  $protocol = $url;
  $protocol =~ s|(^[a-z1-9]+)://.*|$1|;
  $fqdn = $url;
  $fqdn =~ s|${protocol}://||;
  $fqdn =~ s|^([^/]+).*|$1|;
  $uri = $url;
  $uri =~ s|^${protocol}://${fqdn}||;
  return ($protocol, $fqdn, $uri);
}

our ($g_server_fqdn, $g_server_protocol, $g_server_uri, $g_max_log_entries,
     $g_max_rrd_entries, $g_server_logdir, $g_deadlog_dir,
     $g_client_config, $g_server_config, $g_rrddir);

# econfig = eval_config
our (%econfig);
sub get_eval_config {
  my ($file) = @_;
  my (@config);
  local *F;

  if ( ! open (F, $file) ) {
    fileit ("Can't open $file: $!\n", "err");
    exit (33);
  }
  @config = <F>;
  eval "@config";
  if ( $@ ) {
    fileit ("Eval error:  $@\n", "err");
    exit 3;
  }
  if ( $g_debug ) {
    dumphash (%econfig);
  }
}

sub get_config {
  my ($role) = @_;
  my ($line, $file);
  local *F;

  if ( $role eq "client" ) {
    $file = $g_client_config;
  }
  if ( $role eq "server" ) {
    $file = $g_server_config;
  }

  if ( open (F, $file) ) {
    while ( <F> ) {
      $line = $_;
      chomp ($line);
      if ( $line !~ /^#/ ) {
	if ( $line =~ /^\s*store_url / ) {
	  ($g_server_protocol, $g_server_fqdn, $g_server_uri) = parse_server_url(config_value($line));
	}
	if ( $line =~ /^\s*rrddir / ) {
	  $g_rrddir = config_value($line);
	}
	if ( $line =~ /^\s*max_log_entries / ) {
	  $g_max_log_entries = config_value($line);
	}
	if ( $line =~ /^\s*max_rrd_entries / ) {
	  $g_max_rrd_entries = config_value($line);
	}
	if ( $line =~ /^\s*inbound_dir /) {
	  $g_server_logdir = config_value($line);
	}
	if ( $line =~ /^\s*rolled_dir /) {
	  $g_deadlog_dir = config_value($line);
	}
	if ( $line =~ /^\s*stuffer_track /) {
	  $g_host4host_file = config_value($line);
	}
      }
    }
    close (F);
  }
  if ( $role eq "client" && !defined ($g_server_fqdn) ) {
    fileit ("store_url is undefined.  Death!", "err");
    exit (38);
  }
  if ( $role eq "server" &&
       ( (!defined ($g_server_logdir) || "$g_server_logdir" eq "") ||
	 (!defined ($g_deadlog_dir) || "$g_deadlog_dir" eq "") ||
	 (!defined ($g_rrddir) || "$g_rrddir" eq "" ))
       ) {
    fileit ("inbound_dir and/or rolled_dir and/or rrddir is/are undefined.  Death!", "err");
    exit (39);
  }
}

sub url_encode {
  my ($s) = @_;

  $s =~ s/([^A-Za-z0-9])/sprintf("%%%02X", ord($1))/seg;
  return ($s);
}

sub url_decode {
  my ($s) = @_;

  $s =~ s/%([0-9a-f][0-9a-f])/chr (hex ($1))/sieg;
  return ($s);
}

our ($g_lib, $g_lock_dir,$g_incoming,$g_outgoing,$g_libexec, $g_host, $g_collector);
sub bivalve {
  my (@data) = @_;

  my (%goldie_locks, $outfile, $lockfile);
  my $line;
  my $start = time ();
  my $lock;
  $lockfile = "$g_lock_dir/outputfilelock";
  if ( open ($goldie_locks{'outputfilelock'}, ">>$lockfile") ) {
    # write the I/O lock
    while ( ($lock = lock_fh ($goldie_locks{'outputfilelock'})) != 1 && time () - $start < 30 ) {
      sleep(2);
    }
    if ( $lock == 1 ) {
      $outfile = "$g_incoming/${start}.${g_host}.${g_collector}";
      if ( open (F, ">$outfile") ) {
	if ( defined ($data[0]) ) {
	  print F @data;
	} else {
	  while ( <STDIN> ) {
	    $line = $_;
	    print F "$line";
	  }
	}
	close (F);
      } else {
	fileit ("Failed to open $outfile: $!  Holy crap!", "err");
	exit (908);
      }
    } else {
      fileit ("Can't open $lockfile: $! after 30 seconds. Bailing.");
    }
    # delete the I/O lock
    unlock_fh ($goldie_locks{'outputfilelock'});
  } else {
    fileit ("Can't open $lockfile: $!  Bailing!");
  }
}

# List directory entries of $type (dir|file)
# Sort oldest to newest if $sort == 1.
# Recurse if $recuse == 1
sub list_dir_entries {
  my ($dir, $type, $sort, $recurse) = @_;
  local *F;
  my ($entry, $entry_type, @return);

  if ( !opendir (F, $dir) ) {
    fileit ("Can't open $dir: $!");
    return (());
  } else {
    WHILE: while ( ($entry = readdir (F)) ) {
      if ( $entry !~ /^[.]/ && $entry !~ /^lost[+]found$/ ) {
	($entry_type) = (stat ("$dir/$entry"))[2];
	if ( defined ($recurse) && $recurse == 1 && $entry ne "lost+found") {
	  if ( S_ISDIR($entry_type) ) {
	    push (@return, list_dir_entries ("$dir/$entry", $type, $sort, $recurse));
	    next WHILE;
	  }
	}
	if ( defined ($type) ) {
	  if ( ($type eq "file" && S_ISREG($entry_type) && !S_ISLNK($entry_type)) ||
	       ($type eq "dir" && S_ISDIR($entry_type)) ) {
	    push (@return, "$dir/$entry");
	  }
	} else {
	  push (@return, "$dir/$entry");
	}
      }
    }
    closedir (F);
    if ( defined ($sort) && $sort == 1 ) {
      @return = sort (@return);
    }
    return (@return);
  }
}

sub basename {
  my ($path) = @_;

  $path =~ s/.*\///;
  return ($path);
}

sub dirname {
  my ($path) = @_;

  $path =~ s/\/[^\/]*$//;
  return ($path);
}

our ($g_ignore_file, %g_ignores);
sub get_ignores {
  my ($line, $collector, $item);

  if ( ! open (F, $g_ignore_file) ) {
    fileit ("Can't open $g_ignore_file: $!", "err");
    return (());
  }
  while ( <F> ) {
    $line = $_;
    chomp ($line);
    debug ("Considering $line for ignore", 200);
    if ( $line !~ /^#/ &&
         $line !~ /^$/ ) {
      ($collector, $item) = split (/:/, $line, 2);
      debug ("adding $item to ignore list for $collector", 100);
      push (@{ $g_ignores{$collector} }, $item);
    }
  }
  close (F);
}

sub ignoreit {
  my ($collector, $item) = @_;
  my ($ignore);

  foreach $ignore (@{ $g_ignores{$collector} }) {
    if ( $item =~ /^$ignore/ ) {
      return (1);
    }
  }
  return (0);
}

sub tell_nagios {
  my ($host, $status, $msg) = @_;

  print "$host - $msg\n";
  exit ($status);
}

sub dumphash {
  my (%hash) = @_;
  print Data::Dumper->Dump ( [\%hash], ['*hash'] );
}

our ($opt_l, $opt_d);
sub default_plugin_opts {
  getopts('dl:');
  # Sets $opt_* as a side effect.

  if ( defined ($opt_d) ) {
    $g_debug = 1;
    debug ("Debug is ON!");
  }
  if ( defined ($opt_l) && $opt_l =~ /^\d+$/ ) {
    $g_debug_level = $opt_l;
    debug ("Debug level set to $g_debug_level");
  }
}

sub read_host4host {
  if ( ! open (F, $g_host4host_file) ) {
    fileit ("Can't open $g_host4host_file: $!");
  } else {
    eval <F>;
    close (F);
  }
}

sub save_host4host {
  if ( ! open (F, ">${g_host4host_file}") ) {
    fileit ("Can't open $g_host4host_file: $!");
  } else {
    print F Data::Dumper->Dump ( [\%host4host], ['*host4host'] );
    close (F);
  }
}

1;
