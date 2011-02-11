require 'etc'
require 'rubygems'
require 'sys/filesystem'

class Fs
    include YS::Plugin
    include Sys

    def initialize(options)
        @options = options
        @ignore = /bcv|mnt/i #FIX
        self.interval = 60
        @mountpoints = []
        @map = []
    end

    def get_mountpoints
        re = /\/dev\//
        IO.readlines('/etc/mtab').each do |l|
            bd,mp = l.split
            @mountpoints << mp if re.match(bd)
        end
    end

    def go
        @map = []
        get_mountpoints
        @mountpoints.each do |mp|
            h    = {}
            fss  = Filesystem.stat(mp)
            pp fss
            #free = fss.block_size * fss.blocks_available
            free = fss.block_size * fss.blocks_free
            size = fss.fragment_size * fss.blocks
            pp mp
            pp size
            pp free
            h[:sizem]  = size.to_mb
            h[:pfree] = ((free.to_f / size.to_f) * 100).to_i
            h[:pused] = 100 - h[:pfree]
            h[:usedm] = size.-(free).to_mb
            h[:mp] = mp
            h[:owner ] = Etc.getpwuid(File::Stat.new(mp).uid).name
            @map << h
        end
        pp @map
    end
end

