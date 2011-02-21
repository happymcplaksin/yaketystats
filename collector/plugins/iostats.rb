class Iostats
    include YS::Plugin

    # ignore are like so: vg00/var
    def initialize(options)
        @options = options
        self.interval = 60
        @fields = %w{ios/read ios/read_merges bytes/read ms/read_wait ios/write ios/write_merges
                     bytes/write ms/write_wait ios/in_flight ms/total_time_active ms/total_wait_for_all}
        @ignore = options[:ignore]
    end

    def sample(file)
        sysread(file).split.map{|s| s.to_i}
    end
    def go
        @map  = sys_dev_mash
        @data = []
        @map.each_key do |sys|
            next if ignore?(@map[sys])
            ldata = @fields.zip(sample("#{sys}/stat"))
            # turn sectors into bytes and add labels
            ldata.each do|a|
                next if /_merges$|_flight$|for_all$/.match(a[0])
                if /^byte/.match(a[0])
                    @data << ["io/#{a[0]}/#{@map[sys]}",a[1].*(512)]
                else
                    @data << ["io/#{a[0]}/#{@map[sys]}",a[1]]
                end
            end
        end
        @data = Hash[*@data.flatten]
    end
    def stats
        raise YS::NoData unless @data
        out = ''
        @data.each_pair do |thing,value|
            out << counter({:p => thing, :v => value})
        end
        out
    end
    def sys_dev_mash
        sysh = {}
        out  = {}
        sys  = '/sys/block'
        Dir.entries(sys).grep(/dm-/).sort.each do |device|
            dir = File.join(sys,device)
            dev = sysread(File.join(dir,'dev')).sub(/\d+:/,'')
            sysh[dev.to_i] = dir
        end
        dev = '/dev/mapper'
        no = %w{. .. control}
        Dir.entries(dev).-(no).each do |name|
            long   = File.join(dev,name)
            device = File::Stat.new( long ).rdev_minor
            out["#{sysh[device]}"] = decode(name)
        end
        out
    end
    def decode(name)
        vg,*label = name.split('-')
        label = label.join('-')
        "#{vg}/#{label}"
    end
end
