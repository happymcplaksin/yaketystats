require 'open-uri'
# Get your stats from a URL, pre-packaged-style ><
# This plugin is for apps that provie a URL with YS-formattted stats
#
# Requires cron syntax and a url
class From_url
    include YS::Plugin

    attr_reader :stats

    def initialize(options)
        @options = options
        @stats   = ''
        unless options[:url] && options[:cron]
            raise YS::MissingRequiredOption
        end
        @url     = options[:url]
        self.interval = 60
    end

    def go
        @stats = open(@url).read
    end
end
