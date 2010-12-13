# Your starting point for daemon specific classes. This directory is
# already included in your load path, so no need to specify it.

require 'ys'
require 'pp'
require 'ys/plugin'
require 'yaml'

class Controller
    def initialize
        @plugins     = []
        @mydir       = File.expand_path(File.join(File.dirname(__FILE__), '..'))
        @plugconfdirs= [File.join(@mydir,'etc/plugins.d'), File.join(@mydir,'etc/plugouts.d')]
        open_pipe
        load_plugins
        init_plugins
    end

    def reload
        unload_plugins
        load_plugins
        init_plugins
    end

    def unload_plugins
    end

    def open_pipe
        @pipefile = File.join(@mydir,'run/bucket')
        unless FileTest.exists?(@pipefile)
            system "mkfifo #{@pipefile}"
        end
    end

    def load_plugins
        @plugconfdirs.each do |pcdir|
            Dir.chdir(pcdir)
            key = pcdir.sub(/.*g(.+)s.d/,'\1')
            Dir.glob('*.y').each do |f|
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
                        DaemonKit.logger.warning "Plug#{key} #{conf[:name]} has no interval. Refusing to load."
                    end
                else
                    DaemonKit.logger.warning "Config file #{f} refers to #{file} but no such file exists."
                end
            end
        end
    end

    def init_plugins
        @plugins.each do |plugin|
            DaemonKit::Cron.scheduler.every("#{plugin.interval}s") do
                plugin.go
                if plugin.respond_to? 'stats'
                    puts plugin.stats
                end
                if plugin.respond_to? 'monitoring'
                    puts plugin.monitoring
                end
            end
        end
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
