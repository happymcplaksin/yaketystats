require 'ys'
require 'pp'
require 'ys/plugin'
require 'yaml'

class Collector
    include YS::Base
    def initialize
        $YSDEBUG = true
        @plugins     = []
        @config      = DaemonKit::Config.load('collector')
        @mydir       = File.expand_path(File.join(File.dirname(__FILE__), '..'))
        @pipefile    = File.join(@mydir,'run/bucket')
        @pipe        = nil
        @plugconfdirs= [File.join(@mydir,'etc/plugins.d'), File.join(@mydir,'etc/plugouts.d')]
        @stats_dir   = @config.stats_dir
        @stats_server= @config.stats_server
        @store_path  = @config.store_path
        @stats_file  = "#{@stats_dir}/new"
        @size_limit  = 500000
        open_pipe
        load_plugins
        schedule_plugins
        schedule_services
    end

    def reload
        unschedule_plugins
        load_plugins
        schedule_plugins
    end

    def unschedule_plugins
        DaemonKit::Cron.scheduler.find_by_tag('user').map{|job| job.unschedule}
    end

    def maintenance?
        puts ObjectSpace.statistics
        http_agent  = ::Curl::Easy.new
        http_agent.headers["User-Agent"] = 'YaketyStats 3.0 Collector'
        log.debug "Looking for maint file: #{@stats_server}/maintenance" if $YSDEBUG
        # return if maint file
        http_agent.url="#{@stats_server}/maintenance"
        http_agent.perform
        log.debug "Response for maint file was [#{http_agent.response_code}]" if $YSDEBUG
        r = ! http_agent.response_code.nil? && http_agent.response_code != 404
        http_agent.close
        r
    end

    def upload_stats
        return if maintenance?
        puts ObjectSpace.statistics
        log.debug "Stepping aside stats file" if $YSDEBUG
        step_aside
        # look for stats files that aren't 'new'
        files = Dir.glob("#{@stats_dir}/[0-9]*")
        log.debug "Found these stats files: [#{files.join(',')}]" if $YSDEBUG
        files.sort!

        http_agent  = ::Curl::Easy.new
        http_agent.headers["User-Agent"] = 'YaketyStats 3.0 Collector'
        http_agent.multipart_form_post = true
        http_agent.url = "#{@stats_server}/#{@store_path}"
        okre = /OK/
        files.each do |upme|
            if File.zero? upme
                File.unlink upme
                next
            end
            log.debug "Posting #{upme}" if $YSDEBUG
            http_agent.verbose = true if $YSDEBUG
            http_agent.http_post(::Curl::PostField.content('dataversion','1.3'),
                                 ::Curl::PostField.content('host', fqdn),
                                 ::Curl::PostField.file('datafile',upme))
            if okre.match http_agent.body_str
                File.unlink upme
            else
                p http_agent.body_str if $YSDEBUG
                log.fatal "Unable to upload. #{http_agent.response_code}"
                break
            end
        end
        http_agent.close
    end

    def open_pipe
        unless FileTest.exists?(@pipefile)
            FileUtils.mkdir_p(rundir) unless FileTest.exists?(rundir)
            system "mkfifo #{@pipefile}"
        end
        log.debug "About to open the pipe." if $YSDEBUG
        @pipe = open @pipefile, File::RDONLY|File::NONBLOCK unless @pipe
    end

    def read_pipe
        begin
            log.debug "About to read the pipe." if $YSDEBUG
            stats_write @pipe.read
        rescue Errno::EAGAIN
            log.debug "Nothin in the pipe." if $YSDEBUG
        end
    end

    def log
        DaemonKit.logger
    end

    def load_plugins
        @plugconfdirs.each do |pcdir|
            key = pcdir.sub(/.*g(.+)s.d/,'\1')
            Dir.glob("#{pcdir}/*.y").each do |f|
                log.debug "reading #{f}." if $YSDEBUG
                conf = YAML.load_file(f)
                name = conf[:name]
                file = "#{@mydir}/plug#{key}s/#{conf[:name]}"
                if FileTest.exists?(file)
                    begin
                        if key == 'in'
                            load file
                            # Horrible.  # a='Array'; b=Object.const_get(a); x=Class.new(b); y=x.new
                            @plugins <<  Object.const_get(name.capitalize).new(conf[:options])
                        elsif key == 'out'
                            conf[:name] = "#{@mydir}/plugouts/#{conf[:name]}"
                            @plugins << Plugout.new(conf)
                        end
                    rescue YS::NoInterval
                        log.error "Plug#{key} #{conf[:name]} has no interval. Refusing to load."
                    end
                else
                    log.error "Config file #{f} refers to #{file} but no such file exists."
                end
            end
        end
    end

    def schedule_plugins
        @plugins.each do |plugin|
            # interval vs schedule?
            DaemonKit::Cron.scheduler.every("#{plugin.interval}s", :tags => 'user') do
                log.debug "Aboot to run go for #{plugin.class}" if $YSDEBUG
                plugin.go
                if plugin.respond_to? 'stats'
                    log.debug "Aboot to run stats for #{plugin.class}" if $YSDEBUG
                    stats_write plugin.stats
                end
                if plugin.respond_to? 'monitoring'
                    puts plugin.monitoring
                end
            end
        end
    end

    def schedule_services
        DaemonKit::Cron.scheduler.every('10s', :tags => 'service') do
            read_pipe
        end
        DaemonKit::Cron.scheduler.every('310s', :tags => 'service') do
            upload_stats
        end
    end

    def stats_write(s)
        File.open(@stats_file,'a'){|f| f.write(s)}
        embiggen
    end

    def embiggen
        if FileTest.exists?(@stats_file) && File.size(@stats_file) >= @size_limit
            step_aside
        end
    end

    def step_aside
        leak = Time.now.to_i.to_s
        File.rename(@stats_file, File.join(@stats_dir,leak) ) if FileTest.exists?(@stats_file)
    end

end

class Plugout
    attr_reader :interval,:stats
    def initialize(hash)
        @name     = hash[:name]
        @interval = hash[:options][:interval]
        raise YS::NoInterval unless @interval
        @argv     = hash[:options][:argv]
        @stats    = ''
    end
    def go
        @stats = %x{#{@name} #{@argv.join(' ')}}
    end
end
