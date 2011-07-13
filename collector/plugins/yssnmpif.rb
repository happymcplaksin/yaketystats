require 'snmp'

# Happy & Sam 5/10/2011

# Basic (aka we don't know anything about SNMP) SNMP interface plugin for YS
# 
# options:
# * :community => The SNMP community string (required)
# * :fqdn      => The name(s) of the host(s) you're reporting for as a string or an array (required)
# * :interval  => Optional interval. Default: 300
# * :timeout   => Timeout for SNMP connection in seconds (optional, defaults to 10)
# * :retries   => Number of times to retry a connection (optional, defaults to 2)
class Yssnmpif
    include YS::Plugin
    include SNMP
    def initialize(options)
        unless options[:fqdn] && options[:community]
            raise YS::MissingRequiredOption
        end
        @fqdn      = [options[:fqdn]].flatten
        @oids      = {
            ifDescr:         '1.3.6.1.2.1.2.2.1.2',
            ifAdminStatus:   '1.3.6.1.2.1.2.2.1.7',
            ifSpeed:         '1.3.6.1.2.1.2.2.1.5',
            ifOperStatus:    '1.3.6.1.2.1.2.2.1.8',
            ifInOctets:      '1.3.6.1.2.1.2.2.1.10',
            ifInUcastPkts:   '1.3.6.1.2.1.2.2.1.11',
            ifInNUcastPkts:  '1.3.6.1.2.1.2.2.1.12',
            ifInDiscards:    '1.3.6.1.2.1.2.2.1.13',
            ifInErrors:      '1.3.6.1.2.1.2.2.1.14',
            ifOutOctets:     '1.3.6.1.2.1.2.2.1.16',
            ifOutUcastPkts:  '1.3.6.1.2.1.2.2.1.17',
            ifOutNUcastPkts: '1.3.6.1.2.1.2.2.1.18',
            ifOutDiscards:   '1.3.6.1.2.1.2.2.1.19',
            ifOutErrors:     '1.3.6.1.2.1.2.2.1.20',
            # 64-bit
            ifHCInOctets:    '1.3.6.1.2.1.31.1.1.1.6',
            ifHCOutOctets:   '1.3.6.1.2.1.31.1.1.1.10',
            ifHighSpeed:     '1.3.6.1.2.1.31.1.1.1.15'
        }
        @community = options[:community]
        @timeout   = options[:timeout] || 10
        @retries   = options[:retries] || 2
        @options   = options
        @values    = ''
        self.interval = 300
    end
    def doone(host)
        raise unless host
        Manager.open(:Host => host, :Community => @community, :Timeout => @timeout, :Retries => @retries) do |manager|
            manager.walk( @oids.values ) do |row|
                # Skip interfaces that are not up.
                next unless row.select{|r| [@oids[:ifOperStatus],@oids[:ifAdminStatus]].include?(r.oid[0..-2].join('.'))}.map{|x| x.value} == [1,1]
                tmp = Hash[@oids.keys.zip(row.collect{|v| v.value})].reject{|k,v| [:ifIndex,:ifAdminStatus,:ifOperStatus].include?(k)}
                iface = tmp.delete(:ifDescr)
                iface = iface.gsub(/[\/ ]/,'-')
                # Geez!  When ruby-snmp can't find the OID you requested it
                # returns an object in the NoSuchInstance class.
                # NoSuchInstance#to_s returns 'NoSuchInstance'
                # See the NoSuchInstance class here:
                # https://github.com/hallidave/ruby-snmp/blob/master/lib/snmp/varbind.rb
                #
                # If one 64-bit counter exists, assume the other does too and delete the 32-bit counters which are useless
                if tmp[:ifHCInOctets].to_s == 'noSuchInstance'
                    tmp.reject!{|k,v| [:ifHCInOctets,:ifHCOutOctets,:ifHighSpeed].include?(k)}
                    bits=32
                else
                    tmp.reject!{|k,v| [:ifInOctets,:ifOutOctets,:ifSpeed].include?(k)}
                    bits=64
                end
                tmp.each do |label,value|
                    next if value.to_s == 'noSuchInstance'
                    type = 'DERIVE'
                    if label.to_s.match('Speed')
                        type = 'GAUGE'
                        value = value.to_i
                        # The unit for ifHighSpeed is 1 million bps
                        value *= 1000000 if bits == 64
                    end
                    if label.to_s.match(/Octets/)
                        value = value.to_i * 8
                        label = label.to_s.sub('Octets','Bits').to_sym
                    end
                    @values << ysout( p: "#{host}/snmpif/#{label}/#{iface}", t: type, i: self.interval, ts: Time.now.to_i, v: value )
                end
            end
        end
    end
    def go
        @values = ''
        @fqdn.each do |host|
            doone host
        end
    end
    def stats
        raise YS::NoData if @values.empty?
        @values
    end
end
