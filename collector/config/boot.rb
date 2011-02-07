DAEMON_ROOT = "#{File.expand_path(File.dirname(__FILE__))}/.." unless defined?( DAEMON_ROOT )

$:.unshift '.'
$:.unshift File.join(DAEMON_ROOT,'lib')
require 'yaml'
require 'pp'
require 'fileutils'
require 'ys'
require 'syslog_logger'
require 'collector'

$YSDEBUG = true

# simplified version of DaemonKit. Lots of code stolen from it. <3 u!
module YsDaemon
    class << self
        def boot!
            Log.new.debug("YS Collector booting at #{Time.now}!") if $YSDEBUG
            Controller.start
        end
    end
    class Log
        def initialize
            @logger = SyslogLogger.new('collector')
        end
        def severities
            {
              :debug => Logger::DEBUG,
              :info => Logger::INFO,
              :warn => Logger::WARN,
              :error => Logger::ERROR,
              :fatal => Logger::FATAL,
              :unknown => Logger::UNKNOWN
            }
        end
        def debug(msg=nil)
            add(:debug,msg)
        end
        def error(msg=nil)
            add(:error,msg)
        end
        def info(msg=nil)
            add(:info,msg)
        end
        def warn(msg=nil)
            add(:warn,msg)
        end
        def add(sev,msg)
            pp sev
            pp msg
            @logger.add(severities[ sev ]) { msg }
        end
    end
    module Pidfile
        class << self
            def name
                File.join(DAEMON_ROOT,"run/collector.pid") # bah, fix this
            end
            def write
                File.open(Pidfile.name, 'w') {|f| f << "#{Process.pid}\n"}
            end
            def read
                IO.read(Pidfile.name).to_i rescue nil
            end
        end
    end
    module Config
        class << self
            def load(file)
                file += '.yml' unless file =~ /\.yml$/
                path  = File.join( DAEMON_ROOT, 'config', file )
                raise ArgumentError, "Can't find #{path}" unless File.exists?( path )
                return YAML.load_file( path )
            end
        end
    end
    module Controller
        class << self
            def check_user
                # force to run as stats?
                # or at least complain when root?
            end
            def start
                #Process.daemon
                pp Process.pid
                Pidfile.write
                trap("TERM") {Controller.stop; exit}
                trap("INT") {Controller.stop; exit}
                Collector.new
            end
            def stop
                pid = Pidfile.read
                if pid.nil?
                    if ! File.file?(Pidfile.name)
                        puts "Pid file not found. Is the daemon started?"
                        exit 1
                    end
                else
                    FileUtils.rm(Pidfile.name)
                end
                if Process.pid == pid
                    exit
                else
                    Process.kill("TERM", pid)
                end
            end
        end
    end
end

YsDaemon.boot!
