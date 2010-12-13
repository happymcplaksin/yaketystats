require 'timeout'
require 'open3'

module YS
    module SafeRun
        TIMEOUT = 30

        def srun(cmd,mytimeout=TIMEOUT)
            mytimeout = @timeout unless @timeout.nil? || mytimeout != TIMEOUT
            r = ''
            begin
                timeout(mytimeout){
                    Open3.popen3(cmd) do |stdin, stdout, stderr|
                        t = Thread.new(stderr) do |terr|
                            while(line=terr.gets)
                                # what to do with it?
                                puts "stderr: #{line}"
                            end
                        end
                        # should this be a thread too?
                        r = stdout.readlines.join
                        t.join
                    end
                }
            rescue Timeout::Error
                #DaemonKit.logger.warning("Failure: #{cmd} took longer than #{timeout} and failed")
                puts "Warning: #{cmd} took longer than #{mytimeout}."
            end
            r
        end

    end
end
