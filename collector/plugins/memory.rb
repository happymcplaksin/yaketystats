# Memory stats derived from /proc/meminfo
class Memory
    include YS::Plugin

    def initialize(options)
        @options = options
        self.interval = 60
        @enabled = %w{memtotal memfree buffers cached swaptotal swapfree}
    end
    def sample
        out = {}
        IO.read('/proc/meminfo').strip.split("\n").each do |line|
            key,value,trash = line.split
            out[key.sub(/:$/,'').downcase] = value.to_i.*(1024)
        end
        out
    end
    def go
        @data = sample
    end
    def stats
        raise YS::NoData unless @data
        out = ''
        @enabled.each do |key|
            next unless @data[key]
            out << gauge(:p => "memory/#{key}", :v => @data[key])
        end
        out
    end
end
