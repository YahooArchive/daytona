#!/usr/bin/perl -w

use strict;
use warnings;

use Getopt::Long;
use POSIX qw/setsid strftime/;
use Cwd qw/chdir/;
use Fcntl qw/:flock/;
use File::Path;
use File::Spec;
use IO::Handle;
use Time::Local;

my $help = 0;
my $sar_gather_bin = "sar";
my $interval = 5;
my $root_dir = "/tmp/";
my $pid_group_file = "/tmp/perl_pid_group.pid";
my $output_file = "/tmp/output.dat";
my $output_dir = "/tmp/";
my $debug_file = "/tmp/sar_gather_agent_debug.out";
my $sar_data_file = "/tmp/sar.dat";
my $iostat_data_file = "/tmp/iostat.dat";
my $top_data_file = "/tmp/top.dat";
my $top_output_file = "/tmp/top_output.csv";
my $strace_output_file = "/tmp/strace_output.txt";

my $daemonize = 0;
my $shutdown = 0;

my $iostat_bin = "iostat";
my $top_bin = "top";
my $strace_bin = "strace";

my $debug_fh;
my $top_output_fh;

my $strace = 0;
my $strace_delay = 0;
my $strace_duration = 1;
my $strace_proc;
my $strace_pid = 0;

my %stats = (
    cpu_utilization => {
        headings    => [ "CPU", "%user", "%nice", "%system", "%iowait", "%steal", "%idle" ],
        output_file => {all=>"cpu.plt"}
    },
    interrupts => {
        headings    => [ "INTR", "intr/s" ],
    },
    task_creation => {
        headings    => [ "proc/s", "cswch/s" ],
        output_file => {ALL=>"task.plt"}
    },
    swapping => {
        headings    => [ "pswpin/s", "pswpout/s" ],
    },
    paging => {
        headings    => [ "pgpgin/s", "pgpgout/s", "fault/s", "majflt/s",
            "pgfree/s", "pgscank/s", "pgscand/s", "pgsteal/s",
            "%vmeff" ],
    },
    IO => {
        headings    => [ "tps", "rtps", "wtps", "bread/s", "bwrtn/s" ],
    },
    memory_paging => {
        headings    => [ "frmpg/s", "bufpg/s", "campg/s" ],
    },
    memory_utilization => {
        headings    => [ "kbmemfree", "kbmemused", "%memused", "kbbuffers",
            "kbcached", "kbcommit", "%commit", "kbactive", "kbinact", "kbdirty" ],
        output_file => {ALL=>"mem.plt"}
    },
    swap_space => {
        headings    => [ "kbswpfree", "kbswpused", "%swpused", "kbswpcad",
            "%swpcad" ],
    },
    kernel_tables => {
        headings    => [ "dentunusd", "file-nr", "inode-nr", "pty-nr" ],
    },
    queues => {
        headings    => [ "runq-sz", "plist-sz", "ldavg-1", "ldavg-5",
            "ldavg-15" ],
    },
    tty => {
        headings    => [ "TTY", "rcvin/s", "xmtin/s", "framerr/s", "prtyerr/s",
            "brk/s", "ovrun/s" ],

    },
    block_devices => {
        headings    => [ "DEV", "tps", "rd_sec/s", "wr_sec/s", "avgrq-sz", "avgqu-sz", "await", "svctm", "%util"],
    },
    iostat_block_devices => {
        headings    => ["rrqm/s", "wrqm/s", "r/s", "w/s", "rkB/s", "wkB/s", "avgrq-sz", "avgqu-sz", "await", "r_await", "w_await", "svctm", "%util"],
        output_file => {suffix=>"_iostat_block_devices.plt"}
    },
    DEV => {
        headings    => [ "IFACE", "rxpck/s", "txpck/s", "rxkB/s", "txkB/s",
            "rxcmp/s", "txcmp/s", "rxmcst/s", "%ifutil" ],
        output_file => {suffix=>"_network_devices.plt"}
    },
    EDEV => {
        headings    => [ "IFACE", "rxerr/s", "txerr/s", "coll/s", "rxdrop/s",
            "txdrop/s", "txcarr/s", "rxfram/s", "rxfifo/s",
            "txfifo/s" ],
    },
    NFS => {
        headings    => [ "call/s", "retrans/s", "read/s", "write/s", "access/s",
            "getatt/s" ],
        output_file => {ALL=>"nfs.plt"}
    },
    NFSD => {
        headings    => [ "scall/s", "badcall/s", "packet/s", "udp/s", "tcp/s",
            "hit/s", "miss/s", "sread/s", "swrite/s", "saccess/s",
            "sgetatt/s" ],
    },
    SOCK => {
        headings    => [ "totsck", "tcpsck", "udpsck", "rawsck", "ip-frag",
            "tcp-tw" ],
    },
    IP => {
        headings    => [ "irec/s", "fwddgm/s", "idel/s", "orq/s", "asmrq/s",
            "asmok/s", "fragok/s", "fragcrt/s" ],
    },
    EIP => {
        headings    => [ "ihdrerr/s", "iadrerr/s", "iukwnpr/s", "idisc/s",
            "odisc/s", "onort/s", "asmf/s", "fragf/s" ],
    },
    ICMP => {
        headings    => [ "imsg/s", "omsg/s", "iech/s", "iechr/s", "oech/s",
            "oechr/s", "itm/s", "itmr/s", "otm/s", "otmr/s",
            "iadrmk/s", "iadrmkr/s", "oadrmk/s", "oadrmkr/s" ],
    },
    EICMP => {
        headings    => [ "ierr/s", "oerr/s", "idstunr/s", "odstunr/s",
            "itmex/s", "otmex/s", "iparmpb/s", "oparmpb/s",
            "isrcq/s", "osrcq/s", "iredir/s", "oredir/s" ],
    },
    TCP => {
        headings    => [ "active/s", "passive/s", "iseg/s", "oseg/s" ],
    },
    ETCP => {
        headings    => [ "atmptf/s", "estres/s", "retrans/s", "isegerr/s",
            "orsts/s" ],
    },
    UDP => {
        headings    => [ "idgm/s", "odgm/s", "noport/s", "idgmerr/s" ],
    },
    SOCK6 => {
        headings    => [ "tcp6sck", "udp6sck", "raw6sck", "ip6-frag" ],
    },
    IP6 => {
        headings    => [ "irec6/s", "fwddgm6/s", "idel6/s", "orq6/s",
            "asmrq6/s", "asmok6/s", "imcpck6/s", "omcpck6/s",
            "fragok6/s", "fragcr6/s" ],
    },
    EIP6 => {
        headings    => [ "ihdrer6/s", "iadrer6/s", "iukwnp6/s", "i2big6/s",
            "idisc6/s", "odisc6/s", "inort6/s", "onort6/s",
            "asmf6/s", "fragf6/s", "itrpck6/s" ],
    },
    ICMP6 => {
        headings    => [ "imsg6/s", "omsg6/s", "iech6/s", "iechr6/s",
            "oechr6/s", "igmbq6/s", "igmbr6/s", "ogmbr6/s",
            "igmbrd6/s", "ogmbrd6/s", "irtsol6/s", "ortsol6/s",
            "irtad6/s", "inbsol6/s", "onbsol6/s", "inbad6/s",
            "onbad6/s" ],
    },
    EICMP6 => {
        headings    => [ "ierr6/s", "idtunr6/s", "odtunr6/s", "itmex6/s",
            "otmex6/s", "iprmpb6/s", "oprmpb6/s", "iredir6/s",
            "oredir6/s", "ipck2b6/s", "opck2b6/s" ],
    },
    UDP6 => {
        headings    => [ "idgm6/s", "odgm6/s", "noport6/s", "idgmer6/s" ],
    },
    power => {
        headings    => [ "CPU", "MHz" ],
    },
);

my @stats_keys = (
    "cpu_utilization", "interrupts", "task_creation", "swapping", "paging",
    "IO", "memory_paging", "memory_utilization", "swap_space", "kernel_tables",
    "queues", "tty", "block_devices", "iostat_block_devices", "DEV", "EDEV", "NFS", "NFSD", "SOCK",
    "IP", "EIP", "ICMP", "EICMP", "TCP", "ETCP", "UDP", "SOCK6", "IP6", "EIP6",
    "ICMP6", "EICMP6", "UDP6", "power"
);
our $pid_group;
GetOptions(
    'sar-gather=s' => \$sar_gather_bin,
    'interval=s'   => \$interval,
    'root-dir=s'   => \$root_dir,
    'output=s'     => \$output_file,
    'output-dir=s' => \$output_dir,
    'debug-file=s' => \$debug_file,
    'daemonize!'   => \$daemonize,
    'shutdown!'   => \$shutdown,
    'strace!'     => \$strace,
    'strace-delay=s' => \$strace_delay,
    'strace-duration=s' => \$strace_duration,
    'strace-proc=s' => \$strace_proc
) or die "Oops";

$SIG{'TERM'} = sub {
    print STDERR "Caught SIGTERM; shutting down\n";
    my $cnt = kill 'HUP', -$pid_group;
    cleanup();
    exit(1);
};

$SIG{'INT'} = sub {
    print STDERR "Caught SIGINT; shutting down\n";
    my $cnt = kill 'HUP', -$pid_group;
    cleanup();
    exit(0);
};

$SIG{'HUP'} = sub {
    print STDERR "Caught SIGHUP; shutting down\n";
    my $cnt = kill 'HUP', -$pid_group;
    cleanup();
    exit(0);
};

# Where we keep all the individual file handles
our %file_handles;

MAIN: {
    $pid_group_file = $root_dir . "/perl_pid_group.pid";
    $output_file = $root_dir . "/output.dat";
    $output_dir = $root_dir . "/";
    $debug_file = $root_dir . "/sar_gather_agent_debug.out";
    $top_output_file = $root_dir . "/top_output.csv";
    $sar_data_file = $root_dir . "/sar.dat";
    $iostat_data_file = $root_dir . "/iostat.dat";
    $top_data_file = $root_dir . "/top.dat";
    $strace_output_file = $root_dir . "strace_output.txt";

    if($shutdown) {
        my $pgid = 0;

        if ( -e $pid_group_file ) {
            open( my $in_fh, '<', $pid_group_file )
                or die "Couldn't open '$pid_group_file' for reading: $!; stopped";
            my $pid = <$in_fh>;
            close $in_fh;
            chomp $pid;
            print "2. killing $pid \n";
            $pid =~ m/^\d+$/
                or die "Pid '$pid' doesn't look like a number; stopped";
            $pgid = $pid;
        }

        if ($pgid != 0){
            my $cnt = kill 'TERM', -$pgid;
            cleanup();
        }
        #cleanup();
        exit(0);
    }

    if ($daemonize) {
        init_daemon();
    }

    prepare_exec();
    $pid_group = getpgrp();
    write_pid_group_file();
    for(my $i=0;$i<3;$i=$i+1){
        my $temp_pid;
        $temp_pid = fork();
        die "fork() failed: $!" unless defined $temp_pid;
        if ($temp_pid) {
            if ($i == 0){
                run_sar_daemon();
            }elsif ($i == 1){
                if ($strace){
                    run_strace_daemon();
                }else{
                    while (1){}
                }
            }else{
                run_top_daemon();
            }
            last;
        }
        else {
            if ($i != 2){
                next;
            }else{
                run_iostat_daemon();
            }
        }
    }
    finish_exec();
}


# Copied from http://www.perlmonks.org/?node_id=374409
sub init_daemon {
    # First, we fork and exit the parent. That makes the launching process
    # think we're done and also makes sure we're not a process group leader - a
    # necessary condition for the next step to succeed.
    #    my $pid = fork;
    #    exit 0 if $pid;
    #    exit 1 if not defined $pid;

    # Second, we call setsid() which does three things. We become leader of a
    # new session, group leader of a new process group, and become detached
    # from any terminal. To satisfy SVR4, we do the fork dance again to shuck
    # session leadership, which guarantees we never get a controlling terminal.
    #    setsid();
    #    $pid = fork;
    #    exit 0 if $pid;
    #    exit 1 if not defined $pid;

    # Third, we change directory to the root of all. That is a courtesy to the
    # system, which without it would be prevented from unmounting the
    # filesystem we started in.
    #    chdir '/' or die $!;

    # Fourth, we clear the permissions mask for file creation. That frees our
    # daemon to manage its files as it sees fit.
    #    umask 0;

    # Finally, we close unwanted filehandles we have inherited from the parent
    # process. That is another courtesy to the system, as well as being sparing
    # of our own process resources. I'll take them from arguments passed to
    # init_daemon()
    #    close $_ for @_;

    fork and exit;
    POSIX::setsid();
    fork and exit;
    umask 0;
    chdir '/';
    close $_ for @_;
    close STDIN;
    close STDOUT;
    close STDERR;
    open STDIN, '>', '/dev/null';
    open STDOUT, '>', '/dev/null';
    open STDERR, '>', '/dev/null';
}


sub run_iostat_daemon {
    print $debug_fh "In run_iostat_daemon()\n";
    print $debug_fh "iostat collection starting.....\n";
    exec_iostat();
}

sub run_top_daemon {
    print $debug_fh "In run_top_daemon()\n";
    print $debug_fh "top collection starting.....\n";
    exec_top();
}

sub run_sar_daemon {
    #-x $sar_gather_bin or die "Binary '$sar_gather_bin' not found!\n";
    print $debug_fh "In run_sar_daemon()\n";
    print $debug_fh "sar collection starting.....\n";
    exec_sar();
}

sub run_strace_daemon {
    print $debug_fh "In run_strace_daemon()\n";
    print $debug_fh "Setting up Strace for process : $strace_proc\n";
    my $cmd = "ps -eo pid,cmd,%cpu --sort=-%cpu | grep " . $strace_proc .  " | grep -v grep | awk 'FNR == 1 {print \$1}'";
    $strace_pid = `$cmd`;
    $strace_pid =~ s/^\s+|\s+$//g;
    if ($strace_pid == 0 ){
        open(my $fh, '>', $strace_output_file) or die "Could not open file '$strace_output_file' $!";
        print $fh "No active PID found for given process : $strace_proc\n";
        close $fh;
        return;
    }
    print $debug_fh "PID selected for process $strace_proc : $strace_pid\n";
    exec_strace();
}



# Delete all /tmp files created
sub cleanup {
    unlink($pid_group_file) or warn "Unable to unlink '$pid_group_file': $!";
    unlink($sar_data_file) or warn "Unable to unlink '$sar_data_file': $!";
    unlink($iostat_data_file) or warn "Unable to unlink '$iostat_data_file': $!";
    unlink($top_data_file) or warn "Unable to unlink '$top_data_file': $!";
    unlink($debug_file) or warn "Unable to unlink '$debug_file': $!";
}


# We could've exec'd the sar process like this:
#   exec "sar -Ap -o $sar_data_file $interval | flatten_sar.pl >> $output_file";
# That would've exec'd a shell which would launch sar and flatten_sar.pl.
# But integrating flatten_sar.pl into this script saves one file and executable.
sub prepare_exec {
    if (-e $debug_file) {
        unlink($debug_file) or warn "Could not delete $debug_file: $1";
    }
    open( $debug_fh, '>>', $debug_file ) or warn "Couldn't open debug file";
    $debug_fh->autoflush(1);

    #select ($debug_fh);
    print $debug_fh "In prepare_exec()\n";

    if (! -d $output_dir) {
        mkpath($output_dir) or die "Could not make directory: $output_dir: $!";
    }

    print $debug_fh "exec_sar() Cleaning up old sar files\n";

    # Cleanup any old sar files
    my $filename = "";
    for my $stat (keys %stats) {
        foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
            print $debug_fh "$lab:$stats{$stat}{'output_file'}{$lab}\n";
            $filename = $stats{$stat}{'output_file'}{$lab};

            if (!$filename eq "") {
                my $output_file = File::Spec->catfile( $output_dir, $filename );
                print $debug_fh "Trying to unlink: $output_file\n";
                unlink($output_file)
                    or warn "Unable to delete '$output_file': $!";
            }
        }
    }

    print $debug_fh "exec_sar() Creating filehandles\n";

    $filename = "";
    # Our new file handles
    for my $stat (keys %stats) {
        foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
            print $debug_fh "$lab:$stats{$stat}{'output_file'}{$lab}\n";
            if(!($lab eq "suffix")) {
                $filename = $stats{$stat}{'output_file'}{$lab};

                if (!$filename eq "") {
                    my $output_file = File::Spec->catfile( $output_dir, $filename );
                    open( my $out_fh, '>>', $output_file )
                        or die "Couldn't open '$output_file': $!; stopped";
                    $out_fh->autoflush(1);
                    flock( $out_fh, LOCK_EX );
                    $file_handles{$stat}{$lab} = $out_fh;
                }
            }
        }
    }

    $ENV{'LC_TIME'} = 'POSIX';
    $ENV{'S_TIME_FORMAT'} = 'ISO';
    $ENV{PATH} = "$ENV{PATH}:/tmp/daytona_sarmonitor/bin";
    print $debug_fh "Exiting prepare_exec()\n";
}

sub exec_sar {
    print $debug_fh "exec_sar() Executing sar binary: $sar_gather_bin -n NFS -n DEV -u -r -w -o $sar_data_file $interval\n";

    open( my $sar_pipe, '-|', $sar_gather_bin, '-n', 'NFS', '-n', 'DEV',  '-u', '-r', '-w', '-o', $sar_data_file, $interval )
        or die "Couldn't execute '$sar_gather_bin': $!.\nStopped";

    print $debug_fh "exec_sar() calling flatten()\n";

    flatten($sar_pipe);
}

sub exec_iostat {
    print $debug_fh "exec_iostat() Executing iostat binary: $iostat_bin -dtx $interval \n";

    open( my $sar_pipe2, '-|', $iostat_bin, '-dtx', $interval )
        or die "Couldn't execute '$iostat_bin': $!.\nStopped";

    print $debug_fh "exec_iostat() calling flatten_iostat()\n";

    flatten_iostat($sar_pipe2);
}

sub exec_top {
    print $debug_fh "exec_top() Executing top binary: $top_bin -b -i -o +%CPU -d $interval\n";

    open( my $sar_pipe3, '-|', $top_bin, '-b', '-i' , '-o', '+%CPU', '-d', $interval )
        or die "Couldn't execute '$top_bin': $!.\nStopped";

    print $debug_fh "exec_top() calling flatten_top() \n";

    flatten_top($sar_pipe3);
}

sub exec_strace {
    print $debug_fh "Starting strace monitor in $strace_delay secs \n";
    sleep($strace_delay);
    my $strace_cmd = 'timeout ' . $strace_duration . ' ' . $strace_bin . ' -p ' . $strace_pid. ' -c -S time -o ' . $root_dir . '/strace_output.txt';
    print $debug_fh "exec_strace() Executing strace binary: " . $strace_cmd . "\n";
    `$strace_cmd`;
    my $cmd = 'perl -pi -e \'print  "Strace Process : '. $strace_proc . ' | PID : ' . $strace_pid . ' \n\n" if $. == 1\' ' . $root_dir . '/strace_output.txt';
    print $debug_fh "$cmd\n";
    `$cmd`;
}

sub finish_exec {
    #print $debug_fh "$child_pid , $child_top_pid , $parent_pid";
    print $debug_fh "exec_r() calling flock\n";
    for my $stat (keys %file_handles) {
        print $debug_fh "--------$stat\n";
        my $fh = $file_handles{$stat};
        flock( $fh, LOCK_UN );
        close $fh;
    }

    #print $debug_fh "exec_() closing pipe\n";
    #close $sar_pipe;
    #close $sar_pipe2;
}

sub write_pid_group_file {
    if ( -e $pid_group_file ) {
        open( my $in_fh, '<', $pid_group_file )
            or die "Couldn't open '$pid_group_file' for reading: $!; stopped";

        my $pid = <$in_fh>;
        close $in_fh;
        chomp $pid;
        $pid =~ m/^\d+$/
            or die "Pid '$pid' doesn't look like a number; stopped";
        if ( kill 0, $pid ) {
            die "Looks like sar_gather_agent.pl is already running with pid '$pid'. Delete '$pid_group_file' if this is not true.\n";
        }
    }

    open( my $out_fh, '>', $pid_group_file )
        or die "Couldn't open '$pid_group_file' for writing: $!; stopped";

    print {$out_fh} $pid_group, "\n";

    close $out_fh;
}

sub get_data_iostat {
    my ($fh, $stat, $headers, $headerstr, $ts) = @_;
    my $data_string;
    my $line = $_;
    my %out_fh;
    my @out_devs;
    my $scan_label;
    my $write = 0;

    #print STDOUT "Processing line: $line";

    $scan_label = "";

    my @data = split /\s+/, $line;
    return unless ( @data >  3 );

    $scan_label = shift @data;

    #print $debug_fh "Scanned Label : $scan_label\n";

    $data_string = "$ts," if (! $data_string);
    $data_string .= join(",", @data);

    #check if scan label is in label and set write = true
    #else write = false, expect following line to be continuation
    if(length $scan_label > 0){
        #print $debug_fh "set write 1 : $scan_label\n";
        $write = 1;
    }

    if($scan_label eq '') {
        $scan_label = 'ALL';
    }

    my $write_fh = "";
    my $flabel = "";

    foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
        if($lab eq $scan_label) {
            $write_fh = $file_handles{$stat}{$lab};
            #print $debug_fh "Got fh for $stat:$lab, $write_fh = $file_handles{$stat}{$lab}\n";
            $flabel = $lab;
            last;
        }
    }

    my $fname = "";
    if(!$write_fh and length $scan_label > 0 and !($scan_label eq "ALL")) {
        $write_fh = $file_handles{$stat}{$scan_label};
        if(!$write_fh) {
            $fname = $stats{$stat}{'output_file'}{"suffix"};
            if(!$fname eq "") {
                $fname = $scan_label.$fname;
                my $output_file = File::Spec->catfile( $output_dir, $fname );
                unlink($output_file)
                    or print $debug_fh "Unable to delete '$fname': $!";
                open( my $o_fh, '>>', $output_file )
                    or print $debug_fh  "Couldn't open '$output_file': $!; stopped";
                $o_fh->autoflush(1);
                flock( $o_fh, LOCK_EX );
                $file_handles{$stat}{$scan_label} = $o_fh;
                $write_fh = $o_fh;
            }
        }
    }

    $flabel = $scan_label;
    my $header_str = $headerstr->{$stat}->{'header_string'};
    if($write) {
        # Empty line, so we're done with this group of sar data
        if ($write_fh) {
            if (! $headers->{$stat}->{$flabel}->{'printed'} and !($header_str eq "")) {
                print $write_fh $headerstr->{$stat}->{'header_string'}."\n";
                $headers->{$stat}->{$flabel}->{'printed'} = 1;
            }
            #print $debug_fh "$write_fh -> printing data \n";
            print $write_fh "$data_string\n";
            $data_string = "";
        } else {
            #warn "ERROR: fh empty for : $scan_label\n";
        }
    }
}

################################################################################
# Sar output flattening routines
################################################################################

sub get_data {
    my ($fh, $stat, $headers, $headerstr) = @_;
    my $data_string;
    my %out_fh;
    my @out_devs;

    #foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
    #$out_fh{$lab} = $file_handles{$stat}{$lab};
    #push @out_devs, $lab;
    #print $debug_fh "\nExisting Labels for stat $stat:$lab, $out_fh{$lab} = $file_handles{$stat}{$lab}\n";
    #}

    #print STDOUT "Processing data for: $stat\n";
    while (<$fh>) {
        my $line = $_;
        my $write = 0;
        my $no_more = 0;
        my $scan_label = "";
        my $write_fh = "";
        my $flabel = "";


        # Is there a timestamp on this line?  (There should better be.)
        if (($line =~ m/^\d{2}:\d{2}:\d{2}\s+/ and $write eq 0)) {
            #print STDOUT "Processing line: $line";
            $scan_label = "";

            # If yes, then we want to process this line
            my @data = split /\s+/, $line;
            my $timestamp;
            $timestamp = to_ISO8601(shift @data);

            $data_string = "$timestamp," if (! $data_string);

            if ($stat eq "cpu_utilization" ||
                $stat eq "interrupts" ||
                $stat eq "power" ||
                $stat eq "DEV" ||
                $stat eq "EDEV" ||
                $stat eq "block_devices") {
                $scan_label = shift @data;
            }
            #print  STDOUT "Scanned Label : $scan_label\n";

            if($scan_label eq '') {
                $scan_label = 'ALL';
            }

            foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
                if($lab eq $scan_label) {
                    $write_fh = $file_handles{$stat}{$lab};
                    #print $debug_fh "Got fh for $stat:$lab, $write_fh = $file_handles{$stat}{$lab}\n";
                    $flabel = $lab;
                    last;
                }
            }

            my $fname = "";
            if(!$write_fh and length $scan_label > 0 and !($scan_label eq "ALL")) {
                $write_fh = $file_handles{$stat}{$scan_label};
                if(!$write_fh) {
                    $fname = $stats{$stat}{'output_file'}{"suffix"};
                    if(!$fname eq "") {
                        $fname = $scan_label.$fname;
                        my $output_file = File::Spec->catfile( $output_dir, $fname );
                        unlink($output_file)
                            or print $debug_fh "Unable to delete '$fname': $!";
                        open( my $o_fh, '>>', $output_file )
                            or print $debug_fh  "Couldn't open '$output_file': $!; stopped";
                        $o_fh->autoflush(1);
                        flock( $o_fh, LOCK_EX );
                        $file_handles{$stat}{$scan_label} = $o_fh;
                        $write_fh = $o_fh;
                    }
                }
            }
            $flabel = $scan_label;

            # Generate the header, if necessary
            if (! $headers->{$stat}->{$flabel}->{'printed'}) {
                $headerstr->{$stat}->{'header_string'} = "\"Time\"";
                foreach my $label (@{ $stats{$stat}{'headings'} }) {
                    if ($stat eq "cpu_utilization" ||
                        $stat eq "interrupts" ||
                        $stat eq "power" ||
                        $stat eq "DEV" ||
                        $stat eq "EDEV" ||
                        $stat eq "block_devices") {
                        if ($label eq "CPU" || $label eq "INTR" || $label eq "IFACE" || $label eq "DEV" || $label eq "Device:" ) {
                            # Certain lines are extra headers which we skip
                            next;
                        }
                        #print STDOUT "Adding header: $label _ $data[0]\n";
                        # Certain headers we attach a suffix
                        $headerstr->{$stat}->{'header_string'} .= ",\"$label\"";
                        $headerstr->{$stat}->{'header_string'} =~ s/%/_/g;
                        $headerstr->{$stat}->{'header_string'} =~ s/\/s/_sec/g;
                    } else {
                        #print STDOUT "Adding header: $label\n";
                        $headerstr->{$stat}->{'header_string'} .= ",\"$label\"";
                        $headerstr->{$stat}->{'header_string'} =~ s/%/_/g;
                        $headerstr->{$stat}->{'header_string'} =~ s/\/s/_sec/g;
                    }
                } # foreach my $label
            }

            $data_string .= join(",", @data);
            #check if scan label is in label and set write = true
            #else write = false, expect following line to be continuation
            if(length $scan_label > 0){
                #print $debug_fh "set write 1 : $scan_label\n";
                $write = 1;
            }
        }
        else {
            if($write == 0) {
                #warn "ERROR: TS not correct.\n";
                last;
            }
            $write = 1;
            $no_more = 1;
        }


        if($write) {
            # Empty line, so we're done with this group of sar data
            if ($write_fh) {
                if (! $headers->{$stat}->{$flabel}->{'printed'}) {
                    print $write_fh $headerstr->{$stat}->{'header_string'}."\n";
                    $headers->{$stat}->{$flabel}->{'printed'} = 1;
                    #print $debug_fh "setting header printed to 1 for $stat -> $flabel\n";
                }
                print $write_fh "$data_string\n";
                $data_string = "";
            } else {
                #warn "ERROR: fh empty for : $scan_label\n";
            }

            if($no_more){
                last;
            }
        }
    } # while (<ifh>)
}


sub to_ISO8601 {
    my ($timestamp) = @_;

    my $hour = 0;
    my $minute = 0;
    my $second = 0;

    if ($timestamp =~ m/^(\d{2}):(\d{2}):(\d{2})$/) {
        $hour = $1;
        $minute = $2;
        $second = $3;
    } else {
        warn "Warning: Weird timestamp from sar: '$timestamp'.\n";
    }

    my (undef, undef, undef, @date_points) = localtime();

    my $timelocal = timelocal($second, $minute, $hour, @date_points);

    return strftime('%Y-%m-%dT%H:%M:%SZ', gmtime($timelocal));
}

sub flatten_iostat {
    my ($ifh) = @_;

    select ($debug_fh);
    # Set autoflush after each print
    $| = 1;

    my $print_heading = 1;

    my %headers;
    my %headerstr;
    for my $stat (keys %stats) {
        foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
            $headerstr{$stat} = {
                header_string => "",
            };
            $headers{$stat}{$lab} = {
                printed => 0
            };
        }
    }

    print $debug_fh "Started iostat flatten.\n";
    #print $debug_fh join(",", @_);

    my $timestamp = "";
    while (<$ifh>) {
        my $line = $_;
        #print $debug_fh "->Input Line:$line";

        # Is there a timestamp on this line?
        if ($line =~ /^\d{4}-\d{2}-\d{2}T.*/) {
            #print $debug_fh "ioStat date line\n";
            # We got a TS, following lines are for this TS,
            # till we get another TS move ahead and getdata

            my @labels = split /\s+/, $line;

            # First column should be timestamp. Store the TS
            my ($date, $ts) = $line =~ /^(\d\d\d\d-\d?\d-\d?\d)T(\d?\d:\d?\d:\d?\d)/;
            $timestamp = to_ISO8601($ts);
            #print $debug_fh "ioStat, processing $line\n";
        }
        elsif ($line =~ /^Linux.*/) {
            print $debug_fh "Ignore Linux line\n";
        }
        elsif ($line =~ /^Device.*/) {
            my @data = split /\s+/, $line;
            my $headers =   \%headers;
            my $headerstr =   \%headerstr;
            # Generate the header
            my $stat = "iostat_block_devices";
            foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
                if (! $headers->{$stat}->{$lab}->{'printed'}) {
                    $headerstr->{$stat}->{'header_string'} = "\"Time\"";
                    foreach my $label (@{ $stats{$stat}{'headings'} }) {
                        if ( $stat eq "iostat_block_devices") {
                            $headerstr->{$stat}->{'header_string'} .= ",\"$label\"";
                            $headerstr->{$stat}->{'header_string'} =~ s/%/_/g;
                            $headerstr->{$stat}->{'header_string'} =~ s/\/s/_sec/g;
                        }
                    } # foreach my $label
                }
            }
        }
        elsif (length $line > 0) {
            get_data_iostat($ifh, "iostat_block_devices", \%headers, \%headerstr, $timestamp);
        } else {
            #warn "ERROR: No timestamp.\n";
        }
    } # while (<$ifh>)
    print $debug_fh "End flatten.\n";
}

sub flatten_top {
    my ($ifh) = @_;

    select ($debug_fh);
    # Set autoflush after each print
    $| = 1;

    if (-e $top_output_file) {
        unlink($top_output_file) or warn "Could not delete $top_output_file: $1";
    }

    open( $top_output_fh, '>>', $top_output_file ) or warn "Couldn't open top file";
    $top_output_fh->autoflush(1);

    print $debug_fh "Started top flatten.\n";
    #print $debug_fh join(",", @_);

    my $print_header = 1;
    my $timestamp = 0;
    my $printdata = 0;
    #print $top_output_fh "\n";
    while (<$ifh>) {
        my $line = $_;
        $line =~ s/^\s+|\s+$//g;
        $line =~ s/\s+/ /ig;
        $line=~s/ /,/g;
        if ($line =~ /^top.*/) {
            $printdata = 0;
        }
        if ($line =~ /^PID.*/){
            if ($print_header){
                print $top_output_fh "$line\n";
                my $timestring = strftime "%T", localtime;
                $timestamp = to_ISO8601($timestring);
                print $top_output_fh "$timestamp \n";
                $print_header = 0;
            }else{
                my $timestring = strftime "%T", localtime;
                $timestamp = to_ISO8601($timestring);
                print $top_output_fh "$timestamp \n";
            }
            $printdata = 1;
            next;
        }
        if ($printdata){
            print $top_output_fh "$line\n";
        }
    } # while (<$ifh>)
    close $top_output_fh;
    print $debug_fh "End top flatten.\n";
}


sub flatten {
    my ($ifh) = @_;

    select ($debug_fh);
    # Set autoflush after each print
    $| = 1;

    my $print_heading = 1;

    # We need to keep track of sar headers, making sure we generate it
    # and print out only once
    # {
    #   <stat>: {
    #     'header_string' => ""
    #     'printed'       => 0|1
    #   },
    #   <...>
    # }
    # NOTE: We're assuming things are always in the same order
    #foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
    #$out_fh{$lab} = $file_handles{$stat}{$lab};
    #push @out_devs, $lab;
    #print $debug_fh "\nExisting Labels for stat $stat:$lab, $out_fh{$lab} = $file_handles{$stat}{$lab}\n";
    #}
    #
    my %headers;
    my %headerstr;
    for my $stat (keys %stats) {
        foreach my $lab (keys %{$stats{$stat}{'output_file'}} ) {
            $headerstr{$stat} = {
                header_string => "",
            };
            $headers{$stat}{$lab} = {
                printed       => 0
            };
        }
    }

    print $debug_fh "Started sar flatten.\n";
    #print $debug_fh join(",", @_);

    while (<$ifh>) {
        my $line = $_;
        #print STDOUT "->Input Line : $line";

        # Is there a timestamp on this line?
        if ($line =~ m/^\d{2}:\d{2}:\d{2}\s+/) {
            # If yes, then we are (hopefully) at the start of a new set of
            # sample data from one of the stat categories

            my @labels = split /\s+/, $line;
            #print STDOUT "Labels: @labels\n";

            # First column should be timestamp
            my $timestamp = shift @labels;

            # Discover which config sample belongs to
            foreach my $stat (keys %stats) {
                if ($stats{$stat}{'headings'}->[0] eq $labels[0] && $stats{$stat}{'headings'}->[1] eq $labels[1]) {
                    #print $debug_fh "Calling get_data.\n";
                    get_data($ifh, $stat, \%headers, \%headerstr);
                }
            } # foreach my $stat (keys %stats)
        } else {
            #print $debug_fh "ERROR: No timestamp as expected.\n";
        }
    } # while (<$ifh>)
    print $debug_fh "End flatten.\n";
}


__END__

=head1 NAME

sar_gather_agent.pl - Run sar and flatten stdout output

=head1 SYNOPSIS

  sar_gather_agent.pl [options]

  Options:
     --help              Show help message
     --sar-gather=BIN    Point to the sar_gather program
     --interval=INT      Interval between gathers in seconds
     --pid-file=PIDFILE  Name of the file to write PID to
     --output-dir=DIR    Output file directories
     --daemonize         Daemonize sar_gather_agent.pl

=head1 OPTIONS

=over 8

=item B<--help>

Show help message.

=item B<--sar-gather=BIN>

Specify the location of the C<sar> binary.  Default is
C</export/crawlspace/daytona_exec/daytona_sarmonitor/bin/sar>.

=item B<--interval=INT>

Set the C<sar> sampling interval in seconds to INT.  Default is 20.

=item B<--pid_file=PIDFILE>

Sets the location of the sar_gather_agent.pl PID file.  Throws an error
if the PID file already exists and contains the PID of a running
process.   Default is C</tmp/sar_gather_agent.pid>.

=item B<--output=OUTPUT>

Specifies the output file for sar_gather_agent.pl results.  Default is
C</tmp/output.dat>.  This file is deleted when sar_gather_agent.pl
terminates, and will be deleted on startup if it already exists.

=item B<--daemonize>

Run sar_gather_agent.pl and daemonize the process.

=head1 DESCRIPTION

Sar_gather_agent.pl forks C<sar> (which forks C<sadc>), and flattens
the C<sar> output to stdout so that all data from each sample interval is
in csv format, on a single line.  When sar_gather_agent.pl terminates,
the associated C<sar> and C<sadc> processes also die (probably because
terminating sar_gather_agent.pl closes C<$sar_pipe> and deletes
C</tmp/sar.dat> so C<sar> cannot write to its output files).

Sar_gather_agent.pl optionally daemonizes itself, so it can be
disassociated from its parent process.

C<Sar> is run with the options C<-Ap> to collect all data.  C<Sar> can
collect less data, but this has not been tested.

From C</export/crawlspace/daytona_exec/daytona_sarmonitor/>, run
C<bin/sar_gather_agent.pl --daemonize>.  By default, three temportary
files are written to C</tmp>:

  output.dat           - Output file specified by the --output
  sar.dat              - Sar binary data output
  sar_gather_agent.pid - PID file specified by the --pid-file

Executing the command: C<kill `cat /tmp/sar_gather_agent.pid`> will
terminate the process and delete the temporary files, including
output.dat.

=cut

