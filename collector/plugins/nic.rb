class Nic
    include YS::Plugin

    IGNORE = %w{lo0 sit0}

    attr_reader :stats
    def initialize(options)
        @options = options
        self.interval = 60
        @enabled = %w{rx_bytes rx_errors tx_bytes tx_errors}
        @enabled << 'multicast' if options[:enable_multicast]
    end
    def up?(dir)
        IO.read(File.join(dir,'operstate')).strip == 'up'
    end
    def go
        no  = %w{. .. sit0 lo0}
        top = '/sys/class/net/'
        @stats = ''
        data   = {}
        Dir.entries('/sys/class/net/').-(no).each do |nic|
            newtop = File.join(top,nic)
            next unless up?(newtop)
            newtop = File.join(newtop,'statistics')
            @enabled.each do |file|
                name = File.join('net',nic,file)
                data[name] = IO.read(File.join(newtop,file)).strip.to_i
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
end
