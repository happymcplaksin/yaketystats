class Gitlog
    include YS::Plugin
    def initialize(options)
        @options = options
        @fqdn    = options[:fqdn]
        @repo    = options[:repo]
        @value   = 0
        self.interval = 3600
    end
    def go
        Dir.chdir(@repo)
        @value = srun('git log --since="1 hour ago" --date=raw',10).split("\n").grep(/^Date:/).size
    end
    def stats
        gauge(:p => "vcs/commits", :v => @value)
    end
end
