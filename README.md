# Install

```shell
sudo nano /etc/systemd/system/github-receipts.service
```

```shell
[Unit]
Description=GitHub ESC/POS Receipt Printer
After=network.target

[Service]
Type=simple

# Run as root (required for /dev/usb/lp0)
User=root
Group=root

WorkingDirectory=/opt/github-receipts

# PHP built-in server
ExecStart=/usr/bin/php -d detect_unicode=0 -S 0.0.0.0:8080 index.php

Restart=always
RestartSec=3

# Hardening (safe to keep)
NoNewPrivileges=false
PrivateTmp=false

# Logging
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```shell
sudo systemctl daemon-reexec
sudo systemctl daemon-reload
sudo systemctl enable github-receipts
sudo systemctl start github-receipts
sudo systemctl status github-receipts
```