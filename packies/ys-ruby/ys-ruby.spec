%define rubyver         1.9.2
%define rubyminorver    p180

%{!?ysruby:             %global ysruby          /usr/local/ys/ruby}

Name:           ys-ruby
Version:        %{rubyver}%{rubyminorver}
Release:        5%{?dist}
License:        Ruby License/GPL - see COPYING
URL:            http://www.ruby-lang.org/
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildRequires:  readline readline-devel ncurses ncurses-devel gdbm gdbm-devel glibc-devel tcl-devel gcc unzip openssl-devel db4-devel byacc
Source0:        ftp://ftp.ruby-lang.org/pub/ruby/ruby-%{rubyver}-%{rubyminorver}.tar.bz2
Source1:        ys-gems-%{dist}.tar.gz
Patch0:         0001-lib-net-http.rb-Net-HTTPRequest-set_form-Added-to-su.patch
Summary:        An interpreter of object-oriented scripting language
Group:          Development/Languages
Provides: ys-ruby(abi) = 1.9
Provides: ys-ruby-irb
Provides: ys-ruby-rdoc
Provides: ys-ruby-libs
Provides: ys-ruby-devel

%description
You want poetry
I have none.

YaketyStats-Ruby is the interpreted scripting language for quick and easy
object-oriented programming.  It has many features to process text
files and to do system management tasks (as in Perl).  It is simple,
straight-forward, and extensible.

%prep
# Extract pre-built gems (Source1) into buildroot.  Note to Happy: Pretend / is buildroot.
# delete -D and/or -T so no stragglers?
%setup -q -c -n %{buildroot} -D -T -a 1

# Extract Ruby source (Source0) last because the last thing extracting does
# is cd.
%setup -q -n ruby-%{rubyver}-%{rubyminorver}
%patch0 -p1

%build
export CFLAGS="$RPM_OPT_FLAGS -Wall -fno-strict-aliasing"

./configure --prefix=%{ysruby}

make %{?_smp_mflags}

%install
make install DESTDIR=$RPM_BUILD_ROOT

# Installing local gems is problematic.  When you 'rpm -e ys-ruby', they're
# left behind.  So make sure you know what you're doing.
chmod 000 $RPM_BUILD_ROOT/usr/local/ys/ruby/bin/gem

%clean
# comment for debugging spec
#rm -rf $RPM_BUILD_ROOT

%files
%defattr(-, root, root)
%{ysruby}

%changelog
* Mon Feb 21 2011 Team Downtime <teamdowntime@example.com> - 1.9.2-p180-1
- Yup
