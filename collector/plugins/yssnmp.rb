require 'snmp'
require 'pp'

# Basic (aka we don't know anything about SNMP) SNMP plugin for YS
# 
# options:
# * :base      => A string to be prepended to all OIDs (optional)
# * :community => The SNMP community string (required)
# * :fqdn      => the name of the host you're reporting for (required)
# * :oids      => A hash: statspath => OID (required)
# * :interval  => Optional interval. Default: 300

class Yssnmp
    include YS::Plugin
    include SNMP
    def initialize(options)
        unless options[:community] && options[:fqdn] && options[:oids]
            raise YS::MissingRequiredOption
        end

        @fqdn      = options[:fqdn]
        @oids      = options[:oids] || ''
        @base      = options[:base]
        @community = options[:community]
        @options   = options
        @values    = {}
        self.interval = 300
    end
    def go
        raise unless @fqdn
        @values = {}
        Manager.open(:Host => @fqdn, :Community => @community ) do |manager|
            @oids.each_pair do |label,oid|
                manager.walk( ObjectId.new("#{@base}.#{oid}") ) do |row|
                    @values[label] =  row.value
                end
            end
        end
    end
    def stats
        raise YS::NoData if @values.empty?
        out = ''
        @values.each do |label,value|
            out << gauge(:p => "snmp/#{label}", :v => value)
        end
        out
    end
    def monitor
        raise YS::NoData if @values.empty?
        out = ''
        @values.each do |label,value|
            out << "#{label} #{value}\n"
        end
        if $YSDEBUGGER
            puts out
        else
            File.open("/usr/local/nagios/dmz/ys/#{@fqdn}.#{self.class}","w"){|f| f.write out}
        end
    end
end
