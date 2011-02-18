require 'stringio'
require 'zlib'

module YS
    module Nrpe

        def deflate(s)
            f = StringIO.new
            gz = Zlib::GzipWriter.new(f,Zlib::BEST_COMPRESSION)
            gz.write(s)
            gz.close
            f.string
        end

        def encode(s)
            deflate(s).unpack("H*").first
        end

        def decode(s)
             Zlib::GzipReader.new(StringIO.new( [s.strip].pack("H*") )).read
        end

    end
end
