# Why? rubygems adds 40M rss in our experience.
$:.unshift '/usr/local/ys/ruby/lib/ruby/gems/1.9.1/gems/rufus-scheduler-2.0.8/lib'
$:.unshift '/usr/local/ys/ruby/lib/ruby/gems/1.9.1/gems/nokogiri-1.4.4/lib'
# check perms&existance on the YS dirs
# check for existance of /var/yaketystats/fqdn

require 'rufus/scheduler'
require 'nokogiri'
require 'ys'
require 'collector/http'

class Collector
    include YS::Base
    include Pusher
    def initialize
        @plugins      = []
        @config       = YsDaemon::Config.load('collector')
        @pipefile     = '/var/run/ys/bucket'
        @rundir       = File.join(DAEMON_ROOT,'run')
        @pipe         = nil
        @pipefrag     = ''
        @plugconfdirs = [File.join(DAEMON_ROOT,'etc/plugins.d'), File.join(DAEMON_ROOT,'etc/plugouts.d')]
        @stats_dir    = @config["stats_dir"]
        @stats_server = @config["stats_server"]
        @store_path   = @config["store_path"]
        @stats_file   = "#{@stats_dir}/new"
        @size_limit   = 500000
        @scheduler    = Rufus::Scheduler.start_new
        @logger       = YsDaemon::Log.new
        FileUtils.mkdir_p(@stats_dir) unless FileTest.exists?(@stats_dir)
        open_pipe
        load_plugins
        schedule_plugins
        schedule_services
        @scheduler.join
    end

    def reload
        log.info "Reload requested."
        unschedule_plugins
        @plugins = []
        load_plugins
        schedule_plugins
    end

    def unschedule_plugins
        @scheduler.find_by_tag('user').map{|job| job.unschedule}
    end

    def open_pipe
        unless FileTest.exists?(@pipefile)
            FileUtils.mkdir_p(DAEMON_ROOT) unless FileTest.exists?(DAEMON_ROOT)
            system "mkfifo -m 775 #{@pipefile}"
        end
        log.debug "About to open the pipe." if $YSDEBUG
        @pipe = open @pipefile, File::RDONLY|File::NONBLOCK unless @pipe
    end

    def read_pipe
        log.debug "About to read the pipe." if $YSDEBUG
        if @pipe_locked
            log.warning "Attempted to read the pipe, but it was locked."
            return
        end
        @pipe_locked = true
        # Pipes aren't UDP packets, you have to be careful when you read them lest you get partial lines.
        # All of this mess is to avoid partial lines. Nothing is ever easy.
        # Q: Can multiple processes write to a pipe simultaneous-style >< ?
        # A: http://www.steve.org.uk/Reference/Unix/faq_3.html#SEC45
        # Q: Linux PIPE_BUF ?
        # A: 4096 from http://linux.die.net/man/7/pipe
        data = @pipe.read
        data.each_line do |frag|
            frag = "#{@pipefrag}#{frag}"
            @pipefrag = ''
            if frag.include?("\n")
                if valid?(frag)
                    stats_write frag
                else
                    log.warn "Invalid line: #{frag.strip}"
                end
            else
                log.warn "Fragment: #{frag}"
                @pipefrag = frag
            end
        end
        @pipe_locked = false
    end

    def log
        @logger
    end

    def load_plugins
        @plugconfdirs.each do |pcdir|
            key = pcdir.sub(/.*g(.+)s.d/,'\1') # in or out
            Dir.glob("#{pcdir}/*.y").each do |f|
                log.debug "reading #{f}." if $YSDEBUG
                conf = YAML.load_file(f)
                name = conf[:name]
                file = "#{DAEMON_ROOT}/plug#{key}s/#{conf[:name]}"
                file += '.rb' if key == 'in'
                if FileTest.exists?(file)
                    begin
                        if key == 'in'
                            load file
                            # Horrible.  # a='Array'; b=Object.const_get(a); x=Class.new(b); y=x.new
                            @plugins <<  Object.const_get(name.capitalize).new(conf[:options])
                        elsif key == 'out'
                            conf[:name] = "#{DAEMON_ROOT}/plugouts/#{conf[:name]}"
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
            @scheduler.every("#{plugin.interval}s", :tags => 'user') do
                log.debug "#{plugin.class} GO" if $YSDEBUG
                unless plugin.locked?
                    plugin.lock
                    begin
                        plugin.go
                    rescue
                        log.error $!
                    end
                    plugin.unlock
                    log.debug "post-go for #{plugin.class}" if $YSDEBUG
                    if plugin.respond_to? 'stats'
                        log.debug "#{plugin.class} STATS" if $YSDEBUG
                        plugin.lock
                        begin
                            stats_write plugin.stats
                        rescue
                            log.error $!
                        end
                        plugin.unlock
                    else
                        log.debug "#{plugin.class} has no #stats" if $YSDEBUG
                    end
                    if plugin.respond_to? 'monitor'
                        plugin.lock
                        begin
                            puts plugin.monitor
                        rescue
                            log.error $!
                        end
                        plugin.unlock
                    end
                else
                    log.warn "#{plugin.class} locked during subsequent attempt to run."
                end
            end
        end
    end

    def schedule_services
        @scheduler.every('10s', :tags => 'service') do
            read_pipe
        end
        @scheduler.every('310s', :tags => 'service') do
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
    # fix this to use saferun
    def go
        @stats = %x{#{@name} #{@argv.join(' ')}}
    end
end
