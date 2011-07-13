# Basic fiber stats for HP Virtual Connect switches.
#
# options:
# * :community => The SNMP community string (required)
# * :fqdn      => The name(s) of the host(s) you're reporting for as a string or an array (required)
# * :interval  => Optional interval. Default: 300
# * :timeout   => Timeout for SNMP connection in seconds (optional, defaults to 10)
# * :retries   => Number of times to retry a connection (optional, defaults to 2)
require 'snmp'
class Yssnmpvcf
    include YS::Plugin
    include SNMP
    def initialize(options)
        unless options[:community] && options[:fqdn]
            raise YS::MissingRequiredOption
        end

        @fqdn      = [options[:fqdn]].flatten
        @oids      = options[:oids] || ''
        @base      = options[:base]
        @community = options[:community]
        @timeout   = options[:timeout] || 10
        @retries   = options[:retries] || 2
        @options   = options
        @values    = {}
        self.interval = 300
    end

    def doone(host)
        # Here's the map of OIDs to walk plus descriptions.
        #
        # The connUnit items only work with newer switches (or newer firmware).
        # If they're not present, grab stats for every port.
        # Otherwise, only grab stats for ports with:
        # 1) connUnitPortHWState = 4 (active)
        # 2) connUnitPortState   = 2 (online)
        # 3) connUnitPortStatus  = 3 (ready)
        @oids = [
        
        # MIB name: connUnitPortStatIndex
            { 'portnum'                           => '1.3.6.1.3.94.4.5.1.2' },
        
            { 'connUnitPortHWState'               => '1.3.6.1.3.94.1.10.1.23'},
        # possible values:
        #  unknown     (1),
        #  failed      (2), -- port failed diagnostics
        #  bypassed    (3),  -- FCAL bypass, loop only
        #  active      (4), -- connected to a device
        #  loopback    (5), -- Port in ext loopback
        #  txfault     (6), -- Transmitter fault
        #  noMedia     (7), -- media not installed
        #  linkDown    (8)  -- waiting for activity (rx sync)
        
            { 'connUnitPortState'                 => '1.3.6.1.3.94.1.10.1.6' },
        # possible values:
        #  unknown(1),
        #  online(2), -- available for meaningful work
        #  offline(3), -- not available for meaningful work
        #  bypassed(4), -- no longer used (4/12/00)
        #  diagnostics(5)
        
            { 'connUnitPortStatus'                => '1.3.6.1.3.94.1.10.1.7' },
        # possible values:
        #  unknown           (1),
        #  unused            (2), -- device cannot report this status
        #  ready             (3), -- FCAL Loop or FCPH Link reset protocol
        #                         -- initialization has completed
        #  warning           (4), -- do not use (4/12/00)
        #  failure           (5), -- do not use (4/12/00)
        #  notparticipating  (6), -- loop notparticipating and does not
        #                         -- have a loop address
        #  initializing      (7), -- protocol is proceeding
        #  bypass            (8), -- do not use (4/12/00)
        #  ols               (9), -- FCP offline status
        #  other             (10) -- status not described above
        
        # MIB name: connUnitPortStatCountTxElements
            { 'tx_bytes'   => '1.3.6.1.3.94.4.5.1.6' },
        # MIB name: connUnitPortStatCountRxElements
            { 'rx_bytes'   => '1.3.6.1.3.94.4.5.1.7' },
        # MIB name: connUnitPortStatCountRxLinkResets 
            { 'rx_resets' => '1.3.6.1.3.94.4.5.1.33' },
        # MIB name: connUnitPortStatCountTxLinkResets
            { 'tx_resets' => '1.3.6.1.3.94.4.5.1.34' }
        ]
        
        ###
        # active_map maps the three "is this port active" OID names to the values
        # they'll have if the port is active
        @active_map = {
            'connUnitPortHWState' => 4,
            'connUnitPortState'   => 2,
            'connUnitPortStatus'  => 3,
        }

        raise unless host
        begin
            SNMP::Manager.open(:Host => host, :Community => @community, :Timeout => @timeout, :Retries => @retries) do |manager|
                towalk = @oids.map{|x| x.values}.flatten
                manager.walk(towalk) do |row|
                    # Get all the values into an array
                    vals = row.collect{|v| v.value}
                    # Get all the names into an array
                    names = @oids.collect{|h| h.keys.first}
                    # Hash it up
                    pairs = Hash[*names.zip(vals).flatten]
                    
                    # Skip inactive ports but only if the Magic Three OIDs work
                    next unless @active_map.all? do |key,value|
                        pairs[key] == value or
                            pairs[key].to_s == 'noSuchInstance'
                    end
                    
                    # No stats for the activity OIDs
                    @active_map.keys.map{|k| pairs.delete(k)}
                    
                    portnum = pairs.delete('portnum')
                    
                    pairs.each do |name,value|
                        if value.class == SNMP::OctetString
                            value = value.unpack("H*").first.hex
                        end
                        value *= 8 if name.match('bytes')
                        @values << ysout(p: "#{host}/snmp/#{name}/#{portnum}", t: 'DERIVE', i: self.interval, ts: Time.now.to_i, v: value)
                    end
                end
            end
        rescue => e
            print "Trouble talking to #{host}: " + e.inspect + "\n"
            # puts e.backtrace
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
