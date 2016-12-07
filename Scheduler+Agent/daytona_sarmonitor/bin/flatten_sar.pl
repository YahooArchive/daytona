#!/usr/bin/perl -w

use DateTime;

select STDOUT;
$| = 1;

my %stats = (
    cpu_utilization => {
        headings => [ "CPU", "%usr", "%nice", "%sys", "%iowait", "%steal",
                      "%irq", "%soft", "%guest", "%idle" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    interrupts => {
        headings => [ "INTR", "intr/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    task_creation => {
        headings => [ "proc/s", "cswch/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    swapping => {
        headings => [ "pswpin/s", "pswpout/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    paging => {
        headings => [ "pgpgin/s", "pgpgout/s", "fault/s", "majflt/s", "pgfree/s",
                     "pgscank/s", "pgscand/s", "pgsteal/s", "%vmeff" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    IO => {
        headings => [ "tps", "rtps", "wtps", "bread/s", "bwrtn/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    memory_paging => {
        headings => [ "frmpg/s", "bufpg/s", "campg/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    memory_utilization => {
        headings => [ "kbmemfree", "kbmemused", "%memused", "kbbuffers",
                      "kbcached", "kbcommit", "%commit" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    swap_space => {
        headings => [ "kbswpfree", "kbswpused", "%swpused", "kbswpcad", "%swpcad" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    kernel_tables => {
        headings => [ "dentunusd", "file-nr", "inode-nr", "pty-nr" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    queues => {
        headings => [ "runq-sz", "plist-sz", "ldavg-1", "ldavg-5", "ldavg-15" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    tty => {
        headings => [ "TTY", "rcvin/s", "xmtin/s", "framerr/s", "prtyerr/s",
                      "brk/s", "ovrun/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    block_devices => {
        headings => [ "DEV", "rrqm/s", "wrqm/s", "r/s", "w/s", "rd_sec/s",
                      "wr_sec/s", "rkB/s", "wkB/s", "avgrq-sz", "avgqu-sz",
                      "await", "svctm", "%util" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    DEV => {
        headings => [ "IFACE", "rxpck/s", "txpck/s", "rxkB/s", "txkB/s",
                      "rxcmp/s", "txcmp/s", "rxmcst/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    EDEV => {
        headings => [ "IFACE", "rxerr/s", "txerr/s", "coll/s", "rxdrop/s",
                      "txdrop/s", "txcarr/s", "rxfram/s", "rxfifo/s", "txfifo/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    NFS => {
        headings => [ "call/s", "retrans/s", "read/s", "write/s", "access/s",
                      "getatt/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    NFSD => {
        headings => [ "scall/s", "badcall/s", "packet/s", "udp/s", "tcp/s",
                      "hit/s", "miss/s", "sread/s", "swrite/s", "saccess/s",
                      "sgetatt/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    SOCK => {
        headings => [ "totsck", "tcpsck", "udpsck", "rawsck", "ip-frag",
                      "tcp-tw" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    IP => {
        headings => [ "irec/s", "fwddgm/s", "idel/s", "orq/s", "asmrq/s",
                  "asmok/s", "fragok/s", "fragcrt/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    EIP => {
        headings => [ "ihdrerr/s", "iadrerr/s", "iukwnpr/s", "idisc/s",
                      "odisc/s", "onort/s", "asmf/s", "fragf/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    ICMP => {
        headings => [ "imsg/s", "omsg/s", "iech/s", "iechr/s", "oech/s", "oechr/s",
                      "itm/s", "itmr/s", "otm/s", "otmr/s", "iadrmk/s",
                      "iadrmkr/s", "oadrmk/s", "oadrmkr/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    EICMP => {
        headings => [ "ierr/s", "oerr/s", "idstunr/s", "odstunr/s", "itmex/s",
                      "otmex/s", "iparmpb/s", "oparmpb/s", "isrcq/s", "osrcq/s",
                      "iredir/s", "oredir/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    TCP => {
        headings => [ "active/s", "passive/s", "iseg/s", "oseg/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    ETCP => {
        headings => [ "atmptf/s", "estres/s", "retrans/s", "isegerr/s", "orsts/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    UDP => {
        headings => [ "idgm/s", "odgm/s", "noport/s", "idgmerr/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    SOCK6 => {
        headings => [ "tcp6sck", "udp6sck", "raw6sck", "ip6-frag" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    IP6 => {
        headings => [ "irec6/s", "fwddgm6/s", "idel6/s", "orq6/s", "asmrq6/s",
                      "asmok6/s", "imcpck6/s", "omcpck6/s", "fragok6/s",
                      "fragcr6/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    EIP6 => {
        headings => [ "ihdrer6/s", "iadrer6/s", "iukwnp6/s", "i2big6/s",
                      "idisc6/s", "odisc6/s", "inort6/s", "onort6/s", "asmf6/s",
                      "fragf6/s", "itrpck6/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    ICMP6 => {
        headings => [ "imsg6/s", "omsg6/s", "iech6/s", "iechr6/s", "oechr6/s",
                      "igmbq6/s", "igmbr6/s", "ogmbr6/s", "igmbrd6/s", "ogmbrd6/s",
                      "irtsol6/s", "ortsol6/s", "irtad6/s", "inbsol6/s",
                      "onbsol6/s", "inbad6/s", "onbad6/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    EICMP6 => {
        headings => [ "ierr6/s", "idtunr6/s", "odtunr6/s", "itmex6/s", "otmex6/s",
                      "iprmpb6/s", "oprmpb6/s", "iredir6/s", "oredir6/s",
                      "ipck2b6/s", "opck2b6/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    UDP6 => {
        headings => [ "idgm6/s", "odgm6/s", "noport6/s", "idgmer6/s" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
    power => {
        headings => [ "CPU", "MHz" ],
        heading_string => "",
        heading_string_done => 0,
        data_string => "",
    },
);

my @stats_keys = ( "cpu_utilization", "interrupts", "task_creation",
                   "swapping", "paging", "IO", "memory_paging",
                   "memory_utilization", "swap_space", "kernel_tables",
                   "queues", "tty", "block_devices", "DEV", "EDEV", "NFS",
                   "NFSD", "SOCK", "IP", "EIP", "ICMP", "EICMP", "TCP", "ETCP",
                   "UDP", "SOCK6", "IP6", "EIP6", "ICMP6", "EICMP6", "UDP6",
                   "power" );

sub get_data {
  my ($stat) = @_;

  $stats{$stat}{data_string} = "";

  while (<STDIN>) {
    my $line = $_;

    # Is there a timestamp on this line?  (There'd better be.)
    if ($line =~ m/^\d{2}:\d{2}:\d{2}\s+/) {
      # If yes, then we want to process this line
      my @data = split /\s+/, $line;
      my $timestamp = shift @data;

      # Generate the header, if necessary
      if ($stats{$stat}{heading_string_done} == 0) {
        foreach my $label (@{ $stats{$stat}{headings} }) {
          if ($stat eq "cpu_utilization" ||
              $stat eq "interrupts" ||
              $stat eq "power") {
            if ($label eq "CPU" || $label eq "INTR") {
              next;
            }

            $stats{$stat}{heading_string} .= "$label\_$data[0],";
          }
          else {
            $stats{$stat}{heading_string} .= "$label,";
          }
        }
      }

      if ($stat eq "cpu_utilization" ||
          $stat eq "interrupts" ||
          $stat eq "power") {
        shift @data;
      }

      foreach my $datum (@data) {
        $stats{$stat}{data_string} .= "$datum,";
      }
    } else {
      $stats{$stat}{heading_string_done} = 1;
      last;
    }
  }

  #print "[$stat] $stats{$stat}{heading_string}\n";
  #print "$stats{$stat}{data_string}\n";
}

sub print_all_headers {
  my ($fh) = @_;

  print $fh "Timestamp,";
  foreach my $stat (@stats_keys) {
    print $fh "$stats{$stat}{heading_string}";
  }
  print $fh "\n";
}

sub print_all_data {
  my ($fh, $timestamp) = @_;

  print $fh "$timestamp,";
  foreach my $stat (@stats_keys) {
    print $fh "$stats{$stat}{data_string}";
  }
  print $fh "\n";
}

sub to_epoch {
  my ($timestamp) = @_;
  my $hour = 0;
  my $minute = 0;
  my $second = 0;

  if ($timestamp =~ m/^(\d{2}):(\d{2}):(\d{2})$/) {
    $hour = $1;
    $minute = $2;
    $second = $3;
  }
  else {
    warn "Warning: Weird timestamp fro sar: '$timestamp'.\n";
  }

  my $datetime = DateTime->now;
  $datetime->set_hour($hour);
  $datetime->set_minute($minute);
  $datetime->set_second($second);

  $timestamp = $datetime->epoch();

  return $timestamp;
}

MAIN: {
  my $print_heading = 1;

  select STDOUT;
  $| =1;

  while (<STDIN>) {
    my $line = $_;

    # Is there a timestamp on this line?
    if ($line =~ m/^\d{2}:\d{2}:\d{2}\s+/) {
      # If yes, then we want to process this line
      my @labels = split /\s+/, $line;
      my $timestamp = shift @labels;

      $timestamp = to_epoch($timestamp);

      foreach my $stat (keys %stats) {
        if ($stats{$stat}{headings}->[0] eq $labels[0] &&
            $stats{$stat}{headings}->[1] eq $labels[1]) {
          if ($stats{$stat}{headings}->[0] eq "CPU" &&
              $stats{$stat}{headings}->[1] eq "%usr") {
            # I'm assuming CPU utilization is always generated, and always the
            # first set of data output.
            if ($stats{$stat}{heading_string_done} != 0) {
              if ($print_heading) {
                print_all_headers(STDOUT);
                $print_heading = 0;
              }

              # We need to dump out the previous data
              print_all_data(STDOUT, $timestamp);
            }

            get_data("cpu_utilization");
          }
          elsif ($stats{$stat}{headings}->[0] eq "proc/s") {
            get_data("task_creation");
          }
          elsif ($stats{$stat}{headings}->[0] eq "INTR") {
            get_data("interrupts");
          }
          elsif ($stats{$stat}{headings}->[0] eq "pswpin/s") {
            get_data("swapping");
          }
          elsif ($stats{$stat}{headings}->[0] eq "pgpgin/s") {
            get_data("paging");
          }
          elsif ($stats{$stat}{headings}->[0] eq "tps") {
            get_data("IO");
          }
          elsif ($stats{$stat}{headings}->[0] eq "frmpg/s") {
            get_data("memory_paging");
          }
          elsif ($stats{$stat}{headings}->[0] eq "kbmemfree") {
            get_data("memory_utilization");
          }
          elsif ($stats{$stat}{headings}->[0] eq "kbswpfree") {
            get_data("swap_space");
          }
          elsif ($stats{$stat}{headings}->[0] eq "dentunusd") {
            get_data("kernel_tables");
          }
          elsif ($stats{$stat}{headings}->[0] eq "runq-sz") {
            get_data("queues");
          }
          elsif ($stats{$stat}{headings}->[0] eq "TTY") {
            get_data("tty");
          }
          elsif ($stats{$stat}{headings}->[0] eq "DEV") {
            get_data("block_devices");
          }
          elsif ($stats{$stat}{headings}->[0] eq "IFACE" &&
                 $stats{$stat}{headings}->[0] eq "rxpck/s") {
            get_data("DEV");
          }
          elsif ($stats{$stat}{headings}->[0] eq "IFACE" &&
                 $stats{$stat}{headings}->[0] eq "rxerr/s") {
            get_data("EDEV");
          }
          elsif ($stats{$stat}{headings}->[0] eq "call/s") {
            get_data("NFS");
          }
          elsif ($stats{$stat}{headings}->[0] eq "scall/s") {
            get_data("NFSD");
          }
          elsif ($stats{$stat}{headings}->[0] eq "totsck") {
            get_data("SOCK");
          }
          elsif ($stats{$stat}{headings}->[0] eq "irec/s") {
            get_data("IP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "ihdrerr/s") {
            get_data("EIP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "imsg/s") {
            get_data("ICMP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "ierr/s") {
            get_data("EICMP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "active/s") {
            get_data("TCP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "atmptf/s") {
            get_data("ETCP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "idgm/s") {
            get_data("UDP");
          }
          elsif ($stats{$stat}{headings}->[0] eq "tcp6sck") {
            get_data("SOCK6");
          }
          elsif ($stats{$stat}{headings}->[0] eq "irec6/s") {
            get_data("IP6");
          }
          elsif ($stats{$stat}{headings}->[0] eq "ihdrer6/s") {
            get_data("EIP6");
          }
          elsif ($stats{$stat}{headings}->[0] eq "imsg6/s") {
            get_data("ICMP6");
          }
          elsif ($stats{$stat}{headings}->[0] eq "ierr6/s") {
            get_data("EICMP6");
          }
          elsif ($stats{$stat}{headings}->[0] eq "idgm6/s") {
            get_data("UDP6");
          }
          elsif ($stats{$stat}{headings}->[0] eq "CPU" &&
                 $stats{$stat}{headings}->[1] eq "MHz") {
            get_data("power");
          }
        }
      }
    }
  }

  exit;
}

TEST: {
  foreach my $stat (keys %stats) {
    foreach my $label (@{ $stats{$stat}{headings} }) {
      print "$label ";
    }
    print "\n";
  }
}
