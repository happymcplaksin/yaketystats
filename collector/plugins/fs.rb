require 'etc'

class Fs
    include YS::Plugin
    include YS::Nrpe

    def initialize(options)
        @options = options
        @ignore = /bcv|mnt/i #FIX
        self.interval = 60
        @timeout = 10
        @mountmap = {}
    end

    def get_mountpoints
        re = /\/dev\//
        IO.readlines('/etc/mtab').each do |l|
            bd,mp = l.split
            @mountmap[mp] = { "owner" => Etc.getpwuid(File.stat(mp).uid).name } if re.match(bd)
        end
    end

    def sad_fork(fses)
        srun("df -lP #{fses.join(' ')}",@timeout).split("\n")[1..-1]
    end

    def parse_df(a)
        labels = %w{size used available percent}
        a.each do |line|
            t = line.split
            @mountmap[t[-1]].merge!(Hash[*labels.zip(t[1..-2]).flatten])
        end
    end

    def go
        get_mountpoints
        df = sad_fork(@mountmap.keys)
        parse_df(df)
    end

    def stats
        out = ''
        @mountmap.each_key do |fs|
            next if @ignore.match(fs)
            %w{size used}.each do |thing|
                out << gauge( :p => "disk/#{thing}/#{mungefs(fs)}", :v => @mountmap[fs][thing].*(1024) )
            end
        end
        out
    end

    def mungefs(fs)
        fs = fs.sub(/\//,'-').sub(/^-/,'')
        fs = 'root' if fs.empty?
        fs
    end

    def sizing
        out = []
        @mountmap.each_key do |fs|
            next if @ignore.match(fs)
            out << "#{fs} #{@mountmap[fs]['percent'].sub(/%/,'')}"
        end
        out.join("\n") + "\n"
    end

    def ownership
        out = []
        @mountmap.each_key do |fs|
            out << "#{fs}:#{@mountmap[fs]['owner']}"
        end
        out.join(' ')
    end

    def monitor2be
        #pp @mountmap
        print decode( encode(sizing) )
        print decode( encode(ownership) )
    end
end

