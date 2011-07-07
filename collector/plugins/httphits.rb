require 'zlib'
class Httphits
    include YS::Plugin
    # Stupid/cheap line counter for gzipped lines
    #
    # options:
    #
    # * file (required)
    # * label (required)
    def initialize(options)
        @options = options
        @file    = options[:file] || nil
        @label   = options[:label]
        self.interval = 86400
    end
    def go
        @stat = 0
        if FileTest.exists?(@file)
             @stat = Zlib::GzipReader.open(@file){|gz| gz.readlines.size}
        end
    end
    def stats
        gauge(:p => "apache/hits/#{@label}", :v => @stat)
    end
end
