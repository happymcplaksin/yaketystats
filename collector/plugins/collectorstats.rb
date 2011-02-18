require 'linux/proctable'
require 'ys/schedstat'

class Collectorstats
    include YS::Plugin
    include YS::Schedstat
    include Sys

    def initialize(options)
        @options = options
        self.interval = 60
        @procname = options[:procname]
    end
    def go
        @ramdata = ProcTable.ps(Process.pid)
        @cpudata = schedstat('self')
    end
    def stats
        out = ''
        if @ramdata
            # Oh hai. Everyone knows the page size is 4096, dummo.
            out << gauge(:p => "collector/memory/rss", :v => @ramdata.rss.*(4096))
            out << gauge(:p => "collector/memory/vsz", :v => @ramdata.vsize)
        else
            raise YS::NoData
        end
        if @cpudata
            @cpudata.each_pair do |key,value|
                out << counter(:p => "collector/cpu/#{key}", :v => value)
            end
        end
        out
    end
end
