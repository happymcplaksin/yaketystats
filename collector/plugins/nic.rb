# Network Interface stats
class Nic
    include YS::Plugin

    IGNORE = %w{lo0 sit0}

    def initialize(options)
        @options = options
        self.interval = 60
        @enabled = %w{rx_bytes rx_errors tx_bytes tx_errors}
        @enabled << 'multicast' if options[:enable_multicast]
        @ignore = @options[:ignore] if options[:ignore]
    end
    def up?(dir)
        sysread(File.join(dir,'operstate')) == 'up'
    end
    def go
        no  = %w{. .. sit0 lo0 bonding_masters}
        no += @ignore if @ignore
        top = '/sys/class/net/'
        @stats = ''
        data   = {}
        Dir.entries('/sys/class/net/').-(no).each do |nic|
            newtop = File.join(top,nic)
            next unless up?(newtop)
            newtop = File.join(newtop,'statistics')
            @enabled.each do |file|
                name = File.join('net',nic,file)
                data[name] = sysread(File.join(newtop,file)).to_i
            end
        end
        data.map do |stat,value|
            if /bytes/.match(stat)
                @stats << derive(:p => stat.sub(/bytes/,'bits'), :v => value.*(8))
            else
                @stats << derive(:p => stat, :v => value)
            end
        end
    end
    def stats
        raise YS::NoData unless @stats
        @stats
    end
end
