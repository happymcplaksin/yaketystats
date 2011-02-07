# Boot up
require File.join(File.dirname(__FILE__), 'boot')

DaemonKit::Initializer.run do |config|

  # The name of the daemon as reported by process monitoring tools
  config.daemon_name = 'collector'

  # Force the daemon to be killed after X seconds from asking it to
  # config.force_kill_wait = 30

  # Log backraces when a thread/daemon dies (Recommended)
  # config.backtraces = true
  config.log_path = :syslog

  # Configure the safety net (see DaemonKit::Safety)
  # config.safety_net.handler = :hoptoad
  # config.safety_net.hoptoad.api_key = ''
end
