module YS
    module Schedstat
        # From: http://www.kernel.org/doc/Documentation/scheduler/sched-stats.txt
        #
        # /proc/<pid>/schedstat
        #----------------
        #   schedstats also adds a new /proc/<pid>/schedstat file to include some of
        #   the same information on a per-process level.  There are three fields in
        #   this file correlating for that process to:
        #        1) time spent on the cpu
        #        2) time spent waiting on a runqueue
        #        3) # of timeslices run on this cpu
        @fields = %w{on_cpu in_runqueue timeslices}.map{|x| x.to_sym}
        Cpuinfo = Struct.new(*@fields)
        def schedstat(pid)
            raise TypeError unless pid.is_a?(Fixnum) || pid == 'self'

            # I thought about checking to see if the file exists and
            # raising an error if not, but IO.read does that for me...
            data   = IO.read("/proc/#{pid}/schedstat").strip.split
            out    = Cpuinfo.new(*data)
            out
        end

    end
end
