$: << File.expand_path(File.join(File.dirname(__FILE__), '../..'))
require 'lib/ys/base'
require 'lib/ys/saferun'

module YS
    module Plugin
        include YS::Base
        include YS::SafeRun
    end
end
