require 'net/http'

module Pusher
    def maintenance?
        ! Net::HTTP.get_response(URI.parse("#{@stats_server}/maintenance")).class == Net::HTTPNotFound
    end
    def upload_stats
        return if maintenance?
        log.debug "Stepping aside stats file" if $YSDEBUG
        step_aside
        # look for stats files that aren't 'new'
        files = Dir.glob("#{@stats_dir}/[0-9]*")
        log.debug "Found these stats files: [#{files.join(',')}]" if $YSDEBUG
        files.sort!

        okre = /OK/
        url = URI.parse("#{@stats_server}/#{@store_path}")
        req = Net::HTTP::Post.new(url.path)
        Net::HTTP.new(url.host, url.port).start do |http|
            files.each do |upme|
                if File.zero? upme
                    File.unlink upme
                    next
                end
                upme = File.new(upme)
                log.debug "Posting #{upme.path}" if $YSDEBUG
                req.set_form([
                                        ['dataversion' , '1.3'],
                                        ['host' , fqdn],
                                        ['datafile' , upme]
                                   ],'multipart/form-data')

                pp req if $YSDEBUG
                res = http.request(req)
                pp res if $YSDEBUG
                if okre.match res.body
                    upme.close
                    File.unlink upme
                else
                    p res.body if $YSDEBUG
                    log.fatal "Unable to upload. #{res.class} #{res.error!}"
                    break
                end
            end
        end
    end
end
