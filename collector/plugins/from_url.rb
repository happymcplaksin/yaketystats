require 'open-uri'
# Get your stats from a URL, pre-packaged-style ><
#
# This plugin is for apps that provide a URL with YS-formattted stats
#
# options:
# * :cron => cron spec (required)
# * :url => URL from which to grab stats (required)
class From_url
    include YS::Plugin

    attr_reader :stats

    def initialize(options)
        unless options[:url] && options[:cron]
            raise YS::MissingRequiredOption
        end
        @options = options
        @stats   = ''
        @url     = options[:url]
        self.interval = 60
    end

    def go
        @stats = open(@url).read
    end
end
