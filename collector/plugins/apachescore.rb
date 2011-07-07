require 'open-uri'
require 'openssl'

# Apache scoreboard stats.
#
# Assumes scoreboard is available at https://127.0.0.1/server-status?auto
#
# options:
#
# * :self_signed_ok => true|false (defaults to true, whether to accept self-signed certs
#
# Note: Does not do SSL stats
class Apachescore
    include YS::Plugin

    def initialize(options)
        @options   = options
        @data      = {}
        @sslverify = {}
        unless @options[:self_signed_ok] == false
            # Make self-signed certs OK
            @sslverify = {:ssl_verify_mode => OpenSSL::SSL::VERIFY_NONE}
        end
        self.interval = 60
    end

    def go
        scoreboard = {
            '_' => "waiting",
            'S' => "starting",
            'R' => "reading",
            'W' => "sending",
            'K' => "reading_keepalive",
            'D' => "dns",
            'C' => "closing",
            'L' => "logging",
            'G' => "finishing",
            'I' => "idle_cleanup",
            '.' => "open",
        }
        strings = {
            "Total Accesses" => ["accesses/accesses",       :derive],
            "Total kBytes"   => ["bytes/bytes",             :derive],
            "CPULoad"        => ["cpuload/cpuload",         :gauge],
            "Uptime"         => ["uptime/uptime",           :gauge],
            "ReqPerSec"      => ["reqpersec/reqpersec",     :gauge],
            "BytesPerSec"    => ["bytespersec/bytespersec", :gauge],
            "BytesPerReq"    => ["bytesperreq/bytesperreq", :gauge],
            "BusyWorkers"    => ["workers/busy",            :gauge],
            "IdleWorkers"    => ["workers/idle",            :gauge]
        }

        @data = {}
        open('https://127.0.0.1/server-status?auto', @sslverify).read.split("\n").each do |line|
            name,value = line.split(': ')
            if name == 'Scoreboard'
                scoreboard.each do |char,path|
                    @data["scoreboard/#{path}"] = ['gauge', value.count(char)]
                end
            else
                if name.match(/bytes/)
                    value *= 1024
                end
                path,type = strings[name]
                if path.nil? or type.nil?
                    # TODO: Make this log instead of puts
                    puts "yikes, can't find path or type for #{name}."
                else
                    @data["apache/#{path}"] = [type, value]
                end
            end
        end
    end
    def stats
        raise YS::NoData unless @data
        out = ''
        @data.each do |label,tv|
            type, value = tv
            out << send(type,{:p => "#{label}", :v => value})
        end
        out
    end
end




