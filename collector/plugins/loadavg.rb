# Load average stats
class Loadavg
    include YS::Plugin

    def initialize(options)
        @options = options
        self.interval = 60
    end

    def stats
        raise YS::NoData unless @data
        out = ''
        @data.each do |key,value|
            out << gauge( :p => "load/#{key}", :v => value )
        end
        out
    end

    def go
        @data = Hash.new
        @data['1-minute'], @data['5-minute'], @data['15-minute'] = IO.read('/proc/loadavg').scan(/([\d.]+)\s+([\d.]+)\s+([\d.]+).*/).first
    end
end
