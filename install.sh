#!/bin/bash

if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root"
    exit 1
fi

echo "Installing WiFi Daemon..."

apt update
apt install -y gcc libjson-c-dev network-manager

echo "Compiling wifi_daemon..."
gcc -o wifi_daemon wifi_daemon.c -ljson-c

echo "Installing binary..."
cp wifi_daemon /usr/local/bin/
chmod +x /usr/local/bin/wifi_daemon

echo "Creating systemd service..."
cat > /etc/systemd/system/wifi_daemon.service << 'EOF'
[Unit]
Description=WiFi Connection Daemon
After=network.target
Wants=network.target

[Service]
Type=forking
ExecStart=/usr/local/bin/wifi_daemon
Restart=always
RestartSec=5
User=root

[Install]
WantedBy=multi-user.target
EOF

echo "Creating default configuration..."
if [ ! -f /etc/wifi_daemon.json ]; then
    cat > /etc/wifi_daemon.json << 'EOF'
{
    "ssid": "your_wifi_network",
    "password": "your_wifi_password",
    "static_ip": "192.168.1.100/24",
    "gateway": "192.168.1.1",
    "dns": "8.8.8.8",
    "check_interval": 30,
    "min_battery": 20,
    "max_temp": 80000,
    "power_mode": "normal",
    "active_days": [1, 1, 1, 1, 1, 1, 1],
    "start_hour": 0,
    "end_hour": 23,
    "disable_duration": 0,
    "disable_until": 0
}
EOF
fi

echo "Setting up web interface..."
if [ -d /var/www/html ]; then
    cp wifi_control.php /var/www/html/
    chown www-data:www-data /var/www/html/wifi_control.php
    
    echo "www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart wifi_daemon" >> /etc/sudoers.d/wifi_daemon
    chmod 440 /etc/sudoers.d/wifi_daemon
fi

systemctl daemon-reload
systemctl enable wifi_daemon
systemctl start wifi_daemon

echo "Installation completed!"
echo "Edit /etc/wifi_daemon.json to configure your WiFi settings"
echo "Web interface available at: http://your-server-ip/wifi_control.php"
echo "Check daemon status: systemctl status wifi_daemon"
echo "View logs: journalctl -u wifi_daemon -f"