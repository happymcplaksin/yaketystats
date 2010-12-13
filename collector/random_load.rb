#!/home/sam/ruby-ee/bin/ruby

require 'benchmark'

loop do
    t = []
    time = Benchmark.measure do
        10.times do
            t << Thread.new do
                %x{ls -lR / 2>/dev/null}
            end
        end
        t.each{|thread| thread.join}
    end
    puts time
    puts "about to sleep"
    sleep rand(1000)
    puts "done sleeping"

end
