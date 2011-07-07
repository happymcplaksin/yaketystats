#!/usr/local/ys/ruby/bin/ruby

class Nagiosstats
    include YS::Plugin
    def initialize(options)
        @options = options
        self.interval = 900
        @data = {}
        @fqdn = 'your.monitoring.cname.com'
    end
    def go
        ns  = "/usr/local/nagios/bin/nagiostats"

        # If you add more stats to these lists, you must maintain order
        # Oh and these should be options passed in via the .y file, but WOW are we lazy
        labels = %w{hosts/total services/total services/ok services/warning services/critical services/unknown latency/active_service latency/passive_service latency/host}
        list = %w{NUMHOSTS NUMSERVICES NUMSVCOK NUMSVCWARN NUMSVCCRIT NUMSVCUNKN AVGACTSVCLAT AVGPSVSVCLAT AVGACTHSTLAT}

        #first run in 'mrtg' format because it's easy to parse
        # Run the command
        cmd  = "#{ns} -D, -m -d #{list.join(',')}"

        # zip up the results with the list
        out = Hash[list.zip(%x{#{cmd}}.split(','))]
        # make a hash where list items point to our names
        statslabels = Hash[list.zip(labels)]

        @data = {}

        #turn the output into a hash of our_names => values
        out.each do |label,value|
            @data[statslabels[label]] = value.to_i
        end

        # Since the 'mrtg' format doesn't include the following stats, but the non-mrtg run *does* we have to run it again
        extras = {}
        extras['services/active']  = /^Services Actively Checked:\s+/
        extras['services/passive'] = /^Services Passively Checked:\s+/
        out = %x{#{ns}}.split("\n")

        extras.each do |label,regex|
            @data[label] = out.grep(regex).first.sub(regex,'').to_i
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
