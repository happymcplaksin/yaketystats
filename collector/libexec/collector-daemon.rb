# Generated cron daemon

# Do your post daemonization configuration here
# At minimum you need just the first line (without the block), or a lot
# of strange things might start happening...
DaemonKit::Application.running! do |config|
  # Trap signals with blocks or procs
  # config.trap( 'INT' ) do
  #   # do something clever
  # end
  # config.trap( 'TERM', Proc.new { puts 'Going down' } )
end

# Configuration documentation available at http://rufus.rubyforge.org/rufus-scheduler/
# An instance of the scheduler is available through
# DaemonKit::Cron.scheduler

# To make use of the EventMachine-powered scheduler, uncomment the
# line below *before* adding any schedules.
# DaemonKit::EM.run

# Controller class needs to
#
# * Load puppet-friendly config files config.d style ><
# * Load plugin files
# * Handle plugin output including validation
# * Initialize plugin instances
# * Open named pipe and read from it on a schedule
# * Do uploads on a schedule
# * Do uploads right now! signal
# * Write plugin/pipe output
# * Handle writing w/o locking and file switching
# * Handle HUP / reload config
# ** means unschedule all and create new schedules for all

# Plugin improvements
#
# * Auto-add the first part of the path to be plugin.class.to_s.downcase OR:
# * You may want to override the path for a given plugin
# * Handle instances with naming an labels etc The monitoring-side of above
# * You may want to override the interval for an instance of a plugin
# * Do most gathering in #go and hand off structures to #stats and #monitor
# * Do monitoring stuff

Controller.new


# Some samples to get you going:

# Will call #regenerate_monthly_report in 3 days from starting up
#DaemonKit::Cron.scheduler.in("3d") do
#  regenerate_monthly_report()
#end
#
#DaemonKit::Cron.scheduler.every "10m10s" do
#  check_score(favourite_team) # every 10 minutes and 10 seconds
#end
#
#DaemonKit::Cron.scheduler.cron "0 22 * * 1-5" do
#  DaemonKit.logger.info "activating security system..."
#  activate_security_system()
#end
#
# Example error handling (NOTE: all exceptions in scheduled tasks are logged)
#DaemonKit::Cron.handle_exception do |job, exception|
#  DaemonKit.logger.error "Caught exception in job #{job.job_id}: '#{exception}'"
#end

#DaemonKit::Cron.scheduler.every("1m") do
  #DaemonKit.logger.debug "Scheduled task completed at #{Time.now}"
#end

# Run our 'cron' dameon, suspending the current thread
DaemonKit::Cron.run
