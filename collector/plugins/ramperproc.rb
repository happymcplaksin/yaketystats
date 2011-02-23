require 'linux/proctable'

# RAM use per process
#
# Right now this plugin looks for the first process that matches
# :procname and reports stats on it. Obviously that's completely
# broken. Perhaps a future refactoring will bring a pid-file reader
# or some other method(s).
#
# Options:
#
# * :procname => String
class Ramperproc
    include YS::Plugin
    include Sys

    def initialize(options)
        @options = options
        self.interval = 60
        @procname = options[:procname]
    end
    def go
        @data = ProcTable.ps
        @data = @data.reject{|p| p.cmdline.include?('debugger')} if $YSDEBUGGER
        @data = [@data.find{|p| p.cmdline.include?(@procname)}].flatten.first
    end
    def stats
        out = ''
        if @data
            # Oh hai. Everyone knows the page size is 4096, dummo.
            out << gauge(:p => "ram_per_proc/rss/#{@procname}", :v => @data.rss.*(4096))
            out << gauge(:p => "ram_per_proc/vsz/#{@procname}", :v => @data.vsize)
        else
            raise YS::NoData
        end
        out
    end
end
