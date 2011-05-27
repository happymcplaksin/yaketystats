module YS
    BCVRE = Regexp.new('bcv',Regexp::IGNORECASE) # FIX! Add the stuff nagios ignores
    class NoData < StandardError; end
    class NoInterval < StandardError; end
    module Base
        extend self

        SEP=''
        DST = %w{absolute compute counter derive gauge}

        # Return contents of /var/yaketystats/fqdn
        def fqdn()
            @fqdn ||= IO.read('/var/yaketystats/fqdn').strip
        end

        # #sysread because everything is hard. http://tickets.opscode.com/browse/OHAI-196
        # This code was initially written to supprot RHEL5, so upgrading to a newer kernel
        # that fixes this problem isn't possible. Instead, we work around.
        # Completely arbitrary 2k max length. Patch it if that's problematic.
        # returns a String#strip 'ed
        def sysread(file)
            f = File.new(file)
            o = f.read_nonblock(2048)
            f.close
            o.strip
        end

        # Sees if the thing you've passed it matches BCVRE or
        # anything in @options[:ignore]
        def ignore?(name)
            return true if YS::BCVRE.match(name)
            return false unless @options[:ignore]
            [@options[:ignore]].flatten.map{|s| Regexp.new(s)}.any?{|r| r.match(name)}
        end

        # remove the attr_reader :options boilerplate
        def options
            @options
        end

        # returns the interval at which the plugin should run
        def interval
            @options[:interval]
        end
        # Sets a default for the interval at which the plugin should run
        # Config file can override in the options hash.
        def interval=(i)
            @options[:interval] = i unless @options[:interval]
        end

        def lock
            @lock = true
        end

        def locked?
            @lock
        end

        def unlock
            @lock = false
        end

        # Call 'out' with a hash like so
        # x={:p => 'machines/count', :t => 'TYPE', :i => 300, :ts => 123456, :v => 566}
        # YS.out x
        def ysout(hash)
            hash.keys.map{|k| k.to_s}.sort.map{|k| "#{k}=#{hash[k.to_sym]}"}.join(SEP) + "\n"
        end

        # Creates dynamic methods with auto-timestamp for datasource types call like:
        # YS.gauge(interval,path,value)
        # YS.counter(300,'/path/to/thing',83983)
        DST.each do |type|
            define_method type.to_sym do |options|
                #syslog unless options[:path] and options[:value]
                h = { :fqdn => fqdn, :ts => Time.now.to_i, :t => type.to_s.upcase, :i => interval }
                h = h.merge options
                fqdn = h.delete(:fqdn)
                h[:p] = "/#{fqdn}/#{h[:p]}"
                ysout( h )
            end
        end

        def valid?(s)
            h = {}
            h['i']  = /^\d+$/
            h['ts'] = /^\d+$/
            h['v']  = /^[\d.eE+-]+$/
            h['p']  = /^\/*[\/\w\d._-]+$/
            s.split("\n").each do |line|
                line.split(SEP).each do |pair|
                    key,value = pair.split('=')
                    return false if key.nil? or value.nil?
                    if h[key]
                        unless h[key].match(value)
                            puts "invalid value for #{key}: [#{value}]"
                            return false
                        end
                    else
                        if key == 't'
                            unless DST.include?(value.downcase)
                                puts "invalid type"
                                return false
                            end
                        else
                            puts "some crazy extra key: #{key}"
                            return false
                        end
                    end
                end
            end
            true
        end

    end
end
