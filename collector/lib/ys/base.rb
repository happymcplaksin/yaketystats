module YS
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
                h = { :fqdn => fqdn, :ts => Time.now.to_i, :t => type.to_s.upcase, :i => instance_variable_get('@interval') }
                h = h.merge options
                fqdn = h.delete(:fqdn)
                h[:p] = "/#{fqdn}/BNW/#{h[:p]}"
                ysout( h )
            end
        end

        def valid?(s)
            h = {}
            h['i']  = /^\d+$/
            h['ts'] = /^\d+$/
            h['v']  = /^[\d.eE+-]+$/
            h['p']  = /^\/[\/\w\d._-]+$/
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
