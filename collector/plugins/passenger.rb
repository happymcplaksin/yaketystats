# Passenger stats
#
# options:
# * :passenger_path => Path to passenger-status (required)
# * :sudo => true|false (defaults to false)
#
# TODO?  'passenger-status --show=xml' has per-process stats.  Worth anything?
class Passenger
    include YS::Plugin

    def initialize(options)
        unless options[:passenger_path]
            raise YS::MissingRequiredOption
        end
        @options = options
        @sudo = options[:sudo] ? 'sudo' : ''
        @passenger_path = options[:passenger_path]
        @data = {}
        self.interval = 60
    end

    def go
        @data = {}
        re = Regexp.new(/\s+=\s+/)
        map = {
            'max'                     => 'max',
            'count'                   => 'count',
            'active'                  => 'active',
            'inactive'                => 'inactive',
            'Waiting on global queue' => 'waiting',
        }
        out = ''
        cmd = "#{@sudo} #{@passenger_path}/passenger-status"
        %x{#{cmd}}.split("\n").each do |line|
            next unless line.match(re)
            name,value = line.split(re)
            map.each do |mapname, path|
                if name.match("^#{mapname}")
                    @data["passenger/#{path}"] = value
                end
            end
        end
    end

    def stats
        raise YS::NoData unless @data
        out = ''
        @data.each do |label,value|
            out << gauge(:p => "#{label}", :v => value)
        end
        out
    end
end

