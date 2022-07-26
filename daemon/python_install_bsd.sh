pkg install -y python3
pkg install -y rust
pkg install -y swig
vers=$(python3 --version|cut -d' ' -f2|awk -F'.' '{print $1$2}')
pkg install -y py${vers}-pip
pkg install -y py${vers}-virtualenv
pkg install -y py${vers}-wheel
pip install --upgrade pip