class Cpu
    include YS::Plugin

    # CPU stats for YS
    #
    # options:
    #
    # * :verbose => true
    # ** Turns on full stats for all cpus. W/o it, you get combined.
    def initialize(options)
        @options = options
        self.interval = 60
        @labels = %w{user nice system idle iowait irq softirq}
    end
    def sample
        IO.readlines("/proc/stat").grep(/^cpu/)
    end
    def parse(sample)
        out = {}
        sample.each do |line|
            a = line.split
            key = a[0]
            if a[0] == 'cpu'
                key = 'combined'
            end
            out[key] = a[1..-2].map{|s| s.to_i}
            # if there is only /^cpu / and /^cpu0/ then
            # you only want the combined stat.
            if sample.size == 2
                break
            end
            unless @options[:verbose]
                break
            end
        end
        out
    end
    def go
        first  = sample
        sleep  3
        last   = sample
        first  = parse(first)
        last   = parse(last)
        @data  = {}
        @total = {}
        first.each_key do |key|
            @data[key]  = first[key].zip(last[key]).map{|a| a[1] - a[0]}
            @total[key] = @data[key].inject{|a,b| a += b }.to_f
        end
    end
    def stats
        raise YS::NoData unless @data
        out = ''
        @data.each_key do |cpu|
            @labels.each_index do |i|
                percent = 0
                unless @data[cpu][i] == 0
                    percent = (@data[cpu][i].to_f / @total[cpu]) * 100.0
                end
                out << gauge( :p => "cpu/#{cpu}/#{@labels[i]}", :v => percent )
            end
        end
        out
    end
end
