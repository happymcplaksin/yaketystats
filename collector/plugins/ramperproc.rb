require 'linux/proctable'

# RAM use per process
#
# By default this plugin looks for the first process that matches :procname
# and reports stats on it.  If you supply the 'all' option, it will total
# the size of all processes and report that.  Perhaps a future refactoring
# will bring a pid-file reader or some other method(s).
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
        @statname = options[:statname] || @procname
        @all = options[:all]
    end
    def go
        @data = ProcTable.ps
        @data = @data.reject{|p| p.cmdline.include?('debugger')} if $YSDEBUGGER
        @data = [@data.find_all{|p| p.cmdline.include?(@procname)}].flatten
    end
    def stats
        out = ''
        rss = vsize = 0
        if @data
            unless @all
                @data = [@data.first]
            end
            @data.each do |one|
                # Oh hai. Everyone knows the page size is 4096, dummo.
                rss += one.rss * 4096
                vsize += one.vsize
            end
            out << gauge(:p => "ram_per_proc/rss/#{@statname}", :v => rss)
            out << gauge(:p => "ram_per_proc/vsz/#{@statname}", :v => vsize)
        else
            raise YS::NoData
        end
        out
    end
end
