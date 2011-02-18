require 'linux/proctable'

class Collectorstats
    include YS::Plugin
    include Sys

    def initialize(options)
        @options = options
        self.interval = 60
        @procname = options[:procname]
    end
    def go
        @data = ProcTable.ps(Process.pid)
    end
    def stats
        out = ''
        if @data
            # Oh hai. Everyone knows the page size is 4096, dummo.
            out << gauge(:p => "collector/memory/rss", :v => @data.rss.*(4096))
            out << gauge(:p => "collector/memory/vsz", :v => @data.vsize)
            out << counter(:p => "collector/cpu/user", :v => @data.utime.*(100))
            out << counter(:p => "collector/cpu/sys",  :v => @data.stime.*(100))
        else
            raise YS::NoData
        end
        out
    end
end
